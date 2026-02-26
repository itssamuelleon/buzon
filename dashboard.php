<?php 
// Load config first (before any output)
require_once 'config.php';

// Redirect if not logged in (must be before header.php sends any output)
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('dashboard.php'));
    exit;
}

// Check if user can access dashboard
if (!canAccessDashboard()) {
    header('Location: index.php');
    exit;
}

$page_title = 'Dashboard - ITSCC Buzón'; 
include 'components/header.php'; 
require_once 'status_helper.php'; 
?>
    <?php 
    
    // Allow all logged-in users to view the reports
    // Only admins can modify them (handled in view_complaint.php)
    
    // Get filter parameters (support arrays for multi-select)
    $departments = isset($_GET['department']) ? (is_array($_GET['department']) ? $_GET['department'] : [$_GET['department']]) : [];
    $departments = array_filter($departments, fn($v) => $v !== '');
    $statuses = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [];
    $statuses = array_filter($statuses, fn($v) => $v !== '');
    $categories = isset($_GET['category']) ? (is_array($_GET['category']) ? $_GET['category'] : [$_GET['category']]) : [];
    $categories = array_filter($categories, fn($v) => $v !== '');
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_year';
    
    // Build query
    $query = "SELECT DISTINCT c.*, u.name as user_name, u.email as user_email, cat.name as category_name,
                     GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR '||') as department_names,
                     GROUP_CONCAT(d.id ORDER BY d.name SEPARATOR ',') as department_ids,
                     (SELECT COUNT(*) FROM attachments ca WHERE ca.complaint_id = c.id) as attachment_count,
                     c.folio
              FROM complaints c 
              LEFT JOIN users u ON c.user_id = u.id 
              LEFT JOIN categories cat ON c.category_id = cat.id 
              LEFT JOIN complaint_departments cd ON c.id = cd.complaint_id
              LEFT JOIN departments d ON cd.department_id = d.id 
              WHERE 1=1";
    
    // Check if dashboard restriction is enabled (used for manager filtering)
    $stmt_restrict_early = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'restrict_dashboard_access'");
    $stmt_restrict_early->execute();
    $result_restrict_early = $stmt_restrict_early->get_result();
    $is_dashboard_restricted_for_managers = false;
    if ($row_restrict_early = $result_restrict_early->fetch_assoc()) {
        $is_dashboard_restricted_for_managers = $row_restrict_early['setting_value'] == '1';
    }
    
    // If user is a manager (not admin) AND dashboard restriction is enabled, only show complaints assigned to their departments
    // If restriction is disabled, managers can see all reports
    if (!isAdmin() && isset($_SESSION['role']) && $_SESSION['role'] === 'manager' && $is_dashboard_restricted_for_managers) {
        // Get manager's email to find their departments
        $manager_email = $_SESSION['email'];
        
        // Get department IDs where this manager is assigned
        $stmt_dept = $conn->prepare("SELECT id FROM departments WHERE email = ?");
        $stmt_dept->bind_param("s", $manager_email);
        $stmt_dept->execute();
        $result_dept = $stmt_dept->get_result();
        $manager_dept_ids = [];
        while ($row_dept = $result_dept->fetch_assoc()) {
            $manager_dept_ids[] = $row_dept['id'];
        }
        
        // If manager has departments, filter by them
        if (!empty($manager_dept_ids)) {
            $dept_ids_str = implode(',', array_map('intval', $manager_dept_ids));
            $query .= " AND EXISTS (
                SELECT 1 FROM complaint_departments cd_mgr 
                WHERE cd_mgr.complaint_id = c.id 
                AND cd_mgr.department_id IN ($dept_ids_str))";
        } else {
            // Manager has no departments assigned, show nothing
            $query .= " AND 1=0";
        }
    }
    
    // Multi-select filters
    if (!empty($categories)) {
        $cat_ids = implode(',', array_map('intval', $categories));
        $query .= " AND c.category_id IN ($cat_ids)";
    }
    if (!empty($departments)) {
        $dept_ids = implode(',', array_map('intval', $departments));
        $query .= " AND EXISTS (
            SELECT 1 FROM complaint_departments cd2 
            WHERE cd2.complaint_id = c.id 
            AND cd2.department_id IN ($dept_ids))";
    }
    if (!empty($statuses)) {
        $escaped_statuses = array_map(fn($s) => "'" . $conn->real_escape_string($s) . "'", $statuses);
        $query .= " AND c.status IN (" . implode(',', $escaped_statuses) . ")";
    }
    if ($search !== '') {
        $escaped = $conn->real_escape_string($search);
        $like = "'%" . $escaped . "%'";
        $query .= " AND (c.folio LIKE $like OR c.description LIKE $like)";
    }
    if ($date_range && $date_range !== 'all') {
        if ($date_range === 'this_year') {
            $startOfYear = (new DateTime('first day of january ' . date('Y')))->format('Y-m-d 00:00:00');
            $query .= " AND c.created_at >= '" . $conn->real_escape_string($startOfYear) . "'";
        } else {
            $query .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL " . intval($date_range) . " DAY)";
        }
    }
    
    // Get quick statistics (Admins and Managers)
    $stats = ['total' => 0, 'unattended' => 0, 'attended' => 0, 'unassigned' => 0,
              'unattended_ontime' => 0, 'unattended_late' => 0, 'attended_ontime' => 0, 'attended_late' => 0];
    if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))) {
        // If user is a manager AND dashboard is restricted, filter stats by their departments
        $is_manager = !isAdmin() && isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
        $stats_dept_filter = "";
        
        if ($is_manager && $is_dashboard_restricted_for_managers) {
            // Get manager's department IDs
            $manager_email = $_SESSION['email'];
            $stmt_mgr_dept = $conn->prepare("SELECT id FROM departments WHERE email = ?");
            $stmt_mgr_dept->bind_param("s", $manager_email);
            $stmt_mgr_dept->execute();
            $result_mgr_dept = $stmt_mgr_dept->get_result();
            $mgr_dept_ids = [];
            while ($row_mgr_dept = $result_mgr_dept->fetch_assoc()) {
                $mgr_dept_ids[] = $row_mgr_dept['id'];
            }
            
            if (!empty($mgr_dept_ids)) {
                $mgr_dept_ids_str = implode(',', array_map('intval', $mgr_dept_ids));
                $stats_dept_filter = " WHERE EXISTS (
                    SELECT 1 FROM complaint_departments cd_stats 
                    WHERE cd_stats.complaint_id = c.id 
                    AND cd_stats.department_id IN ($mgr_dept_ids_str))";
            } else {
                // Manager has no departments, show zero stats
                $stats_dept_filter = " WHERE 1=0";
            }
        }
        
        $stats_query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('unattended_ontime', 'unattended_late') THEN 1 ELSE 0 END) as unattended,
            SUM(CASE WHEN status IN ('attended_ontime', 'attended_late') THEN 1 ELSE 0 END) as attended,
            SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM complaint_departments cd WHERE cd.complaint_id = c.id) THEN 1 ELSE 0 END) as unassigned,
            SUM(CASE WHEN status = 'unattended_ontime' THEN 1 ELSE 0 END) as unattended_ontime,
            SUM(CASE WHEN status = 'unattended_late' THEN 1 ELSE 0 END) as unattended_late,
            SUM(CASE WHEN status = 'attended_ontime' THEN 1 ELSE 0 END) as attended_ontime,
            SUM(CASE WHEN status = 'attended_late' THEN 1 ELSE 0 END) as attended_late
        FROM complaints c" . $stats_dept_filter;
        $stats_result = $conn->query($stats_query);
        if ($stats_result) {
            $stats = $stats_result->fetch_assoc();
        }
    }

    // Pagination setup
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Build count query with the same filters
    $countQuery = "SELECT COUNT(DISTINCT c.id) as total
                   FROM complaints c 
                   LEFT JOIN users u ON c.user_id = u.id 
                   LEFT JOIN categories cat ON c.category_id = cat.id 
                   LEFT JOIN complaint_departments cd ON c.id = cd.complaint_id
                   LEFT JOIN departments d ON cd.department_id = d.id 
                   WHERE 1=1";
    
    // Apply same manager filter to count query (only if restriction is enabled)
    if (!isAdmin() && isset($_SESSION['role']) && $_SESSION['role'] === 'manager' && $is_dashboard_restricted_for_managers) {
        $manager_email = $_SESSION['email'];
        $stmt_dept2 = $conn->prepare("SELECT id FROM departments WHERE email = ?");
        $stmt_dept2->bind_param("s", $manager_email);
        $stmt_dept2->execute();
        $result_dept2 = $stmt_dept2->get_result();
        $manager_dept_ids2 = [];
        while ($row_dept2 = $result_dept2->fetch_assoc()) {
            $manager_dept_ids2[] = $row_dept2['id'];
        }
        
        if (!empty($manager_dept_ids2)) {
            $dept_ids_str2 = implode(',', array_map('intval', $manager_dept_ids2));
            $countQuery .= " AND EXISTS (
                SELECT 1 FROM complaint_departments cd_mgr2 
                WHERE cd_mgr2.complaint_id = c.id 
                AND cd_mgr2.department_id IN ($dept_ids_str2))";
        } else {
            $countQuery .= " AND 1=0";
        }
    }
    // Multi-select filters for count
    if (!empty($categories)) {
        $cat_ids = implode(',', array_map('intval', $categories));
        $countQuery .= " AND c.category_id IN ($cat_ids)";
    }
    if (!empty($departments)) {
        $dept_ids = implode(',', array_map('intval', $departments));
        $countQuery .= " AND EXISTS (
            SELECT 1 FROM complaint_departments cd2 
            WHERE cd2.complaint_id = c.id 
            AND cd2.department_id IN ($dept_ids))";
    }
    if (!empty($statuses)) {
        $escaped_statuses = array_map(fn($s) => "'" . $conn->real_escape_string($s) . "'", $statuses);
        $countQuery .= " AND c.status IN (" . implode(',', $escaped_statuses) . ")";
    }
    if ($search !== '') {
        $escaped = $conn->real_escape_string($search);
        $like = "'%" . $escaped . "%'";
        $countQuery .= " AND (c.folio LIKE $like OR c.description LIKE $like)";
    }
    if ($date_range && $date_range !== 'all') {
        if ($date_range === 'this_year') {
            $startOfYear = (new DateTime('first day of january ' . date('Y')))->format('Y-m-d 00:00:00');
            $countQuery .= " AND c.created_at >= '" . $conn->real_escape_string($startOfYear) . "'";
        } else {
            $countQuery .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL " . intval($date_range) . " DAY)";
        }
    }
    $countRes = $conn->query($countQuery);
    $total_rows = 0;
    if ($countRes) {
        $rowCount = $countRes->fetch_assoc();
        $total_rows = isset($rowCount['total']) ? intval($rowCount['total']) : 0;
    }
    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    $query .= " GROUP BY c.id, c.description, c.status, c.created_at, c.attended_at, c.is_anonymous, c.user_id, u.name, u.email
              ORDER BY c.created_at DESC
              LIMIT " . intval($per_page) . " OFFSET " . intval($offset);
    
    $result = $conn->query($query);
    
    // Get categories and departments for filter
    $cat_result = $conn->query("SELECT * FROM categories ORDER BY name");
    $dept_result = $conn->query("SELECT * FROM departments ORDER BY name");
    
    // Verificar si hay correos fallidos en la cola (solo admins)
    $failed_emails_count = 0;
    $failed_emails_error = '';
    if (isAdmin()) {
        $failed_q = $conn->query("SELECT COUNT(*) as cnt, MAX(error_message) as last_error FROM email_queue WHERE status = 'failed'");
        if ($failed_q) {
            $failed_row = $failed_q->fetch_assoc();
            $failed_emails_count = intval($failed_row['cnt']);
            $failed_emails_error = $failed_row['last_error'] ?? '';
        }
    }
    ?>

    <main class="container mx-auto px-4 py-6">
        <?php if (isAdmin() && $failed_emails_count > 0): ?>
        <div class="mb-4 bg-amber-50 border border-amber-300 text-amber-800 px-5 py-3 rounded-lg flex items-center gap-3 shadow-sm">
            <i class="ph-warning text-xl text-amber-500 flex-shrink-0"></i>
            <div class="flex-1">
                <span class="font-semibold"><?php echo $failed_emails_count; ?> correo(s) no se pudieron enviar.</span>
                <?php if ($failed_emails_error): ?>
                    <span class="text-amber-700 text-sm ml-1"><?php echo htmlspecialchars($failed_emails_error); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                <?php if (isAdmin() && intval($stats['unassigned']) > 0): ?>
                <button type="button" 
                        onclick="openBulkAnalyzeModal()"
                        class="text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1"
                        title="Analizar todos los reportes sin asignar con IA">
                    <i class="ph-sparkle text-base"></i>
                    Analizar con IA
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Quick Stats (Admins and Managers) -->
            <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
            <div class="flex items-center gap-2 bg-white rounded-lg shadow-sm border border-gray-200 p-1.5 flex-wrap">
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Total</span>
                        <span class="text-lg font-bold text-gray-900 leading-none"><?php echo $stats['total']; ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-green-600 font-semibold">Atendido (a tiempo)</span>
                        <span class="text-lg font-bold text-green-600 leading-none"><?php echo intval($stats['attended_ontime']); ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-emerald-700 font-semibold">Atendido (a destiempo)</span>
                        <span class="text-lg font-bold text-emerald-700 leading-none"><?php echo intval($stats['attended_late']); ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-blue-600 font-semibold">Sin atender (a tiempo)</span>
                        <span class="text-lg font-bold text-blue-600 leading-none"><?php echo intval($stats['unattended_ontime']); ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-orange-600 font-semibold">Sin atender (retrasado)</span>
                        <span class="text-lg font-bold text-orange-600 leading-none"><?php echo intval($stats['unattended_late']); ?></span>
                    </div>
                </div>
                <?php 
                // Only show "Sin Asignar" if user is admin OR if dashboard is not restricted for managers
                $show_unassigned_stat = isAdmin() || !$is_dashboard_restricted_for_managers;
                if ($show_unassigned_stat): 
                ?>
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-red-600 font-semibold">Sin Asignar</span>
                        <span class="text-lg font-bold text-red-600 leading-none"><?php echo $stats['unassigned']; ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <a href="statistics.php" class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors flex items-center gap-1">
                    <i class="ph-chart-line text-base"></i>
                    Ver más
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Compact Filters -->
        <form method="GET" class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 mb-6" x-data="{ showFilters: false }">
            <div class="flex flex-col md:flex-row md:items-end gap-3">
                <!-- Search Bar & Toggle (Always Visible) -->
                <div class="flex items-center gap-2 md:flex-1" x-data="{ query: '<?php echo htmlspecialchars($search, ENT_QUOTES); ?>' }">
                    <div class="flex-grow relative">
                        <label for="q" class="hidden md:block text-xs font-medium text-gray-500 mb-1">Búsqueda</label>
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none md:top-5">
                            <i class="ph-magnifying-glass text-gray-400"></i>
                        </div>
                        <input type="text" id="q" name="q" x-model="query"
                            placeholder="Folio o descripción..."
                            class="block w-full pl-8 pr-8 py-2 text-sm rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                        
                        <!-- Clear Search X Button -->
                        <button type="button" 
                                x-show="query.length > 0" 
                                @click="query = ''; $nextTick(() => $el.closest('div').querySelector('input').focus())"
                                class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                                style="display: none;">
                            <i class="ph-x-circle text-lg bg-white"></i>
                        </button>
                    </div>
                    
                    <!-- Mobile Search Button (Visible only when filters are collapsed) -->
                    <button type="submit" 
                            x-show="!showFilters"
                            class="md:hidden inline-flex items-center justify-center p-2 rounded-md border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm">
                        <i class="ph-magnifying-glass text-lg"></i>
                    </button>

                    <!-- Mobile Filter Toggle -->
                    <button type="button" 
                            @click="showFilters = !showFilters"
                            class="md:hidden inline-flex items-center justify-center p-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="ph-funnel text-lg" :class="showFilters ? 'text-blue-600' : 'text-gray-500'"></i>
                    </button>


                </div>

                <!-- Collapsible Filters (Hidden on Mobile by default, Visible on Desktop) -->
                <div class="flex-wrap items-end gap-3 md:flex" 
                     :class="showFilters ? 'flex' : 'hidden'">
                    
                    <div class="w-full sm:w-48">
                        <label for="category" class="block text-xs font-medium text-gray-500 mb-1">Categorías</label>
                        <select id="category" name="category[]" multiple class="tom-select-multi block w-full text-sm" placeholder="Todas">
                            <?php 
                            $cat_result->data_seek(0);
                            while ($cat = $cat_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo in_array($cat['id'], $categories) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if (function_exists('isAdmin') ? isAdmin() : false): ?>
                    <div class="w-full sm:w-48">
                        <label for="department" class="block text-xs font-medium text-gray-500 mb-1">Departamentos</label>
                        <select id="department" name="department[]" multiple class="tom-select-multi block w-full text-sm" placeholder="Todos">
                            <?php 
                            $dept_result->data_seek(0);
                            while ($dept = $dept_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo in_array($dept['id'], $departments) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
                    <div class="w-full sm:w-48">
                        <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Estados</label>
                        <select id="status" name="status[]" multiple class="tom-select-multi block w-full text-sm" placeholder="Todos">
                            <option value="unattended_ontime" <?php echo in_array('unattended_ontime', $statuses) ? 'selected' : ''; ?>>Sin atender (a tiempo)</option>
                            <option value="unattended_late" <?php echo in_array('unattended_late', $statuses) ? 'selected' : ''; ?>>Sin atender (tarde)</option>
                            <option value="attended_ontime" <?php echo in_array('attended_ontime', $statuses) ? 'selected' : ''; ?>>Atendido</option>
                            <option value="attended_late" <?php echo in_array('attended_late', $statuses) ? 'selected' : ''; ?>>Atendido a destiempo</option>
                            <option value="invalid" <?php echo in_array('invalid', $statuses) ? 'selected' : ''; ?>>Inválido</option>
                            <option value="duplicate" <?php echo in_array('duplicate', $statuses) ? 'selected' : ''; ?>>Duplicado</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="w-full sm:w-40">
                        <label for="date_range" class="block text-xs font-medium text-gray-500 mb-1">Fecha</label>
                        <select id="date_range" name="date_range" class="tom-select-single block w-full text-sm">
                            <option value="this_year" <?php echo $date_range == 'this_year' || $date_range == '' ? 'selected' : ''; ?>>Este año</option>
                            <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Últimos 7 días</option>
                            <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Últimos 30 días</option>
                            <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Últimos 90 días</option>
                            <option value="all" <?php echo $date_range == 'all' ? 'selected' : ''; ?>>Todo el tiempo</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <?php 
                        $hasActiveFilters = !empty($departments) || !empty($categories) || 
                                          !empty($statuses) || !empty($_GET['q']) ||
                                          (isset($_GET['date_range']) && $_GET['date_range'] !== 'this_year' && $_GET['date_range'] !== '');
                        if ($hasActiveFilters):
                        ?>
                            <a href="dashboard.php" class="flex-1 md:flex-none inline-flex justify-center items-center px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                                <i class="ph-trash mr-1"></i>
                                Limpiar
                            </a>
                        <?php endif; ?>
                        
                        <!-- Mobile Apply Button -->
                        <button type="submit" class="md:hidden flex-1 inline-flex justify-center items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 shadow-sm transition-colors">
                            <i class="ph-magnifying-glass mr-1.5"></i>
                            Aplicar Filtros
                        </button>

                        <!-- Desktop Filter Button (Hidden on Mobile) -->
                        <button type="submit" class="hidden md:inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 shadow-sm transition-colors">
                            <i class="ph-magnifying-glass mr-1.5"></i>
                            Buscar
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <?php if (!empty($search)): ?>
            <div class="mb-4 text-gray-600 text-sm">
                Resultados de la búsqueda <span class="font-semibold">"<?php echo htmlspecialchars($search); ?>"</span> 
                <span class="text-gray-500">(<?php echo $total_rows; ?> <?php echo $total_rows == 1 ? 'coincidencia' : 'coincidencias'; ?>)</span>
            </div>
        <?php endif; ?>

            <!-- Complaints List -->
            <!-- Complaints List -->
            <?php 
            // Fetch all rows to be used in both mobile and desktop views
            $complaints = [];
            while ($row = $result->fetch_assoc()) {
                $complaints[] = $row;
            }
            ?>

            <!-- Mobile View (Cards) -->
            <div class="md:hidden space-y-4">
                <?php foreach ($complaints as $row): ?>
                    <?php
                        $createdDate = new DateTime($row['created_at']);
                        $now = new DateTime();
                        $attendedDate = !empty($row['attended_at']) ? new DateTime($row['attended_at']) : null;
                        
                        // Description logic
                        $rawDescription = isset($row['description']) ? $row['description'] : '';
                        $cleanDescription = trim(strip_tags($rawDescription));
                        $maxLen = 100;
                        if (function_exists('mb_strlen')) {
                            $descLen = mb_strlen($cleanDescription, 'UTF-8');
                            $shortDescription = $descLen > $maxLen ? mb_substr($cleanDescription, 0, $maxLen, 'UTF-8') . '…' : $cleanDescription;
                        } else {
                            $descLen = strlen($cleanDescription);
                            $shortDescription = $descLen > $maxLen ? substr($cleanDescription, 0, $maxLen) . '…' : $cleanDescription;
                        }

                        // Status logic
                        $statusInfo = getStatusDisplayInfo($row['status']);
                        
                        // Time ago logic
                        $interval = $createdDate->diff($now);
                        $daysAgo = $interval->days;
                        $timeAgoText = $daysAgo === 0 ? 'Hoy' : ($daysAgo === 1 ? 'Ayer' : "Hace $daysAgo días");
                    ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-500">#<?php echo $row['folio'] ?? str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
                                    <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full <?php echo $statusInfo['class']; ?> ring-1 ring-inset whitespace-nowrap">
                                        <?php echo $statusInfo['text']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="view_complaint.php?id=<?php echo $row['id']; ?>" class="text-xs font-medium text-blue-600 flex items-center gap-1 hover:text-blue-800">
                                Detalles <i class="ph-caret-right font-bold"></i>
                            </a>
                        </div>

                        <h3 class="text-sm font-medium text-gray-900 mb-2 line-clamp-2 leading-snug"><?php echo htmlspecialchars($shortDescription); ?></h3>
                        
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <i class="ph-tag text-gray-400"></i>
                                <span class="truncate max-w-[140px]"><?php echo $row['category_name'] ? htmlspecialchars($row['category_name']) : 'Sin categoría'; ?></span>
                            </div>
                            <div class="text-gray-300 hidden sm:block">•</div>
                            <div class="flex items-center gap-1.5">
                                <i class="ph-clock text-gray-400"></i>
                                <span><?php echo $timeAgoText; ?></span>
                            </div>
                            <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
                                <div class="w-full flex items-start gap-1.5 pt-1 border-t border-gray-50 mt-0.5">
                                    <i class="ph-buildings-light text-gray-400 mt-0.5"></i>
                                    <div class="flex-1 truncate">
                                        <?php if (empty($row['department_names'])): ?>
                                            <span class="italic text-yellow-600">Sin asignación</span>
                                        <?php else: 
                                            $department_names = explode('||', $row['department_names']);
                                            echo htmlspecialchars(implode(', ', $department_names));
                                        endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($complaints)): ?>
                    <div class="text-center py-8 text-gray-500">
                        No se encontraron reportes.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Desktop View (Table) -->
            <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Folio
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-96">
                                Descripción
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Categoría
                            </th>
                            <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                                Departamentos
                            </th>
                            <?php else: ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                                Usuario
                            </th>
                            <?php endif; ?>
                            <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-44">
                                Estado
                            </th>
                            <?php endif; ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fecha
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($complaints as $row): ?>
                            <tr>
                                <?php
                                    $createdDate = new DateTime($row['created_at']);
                                    $now = new DateTime();
                                    $attendedDate = !empty($row['attended_at']) ? new DateTime($row['attended_at']) : null;
                                ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="flex items-center gap-2">
                                        <span>#<?php echo $row['folio'] ?? str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        <?php if (isset($row['attachment_count']) && $row['attachment_count'] > 0): ?>
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-blue-100 text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                                <i class="ph-paperclip text-xs"></i>
                                                <?php echo $row['attachment_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php
                                        $rawDescription = isset($row['description']) ? $row['description'] : '';
                                        $cleanDescription = trim(strip_tags($rawDescription));
                                        $maxLen = 140;
                                        if (function_exists('mb_strlen')) {
                                            $descLen = mb_strlen($cleanDescription, 'UTF-8');
                                            $shortDescription = $descLen > $maxLen
                                                ? mb_substr($cleanDescription, 0, $maxLen, 'UTF-8') . '…'
                                                : $cleanDescription;
                                        } else {
                                            $descLen = strlen($cleanDescription);
                                            $shortDescription = $descLen > $maxLen
                                                ? substr($cleanDescription, 0, $maxLen) . '…'
                                                : $cleanDescription;
                                        }
                                    ?>
                                    <span class="text-gray-900"><?php echo htmlspecialchars($shortDescription); ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="flex items-center gap-2">
                                        <i class="ph-tag text-gray-400"></i>
                                        <span><?php echo $row['category_name'] ? htmlspecialchars($row['category_name']) : 'Sin categoría'; ?></span>
                                    </div>
                                </td>
                                <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php if (empty($row['department_names'])): ?>
                                        <div class="flex items-center gap-2 text-gray-500 italic">
                                            <i class="ph-warning-circle text-yellow-500"></i>
                                            <span>Sin asignación</span>
                                        </div>
                                    <?php else: 
                                        $department_names = explode('||', $row['department_names']);
                                        foreach ($department_names as $index => $dept_name):
                                            if ($index > 0) echo '<div class="border-t border-gray-100 mt-1 pt-1"></div>';
                                    ?>
                                        <div class="flex items-center gap-2">
                                            <i class="ph-buildings-light text-gray-400"></i>
                                            <span><?php echo htmlspecialchars($dept_name); ?></span>
                                        </div>
                                    <?php endforeach; 
                                    endif; ?>
                                </td>
                                <?php else: ?>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php if (!empty($row['is_anonymous']) && intval($row['is_anonymous']) == 1): ?>
                                        <span class="text-gray-500">Anónimo</span>
                                    <?php else: ?>
                                        <div class="max-w-[12rem]">
                                            <div class="text-gray-900 truncate whitespace-nowrap"><?php echo htmlspecialchars($row['user_name'] ?? ''); ?></div>
                                            <div class="text-gray-500 truncate whitespace-nowrap"><?php echo htmlspecialchars($row['user_email'] ?? ''); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'))): ?>
                                <td class="px-6 py-4">
                                    <?php
                                        $statusInfo = getStatusDisplayInfo($row['status']);
                                        $timeText = '';

                                        if ($row['status'] === 'unattended_ontime') {
                                            $businessDaysElapsed = calculateBusinessDays($createdDate, $now);
                                            $daysRemaining = max(0.0, 5.0 - $businessDaysElapsed);
                                            if ($daysRemaining > 0.0) {
                                                $timeText = formatBusinessDayDiffLabel($daysRemaining, 'día restante', 'días restantes', 1);
                                            }
                                        } elseif ($row['status'] === 'attended_ontime' || $row['status'] === 'attended_late') {
                                            if ($attendedDate) {
                                                $businessDaysTaken = calculateBusinessDays($createdDate, $attendedDate);
                                                if ($businessDaysTaken > 0.0) {
                                                    $timeText = formatBusinessDayDiffLabel($businessDaysTaken, 'día después', 'días después', 1);
                                                } else {
                                                    $timeText = 'mismo día';
                                                }
                                            }
                                        }
                                    ?>
                                    <div class="flex flex-col gap-1.5">
                                        <span class="px-2 inline-flex items-center gap-x-1.5 text-xs leading-5 font-semibold rounded-full <?php echo $statusInfo['class']; ?> ring-1 ring-inset w-fit whitespace-nowrap">
                                            <i class="<?php echo $statusInfo['icon']; ?> text-<?php echo $statusInfo['color']; ?>-600"></i>
                                            <?php echo $statusInfo['text']; ?>
                                        </span>
                                        <?php if ($timeText && (function_exists('isAdmin') && (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager')))): ?>
                                            <span class="text-xs text-gray-600"><?php echo $timeText; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $interval = $createdDate->diff($now);
                                        $daysAgo = $interval->days;

                                        if ($daysAgo === 0) {
                                            $timeText = 'Hace 0 días';
                                        } elseif ($daysAgo === 1) {
                                            $timeText = 'Hace 1 día';
                                        } else {
                                            $timeText = 'Hace ' . $daysAgo . ' días';
                                        }
                                    ?>
                                    <span class="px-2 inline-flex items-center gap-x-1.5 text-xs leading-5 font-medium rounded-full bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-600/20">
                                        <i class="ph-clock text-gray-600"></i>
                                        <?php echo $timeText; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="view_complaint.php?id=<?php echo $row['id']; ?>"
                                        class="inline-flex items-center text-primary hover:text-blue-800 font-semibold">
                                        Ver Detalles
                                        <i class="ph-arrow-right ml-1"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php
                // Build base query string preserving current filters except 'page'
                $params = $_GET;
                unset($params['page']);
                $baseQueryString = http_build_query($params);
                $baseUrl = 'dashboard.php' . ($baseQueryString ? ('?' . $baseQueryString . '&') : '?');

                // Determine page number window (Google-like: show a window around current)
                $window = 2;
                $startPage = max(1, $page - $window);
                $endPage = min($total_pages, $page + $window);
            ?>
            <?php if ($total_pages > 1): ?>
            <nav class="mt-6 flex items-center justify-center">
                <ul class="inline-flex -space-x-px">
                    <?php if ($page > 1): ?>
                        <li>
                            <a href="<?php echo $baseUrl . 'page=' . ($page - 1); ?>" 
                               class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">
                                « Anterior
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($startPage > 1): ?>
                        <li>
                            <a href="<?php echo $baseUrl . 'page=1'; ?>" 
                               class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">
                                1
                            </a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li>
                                <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-300">…</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <li>
                            <?php if ($p == $page): ?>
                                <span class="px-3 py-2 leading-tight text-white bg-blue-600 border border-blue-600"><?php echo $p; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $baseUrl . 'page=' . $p; ?>" 
                                   class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">
                                   <?php echo $p; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $total_pages): ?>
                        <?php if ($endPage < $total_pages - 1): ?>
                            <li>
                                <span class="px-3 py-2 leading-tight text-gray-400 bg-white border border-gray-300">…</span>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a href="<?php echo $baseUrl . 'page=' . $total_pages; ?>" 
                               class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li>
                            <a href="<?php echo $baseUrl . 'page=' . ($page + 1); ?>" 
                               class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">
                                Siguiente »
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'components/footer.php'; ?>

<script>
// Initialize Tom Select for multi-select filters
document.addEventListener('DOMContentLoaded', function() {
    // Multi-select
    document.querySelectorAll('.tom-select-multi').forEach(function(el) {
        new TomSelect(el, {
            plugins: ['remove_button'],
            maxItems: null,
            placeholder: el.getAttribute('placeholder') || 'Seleccionar...',
            render: {
                no_results: function(data, escape) {
                    return '<div class="no-results p-2 text-gray-500 text-sm">Sin resultados</div>';
                }
            }
        });
    });
    
    // Single-select (for date range)
    document.querySelectorAll('.tom-select-single').forEach(function(el) {
        new TomSelect(el, {
            maxItems: 1,
            allowEmptyOption: false,
            render: {
                no_results: function(data, escape) {
                    return '<div class="no-results p-2 text-gray-500 text-sm">Sin resultados</div>';
                }
            }
        });
    });
});
</script>

<style>
/* Tom Select custom styles */
.ts-wrapper {
    font-size: 0.875rem;
}
.ts-wrapper .ts-control {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem;
    min-height: 34px;
}
.ts-wrapper.focus .ts-control {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}
.ts-wrapper .ts-control .item {
    background: #3b82f6;
    color: white;
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    margin: 0.125rem;
    font-size: 0.75rem;
}
.ts-wrapper .ts-control .item .remove {
    color: white;
    border-left: 1px solid rgba(255,255,255,0.3);
    margin-left: 0.25rem;
    padding-left: 0.25rem;
}
.ts-dropdown {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.ts-dropdown .option {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}
.ts-dropdown .option.active {
    background: #3b82f6;
    color: white;
}
.ts-dropdown .option:hover {
    background: #eff6ff;
    color: #1e40af;
}
.ts-dropdown .option.active:hover {
    background: #2563eb;
    color: white;
}
</style>

<?php if (isAdmin()): ?>
<!-- Modal de Análisis General con Gemini -->
<div id="bulkAnalyzeModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity" onclick="closeBulkAnalyzeModal()"></div>

        <!-- Modal Panel -->
        <div class="relative bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-6xl max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/20 rounded-lg">
                        <i class="ph-sparkle text-2xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white" id="modal-title">Análisis General con Gemini</h3>
                        <p class="text-blue-100 text-sm">Reportes sin asignar del periodo seleccionado</p>
                    </div>
                </div>
                <button onclick="closeBulkAnalyzeModal()" class="text-white hover:text-blue-200 transition-colors">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6" id="bulkAnalyzeContent">
                <!-- Loading State -->
                <div id="bulkAnalyzeLoading" class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                        <i class="ph-spinner text-3xl text-blue-600 animate-spin"></i>
                    </div>
                    <p class="text-gray-600 font-medium">Analizando reportes con IA...</p>
                    <p class="text-gray-500 text-sm mt-1">Esto puede tardar unos segundos</p>
                </div>

                <!-- Results Container -->
                <div id="bulkAnalyzeResults" class="hidden">
                    <!-- Summary -->
                    <div id="bulkAnalyzeSummary" class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 mb-6 border border-blue-200">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div class="flex items-center gap-6">
                                <div class="text-center">
                                    <span class="block text-2xl font-bold text-blue-700" id="totalAnalyzed">0</span>
                                    <span class="text-xs text-blue-600">Analizados</span>
                                </div>
                                <div class="text-center">
                                    <span class="block text-2xl font-bold text-green-600" id="successAnalyzed">0</span>
                                    <span class="text-xs text-green-600">Exitosos</span>
                                </div>
                                <div class="text-center">
                                    <span class="block text-2xl font-bold text-red-600" id="errorAnalyzed">0</span>
                                    <span class="text-xs text-red-600">Errores</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="selectAllSuggestions()" class="px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-md transition-colors">
                                    <i class="ph-checks mr-1"></i> Seleccionar Todos
                                </button>
                                <button onclick="deselectAllSuggestions()" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
                                    <i class="ph-x mr-1"></i> Deseleccionar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Reports List -->
                    <div id="bulkAnalyzeReportsList" class="space-y-4">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>

                <!-- Error State -->
                <div id="bulkAnalyzeError" class="hidden text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <i class="ph-warning text-3xl text-red-600"></i>
                    </div>
                    <p class="text-red-600 font-medium" id="bulkAnalyzeErrorMessage">Error al analizar</p>
                </div>

                <!-- Empty State -->
                <div id="bulkAnalyzeEmpty" class="hidden text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <i class="ph-check-circle text-3xl text-green-600"></i>
                    </div>
                    <p class="text-green-600 font-medium">¡No hay reportes sin asignar!</p>
                    <p class="text-gray-500 text-sm mt-1">Todos los reportes ya tienen departamentos asignados.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t flex-shrink-0" id="bulkAnalyzeFooter">
                <button onclick="closeBulkAnalyzeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    Cerrar
                </button>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500" id="selectedCount">0 seleccionados</span>
                    <button onclick="applySelectedSuggestions()" id="applySelectedBtn" disabled class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ph-check mr-1"></i> Aplicar Seleccionados
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Bulk Analyze State
let bulkAnalyzeData = [];
let selectedReports = new Set();

function openBulkAnalyzeModal() {
    const modal = document.getElementById('bulkAnalyzeModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Reset states
    document.getElementById('bulkAnalyzeLoading').classList.remove('hidden');
    document.getElementById('bulkAnalyzeResults').classList.add('hidden');
    document.getElementById('bulkAnalyzeError').classList.add('hidden');
    document.getElementById('bulkAnalyzeEmpty').classList.add('hidden');
    
    // Get current date_range from URL or default
    const urlParams = new URLSearchParams(window.location.search);
    const dateRange = urlParams.get('date_range') || 'this_year';
    
    // Call API
    fetch('ajax_gemini_bulk_analyze.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date_range: dateRange })
    })
    .then(response => response.json())
    .then(result => {
        document.getElementById('bulkAnalyzeLoading').classList.add('hidden');
        
        if (!result.success) {
            document.getElementById('bulkAnalyzeError').classList.remove('hidden');
            document.getElementById('bulkAnalyzeErrorMessage').textContent = result.error || 'Error desconocido';
            return;
        }
        
        if (result.data.length === 0) {
            document.getElementById('bulkAnalyzeEmpty').classList.remove('hidden');
            return;
        }
        
        bulkAnalyzeData = result.data;
        selectedReports.clear();
        renderBulkAnalyzeResults();
    })
    .catch(error => {
        document.getElementById('bulkAnalyzeLoading').classList.add('hidden');
        document.getElementById('bulkAnalyzeError').classList.remove('hidden');
        document.getElementById('bulkAnalyzeErrorMessage').textContent = 'Error de conexión: ' + error.message;
    });
}

function closeBulkAnalyzeModal() {
    document.getElementById('bulkAnalyzeModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function renderBulkAnalyzeResults() {
    const container = document.getElementById('bulkAnalyzeReportsList');
    const successCount = bulkAnalyzeData.filter(r => r.success).length;
    const errorCount = bulkAnalyzeData.filter(r => !r.success).length;
    
    document.getElementById('totalAnalyzed').textContent = bulkAnalyzeData.length;
    document.getElementById('successAnalyzed').textContent = successCount;
    document.getElementById('errorAnalyzed').textContent = errorCount;
    
    container.innerHTML = bulkAnalyzeData.map((report, index) => {
        if (!report.success) {
            return `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <i class="ph-warning-circle text-red-500 text-xl"></i>
                        <div>
                            <span class="font-medium text-red-700">#${report.folio}</span>
                            <span class="text-red-600 text-sm ml-2">${report.error}</span>
                        </div>
                    </div>
                </div>
            `;
        }
        
        const data = report.data;
        const depts = data.lista_departamentos || [];
        const accion = data.accion || 'procesar';
        
        // Determinar colores y badges según acción
        let accionBadge = '';
        let accionClass = '';
        let btnText = '<i class="ph-check mr-1"></i> Aplicar';
        let btnClass = 'text-blue-600 bg-blue-100 hover:bg-blue-200';
        
        if (accion === 'invalido') {
            accionBadge = '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 ring-1 ring-gray-300"><i class="ph-prohibit mr-1"></i>Inválido</span>';
            accionClass = 'border-gray-300 bg-gray-50';
            btnText = '<i class="ph-prohibit mr-1"></i> Marcar Inválido';
            btnClass = 'text-gray-600 bg-gray-100 hover:bg-gray-200';
        } else if (accion === 'duplicado') {
            accionBadge = '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-700 ring-1 ring-orange-300"><i class="ph-copy mr-1"></i>Duplicado</span>';
            accionClass = 'border-orange-300 bg-orange-50';
            btnText = '<i class="ph-copy mr-1"></i> Marcar Duplicado';
            btnClass = 'text-orange-600 bg-orange-100 hover:bg-orange-200';
        }
        
        return `
            <div class="bg-white border rounded-lg shadow-sm overflow-hidden ${accionClass}" data-report-index="${index}">
                <div class="p-4">
                    <div class="flex items-start gap-4">
                        <!-- Checkbox -->
                        <div class="flex-shrink-0 pt-1">
                            <input type="checkbox" 
                                   id="report_${report.complaint_id}" 
                                   class="report-checkbox w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                                   onchange="toggleReportSelection(${index})"
                                   ${selectedReports.has(index) ? 'checked' : ''}>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center flex-wrap gap-2 mb-2">
                                <span class="font-bold text-gray-900">#${report.folio}</span>
                                ${accionBadge}
                                ${accion === 'procesar' ? `<span class="px-2 py-0.5 text-xs font-medium rounded-full ${
                                    data.tipo === 'queja' ? 'bg-red-100 text-red-700' :
                                    data.tipo === 'sugerencia' ? 'bg-blue-100 text-blue-700' :
                                    'bg-green-100 text-green-700'
                                }">${data.tipo}</span>` : ''}
                                <span class="text-sm text-gray-500">${report.antiguedad}</span>
                                <span class="text-sm text-gray-500">• ${report.user_name}</span>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3">${report.description_short}</p>
                            
                            <!-- AI Suggestions -->
                            ${accion === 'procesar' ? `
                            <div class="bg-blue-50 rounded-lg p-3 space-y-3">
                                <div class="flex items-center gap-2 text-blue-700 text-sm font-medium">
                                    <i class="ph-sparkle"></i>
                                    Sugerencias de IA
                                </div>
                                
                                <div class="grid md:grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-gray-500">Categoría sugerida:</span>
                                        <span class="ml-1 font-medium text-gray-900">${data.categoria_nombre || 'Sin categoría'}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Departamentos:</span>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            ${depts.map(d => `
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700" title="${d.motivo || ''}">
                                                    ${d.nombre}
                                                </span>
                                            `).join('')}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="pt-2 border-t border-blue-100">
                                    <span class="text-gray-500 text-sm">Resumen:</span>
                                    <p class="text-sm text-gray-700 mt-1">${data.resumen || 'Sin resumen'}</p>
                                </div>
                            </div>
                            ` : `
                            <div class="${accion === 'invalido' ? 'bg-gray-100' : 'bg-orange-50'} rounded-lg p-3">
                                <div class="flex items-center gap-2 ${accion === 'invalido' ? 'text-gray-700' : 'text-orange-700'} text-sm font-medium mb-2">
                                    <i class="ph-${accion === 'invalido' ? 'prohibit' : 'copy'}"></i>
                                    ${accion === 'invalido' ? 'Reporte detectado como inválido' : 'Reporte detectado como duplicado'}
                                </div>
                                <p class="text-sm ${accion === 'invalido' ? 'text-gray-600' : 'text-orange-600'}">
                                    ${data.motivo_cierre || (accion === 'invalido' ? 'El contenido no es una queja, sugerencia o felicitación válida.' : 'Este reporte parece ser duplicado de otro existente.')}
                                </p>
                                ${data.duplicado_de ? `<p class="text-xs text-orange-500 mt-1">Posible duplicado del reporte ID: ${data.duplicado_de}</p>` : ''}
                            </div>
                            `}
                        </div>
                        
                        <!-- Individual Apply Button -->
                        <div class="flex-shrink-0">
                            <button onclick="applySingleSuggestion(${index})" 
                                    class="px-3 py-1.5 text-xs font-medium ${btnClass} rounded-md transition-colors"
                                    id="applyBtn_${index}">
                                ${btnText}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    document.getElementById('bulkAnalyzeResults').classList.remove('hidden');
    updateSelectedCount();
}

function toggleReportSelection(index) {
    if (selectedReports.has(index)) {
        selectedReports.delete(index);
    } else {
        selectedReports.add(index);
    }
    updateSelectedCount();
}

function selectAllSuggestions() {
    bulkAnalyzeData.forEach((report, index) => {
        if (report.success) {
            selectedReports.add(index);
            const checkbox = document.getElementById(`report_${report.complaint_id}`);
            if (checkbox) checkbox.checked = true;
        }
    });
    updateSelectedCount();
}

function deselectAllSuggestions() {
    selectedReports.clear();
    document.querySelectorAll('.report-checkbox').forEach(cb => cb.checked = false);
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = selectedReports.size;
    document.getElementById('selectedCount').textContent = `${count} seleccionado${count !== 1 ? 's' : ''}`;
    document.getElementById('applySelectedBtn').disabled = count === 0;
}

async function applySingleSuggestion(index) {
    const report = bulkAnalyzeData[index];
    if (!report || !report.success) return;
    
    const btn = document.getElementById(`applyBtn_${index}`);
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ph-spinner animate-spin mr-1"></i> Aplicando...';
    btn.disabled = true;
    
    const accion = report.data.accion || 'procesar';
    
    try {
        const response = await fetch('ajax_apply_bulk_suggestions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                suggestions: [{
                    complaint_id: report.complaint_id,
                    accion: accion,
                    categoria_id: report.data.categoria_id,
                    departamentos: report.data.lista_departamentos,
                    motivo_cierre: report.data.motivo_cierre,
                    duplicado_de: report.data.duplicado_de
                }]
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.summary.success > 0) {
            btn.innerHTML = '<i class="ph-check-circle mr-1"></i> Aplicado';
            btn.classList.remove('text-blue-600', 'bg-blue-100', 'hover:bg-blue-200');
            btn.classList.add('text-green-600', 'bg-green-100');
            
            // Remove from selection
            selectedReports.delete(index);
            const checkbox = document.getElementById(`report_${report.complaint_id}`);
            if (checkbox) {
                checkbox.checked = false;
                checkbox.disabled = true;
            }
            updateSelectedCount();
        } else {
            btn.innerHTML = '<i class="ph-warning mr-1"></i> Error';
            btn.classList.remove('text-blue-600', 'bg-blue-100');
            btn.classList.add('text-red-600', 'bg-red-100');
        }
    } catch (error) {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error al aplicar sugerencia: ' + error.message);
    }
}

async function applySelectedSuggestions() {
    if (selectedReports.size === 0) return;
    
    const btn = document.getElementById('applySelectedBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ph-spinner animate-spin mr-1"></i> Aplicando...';
    btn.disabled = true;
    
    const suggestions = [];
    selectedReports.forEach(index => {
        const report = bulkAnalyzeData[index];
        if (report && report.success) {
            suggestions.push({
                complaint_id: report.complaint_id,
                accion: report.data.accion || 'procesar',
                categoria_id: report.data.categoria_id,
                departamentos: report.data.lista_departamentos,
                motivo_cierre: report.data.motivo_cierre,
                duplicado_de: report.data.duplicado_de
            });
        }
    });
    
    try {
        const response = await fetch('ajax_apply_bulk_suggestions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ suggestions })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update UI for each successful application
            result.results.forEach(res => {
                const index = bulkAnalyzeData.findIndex(r => r.complaint_id === res.complaint_id);
                if (index !== -1 && res.success) {
                    const applyBtn = document.getElementById(`applyBtn_${index}`);
                    if (applyBtn) {
                        applyBtn.innerHTML = '<i class="ph-check-circle mr-1"></i> Aplicado';
                        applyBtn.classList.remove('text-blue-600', 'bg-blue-100', 'hover:bg-blue-200');
                        applyBtn.classList.add('text-green-600', 'bg-green-100');
                        applyBtn.disabled = true;
                    }
                    
                    const checkbox = document.getElementById(`report_${bulkAnalyzeData[index].complaint_id}`);
                    if (checkbox) {
                        checkbox.checked = false;
                        checkbox.disabled = true;
                    }
                    
                    selectedReports.delete(index);
                }
            });
            
            updateSelectedCount();
            
            // Show summary
            alert(`✅ Aplicación completada\n\nExitosos: ${result.summary.success}\nErrores: ${result.summary.errors}`);
            
            if (result.summary.success > 0) {
                // Suggest page refresh
                if (confirm('¿Deseas recargar la página para ver los cambios?')) {
                    window.location.reload();
                }
            }
        }
    } catch (error) {
        alert('Error al aplicar sugerencias: ' + error.message);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = selectedReports.size === 0;
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBulkAnalyzeModal();
    }
});
</script>
<?php endif; ?>

</body>
</html>
