<?php 
$page_title = 'Estadísticas - ITSCC Buzón'; 
require_once 'config.php';
require_once 'status_helper.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

include 'components/header.php';

// Incluir Chart.js y sus plugins (después del header)
echo '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@1.2.0/dist/chartjs-chart-matrix.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
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

?>

<div class="min-h-screen bg-gray-50">
    <main class="container mx-auto px-4 py-8">
        <!-- Encabezado -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Estadísticas y Análisis</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Análisis detallado de reportes y métricas de atención
                    </p>
                </div>
                <a href="dashboard.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    <i class="ph-arrow-left text-lg mr-2"></i>
                    Volver al Dashboard
                </a>
            </div>
        </div>

        <!-- Filtro de año -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-8">
            <form method="GET" class="flex items-center gap-4">
                <label for="year" class="font-medium text-gray-700">Periodo:</label>
                <select id="year" name="year" 
                        class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                        onchange="this.form.submit()">
                    <option value="0" <?php echo $selected_year == 0 ? 'selected' : ''; ?>>Todos los años</option>
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Resumen General -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <?php
            $total = $stats_result['total_complaints'];
            $metrics = [
                [
                    'label' => 'Total de Reportes',
                    'value' => $total,
                    'icon' => 'ph-files',
                    'color' => 'blue'
                ],
                [
                    'label' => 'Atendidos a Tiempo',
                    'value' => $stats_result['attended_ontime'],
                    'percent' => $total > 0 ? round(($stats_result['attended_ontime'] / $total) * 100) : 0,
                    'icon' => 'ph-check-circle',
                    'color' => 'green'
                ],
                [
                    'label' => 'Atendidos Tarde',
                    'value' => $stats_result['attended_late'],
                    'percent' => $total > 0 ? round(($stats_result['attended_late'] / $total) * 100) : 0,
                    'icon' => 'ph-clock-counter-clockwise',
                    'color' => 'yellow'
                ],
                [
                    'label' => 'Sin Atender',
                    'value' => $stats_result['unattended_ontime'] + $stats_result['unattended_late'],
                    'percent' => $total > 0 ? round((($stats_result['unattended_ontime'] + $stats_result['unattended_late']) / $total) * 100) : 0,
                    'icon' => 'ph-hourglass',
                    'color' => 'red'
                ]
            ];
            ?>
            <?php foreach ($metrics as $metric): ?>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="inline-flex p-3 rounded-lg text-<?php echo $metric['color']; ?>-600 bg-<?php echo $metric['color']; ?>-100">
                            <i class="<?php echo $metric['icon']; ?> text-2xl"></i>
                        </span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                <?php echo $metric['label']; ?>
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900">
                                    <?php echo number_format($metric['value']); ?>
                                </div>
                                <?php if (isset($metric['percent'])): ?>
                                <div class="ml-2 flex items-baseline text-sm font-semibold text-<?php echo $metric['color']; ?>-600">
                                    <?php echo $metric['percent']; ?>%
                                </div>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Gráficas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Estado de Reportes -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Estado de Reportes</h3>
                <div class="aspect-[4/3]">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Tendencia Mensual -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Tendencia Mensual</h3>
                <div class="aspect-[4/3]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Segunda fila de gráficas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Mapa de Calor de Actividad -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Actividad por Hora y Día</h3>
                <div class="aspect-[4/3]">
                    <canvas id="heatmapChart"></canvas>
                </div>
            </div>

            <!-- Tiempo Promedio de Respuesta -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Tiempo Promedio de Respuesta</h3>
                <div class="aspect-[4/3]">
                    <canvas id="responseTimeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tercera fila de gráficas -->
        <div class="grid grid-cols-1 gap-8 mb-8">
            <!-- Eficiencia por Departamento -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Eficiencia por Departamento</h3>
                <div style="height: 400px;">
                    <canvas id="efficiencyChart"></canvas>
                </div>
            </div>
        </div>
        </div>

        <!-- Estadísticas por Categoría -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas por Categoría</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tiempo Promedio
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($category['category_name'] ?? 'Sin categoría'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $category['total']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-green-600">
                                        <?php 
                                        $percent = $category['total'] > 0 ? round(($category['attended_ontime'] / $category['total']) * 100) : 0;
                                        echo $category['attended_ontime'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-yellow-600">
                                        <?php 
                                        $percent = $category['total'] > 0 ? round(($category['attended_late'] / $category['total']) * 100) : 0;
                                        echo $category['attended_late'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-red-600">
                                        <?php 
                                        $unattended = $category['unattended_ontime'] + $category['unattended_late'];
                                        $percent = $category['total'] > 0 ? round(($unattended / $category['total']) * 100) : 0;
                                        echo $unattended . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
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
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas por Departamento</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tiempo Promedio
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($department = $departments_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $department['total_assigned']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-green-600">
                                        <?php 
                                        $percent = $department['total_assigned'] > 0 ? 
                                            round(($department['attended_ontime'] / $department['total_assigned']) * 100) : 0;
                                        echo $department['attended_ontime'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-yellow-600">
                                        <?php 
                                        $percent = $department['total_assigned'] > 0 ? 
                                            round(($department['attended_late'] / $department['total_assigned']) * 100) : 0;
                                        echo $department['attended_late'] . ' (' . $percent . '%)';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
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
</script>

<?php include 'components/footer.php'; ?>