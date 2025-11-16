<?php
/**
 * Sistema de gestión automática de estados de reportes
 * Basado en días hábiles (lunes a viernes)
 */

/**
 * Calcula el número de días hábile            'icon' => 'ph-hourglass',
            'color' => 'orange'
        },incluyendo fracciones) entre dos fechas
 * @param DateTime $start_date Fecha inicial
 * @param DateTime $end_date Fecha final
 * @return float Número de días hábiles
 */
function calculateBusinessDays($start_date, $end_date) {
    $start = clone $start_date;
    $end = clone $end_date;

    if ($end <= $start) {
        return 0.0;
    }

    $totalDays = 0.0;
    $current = clone $start;
    $current->setTime(0, 0, 0);

    $endDay = clone $end;
    $endDay->setTime(0, 0, 0);

    while ($current <= $endDay) {
        $dayOfWeek = (int)$current->format('N');

        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $dayStart = clone $current;
            $dayStart->setTime(0, 0, 0);

            $dayEndExclusive = clone $current;
            $dayEndExclusive->setTime(0, 0, 0);
            $dayEndExclusive->modify('+1 day');

            $segmentStart = $dayStart < $start ? clone $start : clone $dayStart;
            $segmentEnd = $dayEndExclusive > $end ? clone $end : clone $dayEndExclusive;

            if ($segmentEnd > $segmentStart) {
                $seconds = $segmentEnd->getTimestamp() - $segmentStart->getTimestamp();
                $totalDays += $seconds / 86400;
            }
        }

        $current->modify('+1 day');
    }

    return $totalDays;
}

/**
 * Formatea un valor de días para mostrarse sin ceros innecesarios
 * @param float $days Valor en días hábiles
 * @param int $precision Número de decimales
 * @return string Valor formateado
 */
function formatBusinessDayValue($days, $precision = 1) {
    $normalized = max(0.0, $days);
    $formatted = number_format($normalized, $precision, '.', '');

    if ($precision > 0) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }

    if ($formatted === '') {
        $formatted = '0';
    }

    return $formatted;
}

/**
 * Genera una etiqueta legible que incluye el valor y el texto singular/plural
 * @param float $days Valor en días hábiles
 * @param string $singular Texto a usar cuando es 1 día
 * @param string $plural Texto a usar en cualquier otro caso
 * @param int $precision Número de decimales para mostrar
 * @return string Texto formateado listo para mostrarse
 */
function formatBusinessDayDiffLabel($days, $singular, $plural, $precision = 1) {
    $normalized = max(0.0, $days);
    $formattedValue = formatBusinessDayValue($normalized, $precision);

    $isSingular = abs($normalized - 1.0) < 0.0001;
    $label = $isSingular ? $singular : $plural;

    return $formattedValue . ' ' . $label;
}

/**
 * Determina el estado correcto de un reporte basado en su fecha de creación y atención
 * @param string $created_at Fecha de creación del reporte
 * @param string|null $attended_at Fecha de atención del reporte (null si no ha sido atendido)
 * @return string Estado del reporte: 'unattended_ontime', 'unattended_late', 'attended_ontime', 'attended_late'
 */
function determineReportStatus($created_at, $attended_at = null) {
    $created_date = new DateTime($created_at);
    $now = new DateTime();
    
    // Si el reporte ha sido atendido
    if ($attended_at !== null) {
        $attended_date = new DateTime($attended_at);
        $business_days = calculateBusinessDays($created_date, $attended_date);
        
        // Si se atendió dentro de 5 días hábiles
        if ($business_days <= 5) {
            return 'attended_ontime';
        } else {
            return 'attended_late';
        }
    }
    
    // Si el reporte NO ha sido atendido
    $business_days = calculateBusinessDays($created_date, $now);
    
    // Si aún está dentro de los 5 días hábiles
    if ($business_days <= 5) {
        return 'unattended_ontime';
    } else {
        return 'unattended_late';
    }
}

/**
 * Obtiene información de visualización para un estado
 * @param string $status Estado del reporte
 * @return array Array con 'text', 'class' e 'icon' para mostrar el estado
 */
function getStatusDisplayInfo($status) {
        $statusMap = [
        'unattended_ontime' => [
            'text' => 'Sin atender',
            'class' => 'bg-blue-100 text-blue-800 ring-blue-600/20',
            'icon' => 'ph-clock-bold',
            'color' => 'blue'
        ],
        'unattended_late' => [
            'text' => 'Sin atender',
            'class' => 'bg-red-100 text-red-800 ring-red-600/20',
            'icon' => 'ph-warning-bold',
            'color' => 'red'
        ],
        'attended_ontime' => [
            'text' => 'Atendido',
            'class' => 'bg-green-100 text-green-800 ring-green-600/20',
            'icon' => 'ph-check-circle-bold',
            'color' => 'green'
        ],
        'attended_late' => [
            'text' => 'Atendido tarde',
            'class' => 'bg-orange-100 text-orange-800 ring-orange-600/20',
            'icon' => 'ph-hourglass-bold',
            'color' => 'orange'
        ],
        'invalid' => [
            'text' => 'Inválido',
            'class' => 'bg-red-100 text-red-800 ring-red-600/20',
            'icon' => 'ph-x-circle-bold',
            'color' => 'red'
        ],
        'duplicate' => [
            'text' => 'Duplicado',
            'class' => 'bg-purple-100 text-purple-800 ring-purple-600/20',
            'icon' => 'ph-copy-bold',
            'color' => 'purple'
        ]
    ];
    
    return $statusMap[$status] ?? $statusMap['unattended_ontime'];
}

/**
 * Actualiza el estado de un reporte específico
 * @param mysqli $conn Conexión a la base de datos
 * @param int $complaint_id ID del reporte
 * @return bool True si se actualizó correctamente
 */
function updateComplaintStatus($conn, $complaint_id) {
    // Obtener información del reporte
    $stmt = $conn->prepare("SELECT created_at, attended_at FROM complaints WHERE id = ?");
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    
    if (!$complaint) {
        return false;
    }
    
    // Determinar el nuevo estado
    $new_status = determineReportStatus($complaint['created_at'], $complaint['attended_at']);
    
    // Actualizar el estado en la base de datos
    $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $complaint_id);
    return $stmt->execute();
}

/**
 * Actualiza todos los reportes que necesitan actualización de estado
 * Solo actualiza reportes que están "a tiempo" o "sin atender"
 * @param mysqli $conn Conexión a la base de datos
 * @return int Número de reportes actualizados
 */
function updateAllPendingStatuses($conn) {
    $updated_count = 0;
    
    // Obtener todos los reportes que no han sido atendidos o que están a tiempo
    // No necesitamos revisar los que ya están atendidos tarde o atendidos a tiempo
    $query = "SELECT id, created_at, attended_at, status 
              FROM complaints 
              WHERE status IN ('unattended_ontime', 'unattended_late')
              ORDER BY created_at ASC";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $new_status = determineReportStatus($row['created_at'], $row['attended_at']);
        
        // Solo actualizar si el estado cambió
        if ($new_status !== $row['status']) {
            $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $row['id']);
            if ($stmt->execute()) {
                $updated_count++;
            }
        }
    }
    
    return $updated_count;
}

/**
 * Marca un reporte como atendido
 * @param mysqli $conn Conexión a la base de datos
 * @param int $complaint_id ID del reporte
 * @return bool True si se marcó correctamente
 */
function markComplaintAsAttended($conn, $complaint_id) {
    // Obtener la fecha de creación
    $stmt = $conn->prepare("SELECT created_at FROM complaints WHERE id = ?");
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    
    if (!$complaint) {
        return false;
    }
    
    $now = new DateTime();
    $attended_at = $now->format('Y-m-d H:i:s');
    
    // Determinar el estado basado en la fecha de atención
    $new_status = determineReportStatus($complaint['created_at'], $attended_at);
    
    // Actualizar el reporte
    $stmt = $conn->prepare("UPDATE complaints SET status = ?, attended_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $attended_at, $complaint_id);
    return $stmt->execute();
}

/**
 * Obtiene estadísticas de reportes por estado
 * @param mysqli $conn Conexión a la base de datos
 * @return array Array con conteos por cada estado
 */
function getStatusStatistics($conn) {
    $query = "SELECT status, COUNT(*) as count FROM complaints GROUP BY status";
    $result = $conn->query($query);
    
    $stats = [
        'unattended_ontime' => 0,
        'unattended_late' => 0,
        'attended_ontime' => 0,
        'attended_late' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = (int)$row['count'];
    }
    
    return $stats;
}
?>
