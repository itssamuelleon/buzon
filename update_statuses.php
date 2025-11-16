<?php
/**
 * Script de actualización automática de estados de reportes
 * Este script se ejecuta automáticamente en cada carga de página
 * para mantener los estados actualizados de forma eficiente
 */

// Solo incluir config si no está ya incluido
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

require_once 'status_helper.php';

// Actualizar solo los reportes que necesitan revisión
// (aquellos que no han sido atendidos)
$updated = updateAllPendingStatuses($conn);

// Opcional: registrar en log para debugging (comentar en producción)
// error_log("Status update: $updated reportes actualizados - " . date('Y-m-d H:i:s'));
?>
