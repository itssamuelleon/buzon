<?php 
$page_title = 'Estadísticas - Buzón de Quejas'; 
require_once 'config.php';
require_once 'status_helper.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$show_global_blobs = false; // Disable global blobs for Liquid Glass design
include 'components/header.php';
?>

<!-- Liquid Glass Pattern Implementation -->
<div class="fixed inset-0 overflow-hidden pointer-events-none -z-50">
    <div class="absolute inset-0 bg-institutional">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-50/40 via-transparent to-slate-50/40 dark:from-slate-900/60 dark:via-transparent dark:to-slate-900/60"></div>
    </div>
</div>

</style>

<?php

// Incluir Chart.js y sus plugins (después del header)
echo '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@1.2.0/dist/chartjs-chart-matrix.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<!-- Export libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
';

// Obtener años disponibles
$years_query = "SELECT DISTINCT YEAR(created_at) as year FROM complaints ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = [];
while ($year = $years_result->fetch_assoc()) {
    $available_years[] = $year['year'];
}

// Obtener año seleccionado (0 para todos los años)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : 0;

// Construir la condición WHERE para el año seleccionado
$year_condition = $selected_year > 0 ? "WHERE YEAR(created_at) = $selected_year" : "";

// Estadísticas generales
$stats_query = "
    SELECT 
        COUNT(*) as total_complaints,
        SUM(CASE 
            WHEN status = 'attended_ontime' THEN 1 
            ELSE 0 
        END) as attended_ontime,
        SUM(CASE 
            WHEN status = 'attended_late' THEN 1 
            ELSE 0 
        END) as attended_late,
        SUM(CASE 
            WHEN status = 'unattended_ontime' THEN 1 
            ELSE 0 
        END) as unattended_ontime,
        SUM(CASE 
            WHEN status = 'unattended_late' THEN 1 
            ELSE 0 
        END) as unattended_late,
        AVG(CASE 
            WHEN status IN ('attended_ontime', 'attended_late') 
            THEN TIMESTAMPDIFF(DAY, created_at, attended_at)
            ELSE NULL 
        END) as avg_response_time
    FROM complaints
    $year_condition";

$stats_result = $conn->query($stats_query)->fetch_assoc();

// Obtener también los reportes sin departamento asignado para la métrica individual
$u_cond = $selected_year > 0 ? "AND YEAR(created_at) = $selected_year" : "";
$unassigned_query = "SELECT COUNT(*) as count FROM complaints WHERE id NOT IN (SELECT complaint_id FROM complaint_departments) $u_cond";
$stats_result['unassigned'] = $conn->query($unassigned_query)->fetch_assoc()['count'];

// Estadísticas por categoría
$categories_query = "
    SELECT 
        c.category_id,
        cat.name as category_name,
        COUNT(*) as total,
        SUM(CASE WHEN c.status = 'attended_ontime' THEN 1 ELSE 0 END) as attended_ontime,
        SUM(CASE WHEN c.status = 'attended_late' THEN 1 ELSE 0 END) as attended_late,
        SUM(CASE WHEN c.status = 'unattended_ontime' THEN 1 ELSE 0 END) as unattended_ontime,
        SUM(CASE WHEN c.status = 'unattended_late' THEN 1 ELSE 0 END) as unattended_late,
        AVG(CASE 
            WHEN c.status IN ('attended_ontime', 'attended_late') 
            THEN TIMESTAMPDIFF(DAY, c.created_at, c.attended_at)
            ELSE NULL 
        END) as avg_response_time
    FROM complaints c
    LEFT JOIN categories cat ON c.category_id = cat.id
    $year_condition
    GROUP BY c.category_id, cat.name
    ORDER BY total DESC";

$categories_result = $conn->query($categories_query);

// Estadísticas por departamento
$departments_query = "
    SELECT 
        d.id,
        d.name as department_name,
        COUNT(DISTINCT c.id) as total_assigned,
        SUM(CASE WHEN c.status = 'attended_ontime' THEN 1 ELSE 0 END) as attended_ontime,
        SUM(CASE WHEN c.status = 'attended_late' THEN 1 ELSE 0 END) as attended_late,
        AVG(CASE 
            WHEN c.status IN ('attended_ontime', 'attended_late') 
            THEN TIMESTAMPDIFF(DAY, c.created_at, c.attended_at)
            ELSE NULL 
        END) as avg_response_time
    FROM departments d
    LEFT JOIN complaint_departments cd ON d.id = cd.department_id
    LEFT JOIN complaints c ON cd.complaint_id = c.id
    " . ($year_condition ? str_replace("WHERE", "WHERE c.id IS NOT NULL AND", $year_condition) : "WHERE c.id IS NOT NULL") . "
    GROUP BY d.id, d.name
    ORDER BY total_assigned DESC";

$departments_result = $conn->query($departments_query);

// Tendencia mensual
$monthly_trend_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('attended_ontime', 'attended_late') THEN 1 ELSE 0 END) as attended,
        AVG(CASE 
            WHEN status IN ('attended_ontime', 'attended_late') 
            THEN TIMESTAMPDIFF(DAY, created_at, attended_at)
            ELSE NULL 
        END) as avg_response_time
    FROM complaints
    $year_condition
    GROUP BY month
    ORDER BY month ASC";

$monthly_trend_result = $conn->query($monthly_trend_query);

// Heatmap de actividad por hora y día
$heatmap_query = "
    SELECT 
        DAYOFWEEK(created_at) as day_of_week,
        HOUR(created_at) as hour_of_day,
        COUNT(*) as count
    FROM complaints
    $year_condition
    GROUP BY day_of_week, hour_of_day
    ORDER BY day_of_week, hour_of_day";

$heatmap_result = $conn->query($heatmap_query);

// Tiempo promedio de respuesta por mes
$response_time_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        AVG(CASE 
            WHEN status IN ('attended_ontime', 'attended_late') 
            THEN TIMESTAMPDIFF(HOUR, created_at, attended_at)
            ELSE NULL 
        END) as avg_response_hours
    FROM complaints
    $year_condition
    GROUP BY month
    ORDER BY month ASC";

$response_time_result = $conn->query($response_time_query);

// Eficiencia por departamento
$efficiency_query = "
    SELECT 
        d.name as department_name,
        COUNT(*) as total_complaints,
        SUM(CASE WHEN c.status = 'attended_ontime' THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN c.status = 'attended_late' THEN 1 ELSE 0 END) as late,
        AVG(CASE 
            WHEN c.status IN ('attended_ontime', 'attended_late') 
            THEN TIMESTAMPDIFF(HOUR, c.created_at, c.attended_at)
            ELSE NULL 
        END) as avg_response_hours
    FROM departments d
    LEFT JOIN complaint_departments cd ON d.id = cd.department_id
    LEFT JOIN complaints c ON cd.complaint_id = c.id
    " . ($year_condition ? str_replace("WHERE", "WHERE c.id IS NOT NULL AND", $year_condition) : "WHERE c.id IS NOT NULL") . "
    GROUP BY d.id, d.name
    HAVING total_complaints > 0
    ORDER BY (SUM(CASE WHEN c.status = 'attended_ontime' THEN 1 ELSE 0 END) / COUNT(*)) DESC";

$efficiency_result = $conn->query($efficiency_query);

// Preparar datos para gráficas
$monthly_labels = [];
$monthly_totals = [];
$monthly_attended = [];
$monthly_response_times = [];

while ($row = $monthly_trend_result->fetch_assoc()) {
    $date = new DateTime($row['month'] . '-01');
    $monthly_labels[] = $date->format('M Y');
    $monthly_totals[] = intval($row['total']);
    $monthly_attended[] = intval($row['attended']);
    $monthly_response_times[] = $row['avg_response_time'] ? round($row['avg_response_time'], 1) : 0;
}

// Preparar datos para exportación - Categorías
$categories_data = [];
$categories_result_clone = $conn->query($categories_query);
while ($category = $categories_result_clone->fetch_assoc()) {
    $unattended = $category['unattended_ontime'] + $category['unattended_late'];
    $categories_data[] = [
        'name' => $category['category_name'] ?? 'Sin categoría',
        'total' => intval($category['total']),
        'attended_ontime' => intval($category['attended_ontime']),
        'attended_late' => intval($category['attended_late']),
        'unattended' => $unattended,
        'avg_response_time' => $category['avg_response_time'] ? round($category['avg_response_time'], 1) : null
    ];
}

// Preparar datos para exportación - Departamentos
$departments_data = [];
$departments_result_clone = $conn->query($departments_query);
while ($department = $departments_result_clone->fetch_assoc()) {
    $departments_data[] = [
        'name' => $department['department_name'],
        'total_assigned' => intval($department['total_assigned']),
        'attended_ontime' => intval($department['attended_ontime']),
        'attended_late' => intval($department['attended_late']),
        'avg_response_time' => $department['avg_response_time'] ? round($department['avg_response_time'], 1) : null
    ];
}

// Datos generales para exportación
$export_data = [
    'period' => $selected_year > 0 ? "Año $selected_year" : "Todos los años",
    'generated_at' => date('d/m/Y H:i'),
    'summary' => [
        'total' => intval($stats_result['total_complaints']),
        'attended_ontime' => intval($stats_result['attended_ontime']),
        'attended_late' => intval($stats_result['attended_late']),
        'unattended_ontime' => intval($stats_result['unattended_ontime']),
        'unattended_late' => intval($stats_result['unattended_late']),
        'avg_response_time' => $stats_result['avg_response_time'] ? round($stats_result['avg_response_time'], 1) : null
    ],
    'categories' => $categories_data,
    'departments' => $departments_data,
    'monthly' => [
        'labels' => $monthly_labels,
        'totals' => $monthly_totals,
        'attended' => $monthly_attended,
        'response_times' => $monthly_response_times
    ]
];

?>

<style>
    /* Force high contrast text on gray text elements for better readability */
    .text-gray-400,
    .text-gray-500,
    .text-gray-600,
    .text-gray-700 {
        color: #111827 !important; /* black */
    }
    html.dark .text-gray-400,
    html.dark .text-gray-500,
    html.dark .text-gray-600,
    html.dark .text-gray-700 {
        color: #f8fafc !important; /* white */
    }
    
    /* Ensure icons don't randomly turn pitch black unless desired, but usually they inherit.
       We specifically target textual elements by only overriding text classes */
</style>

<div class="flex-grow bg-transparent">
    <main class="container mx-auto px-4 py-8">
        <!-- Encabezado -->
        <div class="mb-6 liquid-glass p-5 rounded-2xl shadow-sm border border-gray-200/50 dark:border-white/5">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 dark:bg-blue-500/20 rounded-xl flex-shrink-0 shadow-sm dark:shadow-none border border-transparent dark:border-blue-500/30 hidden sm:flex">
                        <i class="ph-chart-pie-slice text-white dark:text-blue-400 text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Estadísticas y Análisis</h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Análisis detallado de reportes y métricas de atención
                        </p>
                    </div>
                </div>
                <a href="dashboard.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white/50 dark:bg-black/20 dark:text-gray-300 border border-gray-300/30 dark:border-white/10 rounded-xl hover:bg-white transition-all shadow-sm">
                    <i class="ph-arrow-left text-lg mr-2"></i>
                    Volver al Dashboard
                </a>
            </div>
        </div>

        <!-- Filtro de año y botones de exportación -->
        <div class="liquid-glass rounded-2xl shadow-sm p-4 border border-gray-200/50 dark:border-white/5 mb-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <form method="GET" class="flex items-center gap-4">
                    <label for="year" class="font-medium text-gray-700 dark:text-gray-200">Periodo:</label>
                    <select id="year" name="year" 
                            class="rounded-xl border-gray-300/30 bg-white/50 dark:bg-black/20 dark:border-white/10 dark:text-white shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                            onchange="this.form.submit()">
                        <option value="0" <?php echo $selected_year == 0 ? 'selected' : ''; ?>>Todos los años</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <!-- Botones de exportación -->
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400 hidden sm:inline">Exportar:</span>
                    <button onclick="exportToPDF()" 
                            class="group inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-md hover:shadow-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105">
                        <i class="ph-file-pdf text-lg mr-2 group-hover:animate-bounce"></i>
                        PDF
                    </button>
                    <button onclick="exportToExcel()" 
                            class="group inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-md hover:shadow-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105">
                        <i class="ph-file-xls text-lg mr-2 group-hover:animate-bounce"></i>
                        Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Resumen General -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:flex lg:flex-row gap-3 w-full mb-6">
            <?php
            $total = $stats_result['total_complaints'];
            $metrics = [
                [
                    'title' => 'Total',
                    'subtitle' => '',
                    'value' => $total,
                    'icon' => 'ph-list-checks',
                    'color' => 'gray'
                ],
                [
                    'title' => 'Atendido',
                    'subtitle' => 'a tiempo',
                    'value' => $stats_result['attended_ontime'],
                    'icon' => 'ph-check-circle',
                    'color' => 'green'
                ],
                [
                    'title' => 'Atendido',
                    'subtitle' => 'tarde',
                    'value' => $stats_result['attended_late'],
                    'icon' => 'ph-clock-afternoon',
                    'color' => 'orange'
                ],
                [
                    'title' => 'Sin atender',
                    'subtitle' => 'a tiempo',
                    'value' => $stats_result['unattended_ontime'],
                    'icon' => 'ph-hourglass-medium',
                    'color' => 'blue'
                ],
                [
                    'title' => 'Sin atender',
                    'subtitle' => 'retrasado',
                    'value' => $stats_result['unattended_late'],
                    'icon' => 'ph-warning',
                    'color' => 'red'
                ],
                [
                    'title' => 'Sin',
                    'subtitle' => 'Asignar',
                    'value' => $stats_result['unassigned'],
                    'icon' => 'ph-folder',
                    'color' => 'yellow'
                ]
            ];
            ?>
            <?php foreach ($metrics as $metric): ?>
            <div class="liquid-glass rounded-xl p-3 flex items-center gap-3 flex-1 min-w-[120px] border-l-4 border-l-<?php echo $metric['color']; ?>-<?php echo $metric['color'] === 'gray' ? '400' : '500'; ?> transition-all">
                <div class="w-12 h-12 rounded-xl bg-<?php echo $metric['color']; ?>-500/10 dark:bg-<?php echo $metric['color']; ?>-400/10 flex items-center justify-center flex-shrink-0">
                    <i class="ph-fill <?php echo $metric['icon']; ?> text-<?php echo $metric['color']; ?>-600 dark:text-<?php echo $metric['color']; ?>-400 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xl font-bold text-<?php echo $metric['color'] === 'gray' ? 'gray-900' : $metric['color'] . '-600'; ?> dark:text-<?php echo $metric['color'] === 'gray' ? 'white' : $metric['color'] . '-400'; ?> leading-none"><?php echo number_format($metric['value']); ?></div>
                    <div class="mt-1.5" title="<?php echo trim($metric['title'] . ' ' . $metric['subtitle']); ?>">
                        <div class="text-[11px] uppercase tracking-widest text-<?php echo $metric['color'] === 'gray' ? 'gray-500' : $metric['color'] . '-600'; ?> dark:text-<?php echo $metric['color'] === 'gray' ? 'gray-400' : $metric['color'] . '-400/80'; ?> font-bold whitespace-nowrap leading-tight truncate"><?php echo $metric['title']; ?></div>
                        <?php if (!empty($metric['subtitle'])): ?>
                        <div class="text-[9px] uppercase tracking-widest text-<?php echo $metric['color'] === 'gray' ? 'gray-400' : $metric['color'] . '-500/70'; ?> font-semibold whitespace-nowrap leading-tight mt-0.5 truncate"><?php echo $metric['subtitle']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Gráficas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
            <!-- Estado de Reportes -->
            <div class="liquid-glass rounded-2xl shadow-sm p-5 border border-gray-200/50 dark:border-white/5">
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Estado de Reportes</h3>
                <div class="aspect-[4/3]">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Tendencia Mensual -->
            <div class="liquid-glass rounded-2xl shadow-sm p-5 border border-gray-200/50 dark:border-white/5">
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Tendencia Mensual</h3>
                <div class="aspect-[4/3]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Segunda fila de gráficas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
            <!-- Mapa de Calor de Actividad -->
            <div class="liquid-glass rounded-2xl shadow-sm p-5 border border-gray-200/50 dark:border-white/5">
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Actividad por Hora y Día</h3>
                <div class="aspect-[4/3]">
                    <canvas id="heatmapChart"></canvas>
                </div>
            </div>

            <!-- Tiempo Promedio de Respuesta -->
            <div class="liquid-glass rounded-2xl shadow-sm p-5 border border-gray-200/50 dark:border-white/5">
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Tiempo Promedio de Respuesta</h3>
                <div class="aspect-[4/3]">
                    <canvas id="responseTimeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tercera fila de gráficas -->
        <div class="grid grid-cols-1 gap-5 mb-6">
            <!-- Eficiencia por Departamento -->
            <div class="liquid-glass rounded-2xl shadow-sm p-5 border border-gray-200/50 dark:border-white/5">
                <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Eficiencia por Departamento</h3>
                <div style="height: 350px;">
                    <canvas id="efficiencyChart"></canvas>
                </div>
            </div>
        </div>
        </div>

        <!-- Estadísticas por Categoría -->
        <div class="liquid-glass rounded-2xl shadow-sm p-5 border border-gray-200/50 dark:border-white/5 mb-6">
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Estadísticas por Categoría</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Categoría
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Atendidos a Tiempo
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Atendidos Tarde
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Sin Atender
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Tiempo Promedio
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                        <?php echo htmlspecialchars($category['category_name'] ?? 'Sin categoría'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-300"><?php echo $category['total']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-green-600 dark:text-green-400">
                                        <?php 
                                        $percent = $category['total'] > 0 ? round(($category['attended_ontime'] / $category['total']) * 100) : 0;
                                        echo $category['attended_ontime'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-yellow-600 dark:text-yellow-400">
                                        <?php 
                                        $percent = $category['total'] > 0 ? round(($category['attended_late'] / $category['total']) * 100) : 0;
                                        echo $category['attended_late'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-red-600 dark:text-red-400">
                                        <?php 
                                        $unattended = $category['unattended_ontime'] + $category['unattended_late'];
                                        $percent = $category['total'] > 0 ? round(($unattended / $category['total']) * 100) : 0;
                                        echo $unattended . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-300">
                                        <?php 
                                        echo $category['avg_response_time'] ? 
                                            round($category['avg_response_time'], 1) . ' días' : 
                                            'N/A';
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Estadísticas por Departamento -->
        <div class="liquid-glass rounded-2xl shadow-sm p-5 border border-gray-200/50 dark:border-white/5 mb-6">
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 mb-3">Estadísticas por Departamento</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-slate-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Departamento
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Reportes Asignados
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Atendidos a Tiempo
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Atendidos Tarde
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                Tiempo Promedio
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        <?php while ($department = $departments_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-300"><?php echo $department['total_assigned']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-green-600 dark:text-green-400">
                                        <?php 
                                        $percent = $department['total_assigned'] > 0 ? 
                                            round(($department['attended_ontime'] / $department['total_assigned']) * 100) : 0;
                                        echo $department['attended_ontime'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-yellow-600 dark:text-yellow-400">
                                        <?php 
                                        $percent = $department['total_assigned'] > 0 ? 
                                            round(($department['attended_late'] / $department['total_assigned']) * 100) : 0;
                                        echo $department['attended_late'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-300">
                                        <?php 
                                        echo $department['avg_response_time'] ? 
                                            round($department['avg_response_time'], 1) . ' días' : 
                                            'N/A';
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Scripts para gráficas -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Función para actualizar colores de Chart.js según el tema
    const updateChartColors = () => {
        const isDark = document.documentElement.classList.contains('dark');
        Chart.defaults.color = isDark ? '#f8fafc' : '#111827';
        Chart.defaults.borderColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        
        // Actualizar instancias existentes
        for (let id in Chart.instances) {
            const chart = Chart.instances[id];
            // Texto general y subtítulos
            chart.options.color = isDark ? '#f8fafc' : '#111827';
            
            // Actualizar color de las escalas (X, Y)
            if (chart.options.scales) {
                Object.keys(chart.options.scales).forEach(axis => {
                    if (chart.options.scales[axis]) {
                        if (chart.options.scales[axis].ticks) {
                            chart.options.scales[axis].ticks.color = isDark ? '#f8fafc' : '#111827';
                        }
                        if (chart.options.scales[axis].grid) {
                            chart.options.scales[axis].grid.color = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                        }
                    }
                });
            }
            
            // Actualizar color de las leyendas (Legend plugin)
            if (chart.options.plugins && chart.options.plugins.legend) {
                if (!chart.options.plugins.legend.labels) chart.options.plugins.legend.labels = {};
                chart.options.plugins.legend.labels.color = isDark ? '#f8fafc' : '#111827';
            }
            
            chart.update();
        }
    };
    
    // Configurar color inicial y observar cambios futuros en el body (modo oscuro/claro)
    updateChartColors();
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                updateChartColors();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    // Gráfica de Estado de Reportes
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Atendidos a Tiempo', 'Atendidos Tarde', 'Sin Atender (A Tiempo)', 'Sin Atender (Tarde)'],
            datasets: [{
                data: [
                    <?php echo $stats_result['attended_ontime']; ?>,
                    <?php echo $stats_result['attended_late']; ?>,
                    <?php echo $stats_result['unattended_ontime']; ?>,
                    <?php echo $stats_result['unattended_late']; ?>
                ],
                backgroundColor: [
                    '#10B981', // Verde para atendidos a tiempo
                    '#FBBF24', // Amarillo para atendidos tarde
                    '#60A5FA', // Azul para sin atender a tiempo
                    '#EF4444'  // Rojo para sin atender tarde
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Gráfica de Tendencia Mensual
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [{
                label: 'Total de Reportes',
                data: <?php echo json_encode($monthly_totals); ?>,
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true
            }, {
                label: 'Reportes Atendidos',
                data: <?php echo json_encode($monthly_attended); ?>,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Mapa de Calor de Actividad
    const heatmapCtx = document.getElementById('heatmapChart').getContext('2d');
    const heatmapData = [];
    <?php 
    while ($row = $heatmap_result->fetch_assoc()) {
        echo "heatmapData.push({
            x: " . $row['hour_of_day'] . ",
            y: " . ($row['day_of_week']-1) . ",
            v: " . $row['count'] . "
        });\n";
    }
    ?>
    new Chart(heatmapCtx, {
        type: 'matrix',
        data: {
            datasets: [{
                data: heatmapData,
                backgroundColor(context) {
                    const value = context.dataset.data[context.dataIndex].v;
                    const alpha = value ? Math.min(0.2 + (value / 10), 1) : 0;
                    return `rgba(37, 99, 235, ${alpha})`;
                },
                borderColor: '#fff',
                borderWidth: 1,
                width: ({ chart }) => (chart.chartArea || {}).width / 24 - 1,
                height: ({ chart }) => (chart.chartArea || {}).height / 7 - 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    type: 'linear',
                    offset: true,
                    min: -0.5,
                    max: 23.5,
                    ticks: {
                        stepSize: 1,
                        callback: v => v < 10 ? `0${v}:00` : `${v}:00`
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    type: 'linear',
                    offset: true,
                    min: -0.5,
                    max: 6.5,
                    ticks: {
                        stepSize: 1,
                        callback: v => ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'][v]
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        title() {
                            return '';
                        },
                        label(context) {
                            const v = context.dataset.data[context.dataIndex];
                            return [
                                ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'][v.y],
                                `${v.x < 10 ? '0' + v.x : v.x}:00`,
                                `${v.v} reporte${v.v === 1 ? '' : 's'}`
                            ];
                        }
                    }
                }
            }
        }
    });

    // Gráfica de Tiempo de Respuesta
    const responseTimeCtx = document.getElementById('responseTimeChart').getContext('2d');
    const responseTimeData = {
        labels: [],
        datasets: [{
            label: 'Tiempo Promedio (Horas)',
            data: [],
            borderColor: '#2563EB',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            fill: true
        }]
    };
    <?php 
    while ($row = $response_time_result->fetch_assoc()) {
        $date = new DateTime($row['month'] . '-01');
        echo "responseTimeData.labels.push('" . $date->format('M Y') . "');\n";
        echo "responseTimeData.datasets[0].data.push(" . ($row['avg_response_hours'] ? round($row['avg_response_hours'], 1) : 0) . ");\n";
    }
    ?>
    new Chart(responseTimeCtx, {
        type: 'line',
        data: responseTimeData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Horas'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Gráfica de Eficiencia por Departamento
    const efficiencyCtx = document.getElementById('efficiencyChart').getContext('2d');
    const efficiencyData = {
        labels: [],
        datasets: [{
            label: 'Atendidos a Tiempo',
            data: [],
            backgroundColor: '#10B981',
        }, {
            label: 'Atendidos Tarde',
            data: [],
            backgroundColor: '#FBBF24',
        }]
    };
    <?php 
    while ($row = $efficiency_result->fetch_assoc()) {
        echo "efficiencyData.labels.push('" . addslashes($row['department_name']) . "');\n";
        echo "efficiencyData.datasets[0].data.push(" . $row['on_time'] . ");\n";
        echo "efficiencyData.datasets[1].data.push(" . $row['late'] . ");\n";
    }
    ?>
    new Chart(efficiencyCtx, {
        type: 'bar',
        data: efficiencyData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});

// Export data from PHP to JavaScript
const exportData = <?php echo json_encode($export_data, JSON_UNESCAPED_UNICODE); ?>;

// Export to PDF function
function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 14;
    let yPos = 20;
    
    // Title
    doc.setFontSize(20);
    doc.setTextColor(30, 58, 138); // Blue
    doc.text('Estadísticas de Reportes - ITSCC Buzón', margin, yPos);
    yPos += 10;
    
    // Period and date
    doc.setFontSize(11);
    doc.setTextColor(100);
    doc.text(`Periodo: ${exportData.period}`, margin, yPos);
    yPos += 6;
    doc.text(`Generado: ${exportData.generated_at}`, margin, yPos);
    yPos += 15;
    
    // Summary section
    doc.setFontSize(14);
    doc.setTextColor(30, 58, 138);
    doc.text('Resumen General', margin, yPos);
    yPos += 8;
    
    const summaryData = [
        ['Total de Reportes', exportData.summary.total.toString()],
        ['Atendidos a Tiempo', `${exportData.summary.attended_ontime} (${exportData.summary.total > 0 ? Math.round((exportData.summary.attended_ontime / exportData.summary.total) * 100) : 0}%)`],
        ['Atendidos Tarde', `${exportData.summary.attended_late} (${exportData.summary.total > 0 ? Math.round((exportData.summary.attended_late / exportData.summary.total) * 100) : 0}%)`],
        ['Sin Atender (A Tiempo)', exportData.summary.unattended_ontime.toString()],
        ['Sin Atender (Tarde)', exportData.summary.unattended_late.toString()],
        ['Tiempo Promedio de Respuesta', exportData.summary.avg_response_time ? `${exportData.summary.avg_response_time} días` : 'N/A']
    ];
    
    doc.autoTable({
        startY: yPos,
        head: [['Métrica', 'Valor']],
        body: summaryData,
        theme: 'striped',
        headStyles: { fillColor: [30, 58, 138], textColor: 255 },
        margin: { left: margin, right: margin },
        styles: { fontSize: 10 }
    });
    
    yPos = doc.lastAutoTable.finalY + 15;
    
    // Categories section
    doc.setFontSize(14);
    doc.setTextColor(30, 58, 138);
    doc.text('Estadísticas por Categoría', margin, yPos);
    yPos += 8;
    
    const categoriesTableData = exportData.categories.map(cat => [
        cat.name,
        cat.total.toString(),
        `${cat.attended_ontime} (${cat.total > 0 ? Math.round((cat.attended_ontime / cat.total) * 100) : 0}%)`,
        `${cat.attended_late} (${cat.total > 0 ? Math.round((cat.attended_late / cat.total) * 100) : 0}%)`,
        cat.unattended.toString(),
        cat.avg_response_time ? `${cat.avg_response_time} días` : 'N/A'
    ]);
    
    doc.autoTable({
        startY: yPos,
        head: [['Categoría', 'Total', 'A Tiempo', 'Tarde', 'Sin Atender', 'Tiempo Prom.']],
        body: categoriesTableData,
        theme: 'striped',
        headStyles: { fillColor: [30, 58, 138], textColor: 255 },
        margin: { left: margin, right: margin },
        styles: { fontSize: 9 },
        columnStyles: {
            0: { cellWidth: 40 }
        }
    });
    
    // Check if we need a new page for departments
    yPos = doc.lastAutoTable.finalY + 15;
    if (yPos > 250) {
        doc.addPage();
        yPos = 20;
    }
    
    // Departments section
    doc.setFontSize(14);
    doc.setTextColor(30, 58, 138);
    doc.text('Estadísticas por Departamento', margin, yPos);
    yPos += 8;
    
    const departmentsTableData = exportData.departments.map(dept => [
        dept.name,
        dept.total_assigned.toString(),
        `${dept.attended_ontime} (${dept.total_assigned > 0 ? Math.round((dept.attended_ontime / dept.total_assigned) * 100) : 0}%)`,
        `${dept.attended_late} (${dept.total_assigned > 0 ? Math.round((dept.attended_late / dept.total_assigned) * 100) : 0}%)`,
        dept.avg_response_time ? `${dept.avg_response_time} días` : 'N/A'
    ]);
    
    doc.autoTable({
        startY: yPos,
        head: [['Departamento', 'Asignados', 'A Tiempo', 'Tarde', 'Tiempo Prom.']],
        body: departmentsTableData,
        theme: 'striped',
        headStyles: { fillColor: [30, 58, 138], textColor: 255 },
        margin: { left: margin, right: margin },
        styles: { fontSize: 9 },
        columnStyles: {
            0: { cellWidth: 50 }
        }
    });
    
    // Check if we need a new page for monthly data
    yPos = doc.lastAutoTable.finalY + 15;
    if (yPos > 220) {
        doc.addPage();
        yPos = 20;
    }
    
    // Monthly trend section
    if (exportData.monthly.labels.length > 0) {
        doc.setFontSize(14);
        doc.setTextColor(30, 58, 138);
        doc.text('Tendencia Mensual', margin, yPos);
        yPos += 8;
        
        const monthlyTableData = exportData.monthly.labels.map((label, i) => [
            label,
            exportData.monthly.totals[i].toString(),
            exportData.monthly.attended[i].toString(),
            exportData.monthly.response_times[i] ? `${exportData.monthly.response_times[i]} días` : 'N/A'
        ]);
        
        doc.autoTable({
            startY: yPos,
            head: [['Mes', 'Total', 'Atendidos', 'Tiempo Prom.']],
            body: monthlyTableData,
            theme: 'striped',
            headStyles: { fillColor: [30, 58, 138], textColor: 255 },
            margin: { left: margin, right: margin },
            styles: { fontSize: 9 }
        });
    }
    
    // Footer on each page
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150);
        doc.text(`Página ${i} de ${pageCount}`, pageWidth / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });
        doc.text('ITSCC Buzón de Quejas', margin, doc.internal.pageSize.getHeight() - 10);
    }
    
    // Save the PDF
    const filename = `estadisticas_${exportData.period.replace(/ /g, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.pdf`;
    doc.save(filename);
    
    // Show success notification
    showExportNotification('PDF descargado exitosamente');
}

// Export to Excel function
function exportToExcel() {
    const wb = XLSX.utils.book_new();
    
    // Sheet 1: Summary
    const summaryWS = XLSX.utils.aoa_to_sheet([
        ['Estadísticas de Reportes - ITSCC Buzón'],
        [''],
        ['Periodo:', exportData.period],
        ['Generado:', exportData.generated_at],
        [''],
        ['RESUMEN GENERAL'],
        ['Métrica', 'Valor', 'Porcentaje'],
        ['Total de Reportes', exportData.summary.total, '100%'],
        ['Atendidos a Tiempo', exportData.summary.attended_ontime, exportData.summary.total > 0 ? `${Math.round((exportData.summary.attended_ontime / exportData.summary.total) * 100)}%` : '0%'],
        ['Atendidos Tarde', exportData.summary.attended_late, exportData.summary.total > 0 ? `${Math.round((exportData.summary.attended_late / exportData.summary.total) * 100)}%` : '0%'],
        ['Sin Atender (A Tiempo)', exportData.summary.unattended_ontime, exportData.summary.total > 0 ? `${Math.round((exportData.summary.unattended_ontime / exportData.summary.total) * 100)}%` : '0%'],
        ['Sin Atender (Tarde)', exportData.summary.unattended_late, exportData.summary.total > 0 ? `${Math.round((exportData.summary.unattended_late / exportData.summary.total) * 100)}%` : '0%'],
        ['Tiempo Promedio de Respuesta', exportData.summary.avg_response_time ? `${exportData.summary.avg_response_time} días` : 'N/A', '']
    ]);
    summaryWS['!cols'] = [{ wch: 30 }, { wch: 20 }, { wch: 15 }];
    XLSX.utils.book_append_sheet(wb, summaryWS, 'Resumen');
    
    // Sheet 2: Categories
    const categoriesHeaders = ['Categoría', 'Total', 'Atendidos a Tiempo', '% A Tiempo', 'Atendidos Tarde', '% Tarde', 'Sin Atender', '% Sin Atender', 'Tiempo Promedio (días)'];
    const categoriesRows = exportData.categories.map(cat => [
        cat.name,
        cat.total,
        cat.attended_ontime,
        cat.total > 0 ? `${Math.round((cat.attended_ontime / cat.total) * 100)}%` : '0%',
        cat.attended_late,
        cat.total > 0 ? `${Math.round((cat.attended_late / cat.total) * 100)}%` : '0%',
        cat.unattended,
        cat.total > 0 ? `${Math.round((cat.unattended / cat.total) * 100)}%` : '0%',
        cat.avg_response_time || 'N/A'
    ]);
    const categoriesWS = XLSX.utils.aoa_to_sheet([categoriesHeaders, ...categoriesRows]);
    categoriesWS['!cols'] = [{ wch: 25 }, { wch: 10 }, { wch: 18 }, { wch: 12 }, { wch: 16 }, { wch: 12 }, { wch: 12 }, { wch: 14 }, { wch: 20 }];
    XLSX.utils.book_append_sheet(wb, categoriesWS, 'Categorías');
    
    // Sheet 3: Departments
    const deptHeaders = ['Departamento', 'Total Asignados', 'Atendidos a Tiempo', '% A Tiempo', 'Atendidos Tarde', '% Tarde', 'Tiempo Promedio (días)'];
    const deptRows = exportData.departments.map(dept => [
        dept.name,
        dept.total_assigned,
        dept.attended_ontime,
        dept.total_assigned > 0 ? `${Math.round((dept.attended_ontime / dept.total_assigned) * 100)}%` : '0%',
        dept.attended_late,
        dept.total_assigned > 0 ? `${Math.round((dept.attended_late / dept.total_assigned) * 100)}%` : '0%',
        dept.avg_response_time || 'N/A'
    ]);
    const deptWS = XLSX.utils.aoa_to_sheet([deptHeaders, ...deptRows]);
    deptWS['!cols'] = [{ wch: 30 }, { wch: 15 }, { wch: 18 }, { wch: 12 }, { wch: 16 }, { wch: 12 }, { wch: 20 }];
    XLSX.utils.book_append_sheet(wb, deptWS, 'Departamentos');
    
    // Sheet 4: Monthly Trend
    if (exportData.monthly.labels.length > 0) {
        const monthlyHeaders = ['Mes', 'Total Reportes', 'Reportes Atendidos', '% Atendidos', 'Tiempo Promedio (días)'];
        const monthlyRows = exportData.monthly.labels.map((label, i) => [
            label,
            exportData.monthly.totals[i],
            exportData.monthly.attended[i],
            exportData.monthly.totals[i] > 0 ? `${Math.round((exportData.monthly.attended[i] / exportData.monthly.totals[i]) * 100)}%` : '0%',
            exportData.monthly.response_times[i] || 'N/A'
        ]);
        const monthlyWS = XLSX.utils.aoa_to_sheet([monthlyHeaders, ...monthlyRows]);
        monthlyWS['!cols'] = [{ wch: 15 }, { wch: 15 }, { wch: 18 }, { wch: 12 }, { wch: 20 }];
        XLSX.utils.book_append_sheet(wb, monthlyWS, 'Tendencia Mensual');
    }
    
    // Save the file
    const filename = `estadisticas_${exportData.period.replace(/ /g, '_').toLowerCase()}_${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(wb, filename);
    
    // Show success notification
    showExportNotification('Excel descargado exitosamente');
}

// Notification function
function showExportNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed bottom-4 right-4 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 z-50 animate-slide-up';
    notification.innerHTML = `
        <i class="ph-check-circle text-2xl"></i>
        <span class="font-medium">${message}</span>
    `;
    notification.style.animation = 'slideUp 0.3s ease-out';
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideDown 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<style>
@keyframes slideUp {
    from { transform: translateY(100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@keyframes slideDown {
    from { transform: translateY(0); opacity: 1; }
    to { transform: translateY(100px); opacity: 0; }
}
</style>

<?php include 'components/footer.php'; ?>