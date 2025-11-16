<?php $page_title = 'Reportes - ITSCC Buzón'; include 'components/header.php'; require_once 'status_helper.php'; ?>
    <?php 
    
    // Allow all users to view the reports
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

    <main class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-primary mb-6">Reportes</h1>
            
            <!-- Filters -->
            <form method="GET" class="bg-white rounded-lg shadow p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label for="q" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="ph-magnifying-glass text-gray-500 mr-1"></i>
                            Búsqueda (folio o descripción)
                        </label>
                        <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Ej. 1-2025 o palabras de la descripción"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" />
                    </div>
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="ph-tag text-gray-500 mr-1"></i>
                            Categoría
                        </label>
                        <select id="category" name="category"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Todas las categorías</option>
                            <?php while ($cat = $cat_result->fetch_assoc()): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if (function_exists('isAdmin') ? isAdmin() : false): ?>
                        <div>
                            <label for="department" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="ph-buildings text-gray-500 mr-1"></i>
                                Departamento
                            </label>
                            <select id="department" name="department"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                <option value="">Todos los departamentos</option>
                                <?php while ($dept = $dept_result->fetch_assoc()): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $department == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="ph-status text-gray-500 mr-1"></i>
                            Estado
                        </label>
                        <select id="status" name="status"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="">Todos los estados</option>
                            <option value="unattended_ontime" <?php echo $status == 'unattended_ontime' ? 'selected' : ''; ?>>Sin atender (a tiempo)</option>
                            <option value="unattended_late" <?php echo $status == 'unattended_late' ? 'selected' : ''; ?>>Sin atender (tarde)</option>
                            <option value="attended_ontime" <?php echo $status == 'attended_ontime' ? 'selected' : ''; ?>>Atendido</option>
                            <option value="attended_late" <?php echo $status == 'attended_late' ? 'selected' : ''; ?>>Atendido a destiempo</option>
                            <option value="invalid" <?php echo $status == 'invalid' ? 'selected' : ''; ?>>Inválido</option>
                            <option value="duplicate" <?php echo $status == 'duplicate' ? 'selected' : ''; ?>>Duplicado</option>
                        </select>
                    </div>

                    <div>
                        <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="ph-calendar text-gray-500 mr-1"></i>
                            Rango de Fechas
                        </label>
                        <select id="date_range" name="date_range"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                            <option value="this_year" <?php echo $date_range == 'this_year' ? 'selected' : ''; ?>>Este año</option>
                            <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Últimos 7 días</option>
                            <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Últimos 30 días</option>
                            <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Últimos 90 días</option>
                            <option value="" <?php echo $date_range == '' ? 'selected' : ''; ?>>Todo el tiempo</option>
                        </select>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-end space-x-2">
                    <?php if (function_exists('isAdmin') ? isAdmin() : false): ?>
                        <a href="statistics.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm">
                            <i class="ph-chart-line text-lg mr-2"></i>
                            Ver Estadísticas
                        </a>
                    <?php endif; ?>

                    <?php 
                    // Verificar si hay filtros activos
                    $hasActiveFilters = !empty($_GET['department']) || !empty($_GET['category']) || 
                                      !empty($_GET['status']) || 
                                      (isset($_GET['date_range']) && $_GET['date_range'] !== 'this_year' && $_GET['date_range'] !== '');
                    if ($hasActiveFilters):
                    ?>
                        <a href="dashboard.php" 
                           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="ph-x-circle text-lg mr-2"></i>
                            Eliminar Filtros
                        </a>
                    <?php endif; ?>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="ph-magnifying-glass text-lg mr-2"></i>
                        Buscar
                    </button>
                </div>
            </form>

            <!-- Complaints List -->
            <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                                <?php echo (function_exists('isAdmin') && isAdmin()) ? 'Departamentos' : 'Usuario'; ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-44">
                                Estado
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fecha
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <?php
                                    $createdDate = new DateTime($row['created_at']);
                                    $now = new DateTime();
                                    $attendedDate = !empty($row['attended_at']) ? new DateTime($row['attended_at']) : null;
                                ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    #<?php echo $row['folio'] ?? str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>
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
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
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
                                    <?php else: ?>
                                        <?php if (!empty($row['is_anonymous']) && intval($row['is_anonymous']) == 1): ?>
                                            <span class="text-gray-500">Anónimo</span>
                                        <?php else: ?>
                                            <div class="max-w-[12rem]">
                                                <div class="text-gray-900 truncate whitespace-nowrap"><?php echo htmlspecialchars($row['user_name'] ?? ''); ?></div>
                                                <div class="text-gray-500 truncate whitespace-nowrap"><?php echo htmlspecialchars($row['user_email'] ?? ''); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
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
                        <?php endwhile; ?>
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
