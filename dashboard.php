<?php 
// Load config first (before any output)
require_once 'config.php';

// Redirect if not logged in (must be before header.php sends any output)
if (!isLoggedIn()) {
    header('Location: login.php');
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
    
    // Get filter parameters
    $department = isset($_GET['department']) ? $_GET['department'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
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
    
    if (isset($_GET['category']) && $_GET['category'] !== '') {
        $query .= " AND c.category_id = " . intval($_GET['category']);
    }
    if ($department) {
        $query .= " AND EXISTS (
            SELECT 1 FROM complaint_departments cd2 
            WHERE cd2.complaint_id = c.id 
            AND cd2.department_id = " . intval($department) . ")";
    }
    if ($status) {
        $query .= " AND c.status = '" . $conn->real_escape_string($status) . "'";
    }
    if ($search !== '') {
        $escaped = $conn->real_escape_string($search);
        $like = "'%" . $escaped . "%'";
        $query .= " AND (c.folio LIKE $like OR c.description LIKE $like)";
    }
    if ($date_range) {
        if ($date_range === 'this_year') {
            $startOfYear = (new DateTime('first day of january ' . date('Y')))->format('Y-m-d 00:00:00');
            $query .= " AND c.created_at >= '" . $conn->real_escape_string($startOfYear) . "'";
        } else {
            $query .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL " . intval($date_range) . " DAY)";
        }
    }
    
    // Get quick statistics (Admins only)
    $stats = ['total' => 0, 'unattended' => 0, 'unassigned' => 0];
    if (function_exists('isAdmin') && isAdmin()) {
        $stats_query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('unattended_ontime', 'unattended_late') THEN 1 ELSE 0 END) as unattended,
            SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM complaint_departments cd WHERE cd.complaint_id = c.id) THEN 1 ELSE 0 END) as unassigned
        FROM complaints c";
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
    if (isset($_GET['category']) && $_GET['category'] !== '') {
        $countQuery .= " AND c.category_id = " . intval($_GET['category']);
    }
    if ($department) {
        $countQuery .= " AND EXISTS (
            SELECT 1 FROM complaint_departments cd2 
            WHERE cd2.complaint_id = c.id 
            AND cd2.department_id = " . intval($department) . ")";
    }
    if ($status) {
        $countQuery .= " AND c.status = '" . $conn->real_escape_string($status) . "'";
    }
    if ($search !== '') {
        $escaped = $conn->real_escape_string($search);
        $like = "'%" . $escaped . "%'";
        $countQuery .= " AND (c.folio LIKE $like OR c.description LIKE $like)";
    }
    if ($date_range) {
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
    ?>

    <main class="container mx-auto px-4 py-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
            
            <!-- Quick Stats (Admins only) -->
            <?php if (function_exists('isAdmin') && isAdmin()): ?>
            <div class="flex items-center gap-2 bg-white rounded-lg shadow-sm border border-gray-200 p-1.5">
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Total</span>
                        <span class="text-lg font-bold text-gray-900 leading-none"><?php echo $stats['total']; ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-orange-600 font-semibold">Sin Atender</span>
                        <span class="text-lg font-bold text-orange-600 leading-none"><?php echo $stats['unattended']; ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-3 border-r border-gray-200">
                    <div class="flex flex-col">
                        <span class="text-[10px] uppercase tracking-wider text-red-600 font-semibold">Sin Asignar</span>
                        <span class="text-lg font-bold text-red-600 leading-none"><?php echo $stats['unassigned']; ?></span>
                    </div>
                </div>
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
                    
                    <div class="w-full sm:w-40">
                        <label for="category" class="block text-xs font-medium text-gray-500 mb-1">Categoría</label>
                        <select id="category" name="category" class="block w-full py-1.5 text-sm rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <?php 
                            $cat_result->data_seek(0);
                            while ($cat = $cat_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <?php if (function_exists('isAdmin') ? isAdmin() : false): ?>
                    <div class="w-full sm:w-40">
                        <label for="department" class="block text-xs font-medium text-gray-500 mb-1">Departamento</label>
                        <select id="department" name="department" class="block w-full py-1.5 text-sm rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <?php 
                            $dept_result->data_seek(0);
                            while ($dept = $dept_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <div class="w-full sm:w-40">
                        <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                        <select id="status" name="status" class="block w-full py-1.5 text-sm rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="unattended_ontime" <?php echo $status == 'unattended_ontime' ? 'selected' : ''; ?>>Sin atender (a tiempo)</option>
                            <option value="unattended_late" <?php echo $status == 'unattended_late' ? 'selected' : ''; ?>>Sin atender (tarde)</option>
                            <option value="attended_ontime" <?php echo $status == 'attended_ontime' ? 'selected' : ''; ?>>Atendido</option>
                            <option value="attended_late" <?php echo $status == 'attended_late' ? 'selected' : ''; ?>>Atendido a destiempo</option>
                            <option value="invalid" <?php echo $status == 'invalid' ? 'selected' : ''; ?>>Inválido</option>
                            <option value="duplicate" <?php echo $status == 'duplicate' ? 'selected' : ''; ?>>Duplicado</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="w-full sm:w-36">
                        <label for="date_range" class="block text-xs font-medium text-gray-500 mb-1">Fecha</label>
                        <select id="date_range" name="date_range" class="block w-full py-1.5 text-sm rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="this_year" <?php echo $date_range == 'this_year' ? 'selected' : ''; ?>>Este año</option>
                            <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Últimos 7 días</option>
                            <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Últimos 30 días</option>
                            <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Últimos 90 días</option>
                            <option value="" <?php echo $date_range == '' ? 'selected' : ''; ?>>Todo</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <?php 
                        $hasActiveFilters = !empty($_GET['department']) || !empty($_GET['category']) || 
                                          !empty($_GET['status']) || !empty($_GET['q']) ||
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
                                <?php if (function_exists('isAdmin') && isAdmin()): ?>
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
                            <?php if (function_exists('isAdmin') && isAdmin()): ?>
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
                            <?php if (function_exists('isAdmin') && isAdmin()): ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                                Departamentos
                            </th>
                            <?php else: ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                                Usuario
                            </th>
                            <?php endif; ?>
                            <?php if (function_exists('isAdmin') && isAdmin()): ?>
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
                                <?php if (function_exists('isAdmin') && isAdmin()): ?>
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
                                <?php if (function_exists('isAdmin') && isAdmin()): ?>
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
                                        <?php if ($timeText && (function_exists('isAdmin') && isAdmin())): ?>
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
</body>
</html>
