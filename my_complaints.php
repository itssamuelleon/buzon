<?php 
$page_title = 'Mis Reportes - ITSCC Buzón'; 
include 'components/header.php';
require_once 'status_helper.php'; 

if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('my_complaints.php'));
    exit;
}

// Get both anonymous and non-anonymous complaints for the logged-in user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT c.id, c.folio, c.status, c.created_at, c.is_anonymous, c.description,
           cat.name as category_name
    FROM complaints c 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$complaints = $result->fetch_all(MYSQLI_ASSOC);

// Helper function/map for status display
function getStatusInfo($status) {
    $info = getStatusDisplayInfo($status);
    // Agregar el icono HTML formateado
    $color = $info['color'];
    $info['icon_html'] = '<i class="' . $info['icon'] . ' text-' . $color . '-600"></i>';
    return $info;
}

?>

<div class="bg-gray-50 min-h-screen">
    <main class="container mx-auto px-4 py-12">
        <div class="max-w-5xl mx-auto">
            
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="p-8 md:p-12">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                        <div class="flex items-center gap-4">
                             <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center">
                                <i class="ph-folder-open text-4xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Mis Reportes</h1>
                                <p class="text-gray-500 mt-1">Aquí puedes ver el historial y estado de tus reportes.</p>
                            </div>
                        </div>
                        <a href="submit_complaint.php" class="w-full md:w-auto flex justify-center items-center bg-blue-600 text-white text-base font-semibold py-3 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-lg transform hover:-translate-y-1 transition-all duration-300 ease-in-out">
                            <i class="ph-plus-circle text-xl mr-2"></i>
                            <span>Nuevo Reporte</span>
                        </a>
                    </div>

                    <?php if (empty($complaints)): ?>
                        <!-- Empty State -->
                        <div class="text-center p-8 md:p-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                            <div class="w-20 h-20 mx-auto bg-gray-200 text-gray-500 rounded-full flex items-center justify-center">
                                <i class="ph-files text-5xl"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800 mt-6">No has enviado ningún reporte todavía</h2>
                            <p class="text-gray-600 mt-2 max-w-md mx-auto">Cuando envíes un reporte, aparecerá aquí para que puedas seguir su progreso.</p>
                            <div class="mt-8">
                                <a href="submit_complaint.php" class="inline-block bg-blue-600 text-white font-semibold py-3 px-8 rounded-lg hover:bg-blue-700 transition-colors duration-300 shadow-md">
                                    Enviar mi primer reporte
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Complaints List -->
                        <div class="space-y-4">
                            <?php foreach ($complaints as $complaint): ?>
                                <?php $statusInfo = getStatusInfo($complaint['status']); ?>
                                <a href="view_complaint.php?id=<?php echo $complaint['id']; ?>" class="block bg-white hover:bg-gray-50 p-6 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all duration-300 ease-in-out cursor-pointer">
                                    <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                                        <!-- Left Side: Info -->
                                        <div class="flex-grow">
                                            <div class="flex items-center gap-3 mb-2">
                                                <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium <?php echo $statusInfo['class']; ?> ring-1 ring-inset">
                                                    <i class="<?php echo $statusInfo['icon']; ?> text-<?php echo $statusInfo['color']; ?>-600"></i>
                                                    <?php echo $statusInfo['text']; ?>
                                                </span>
                                                <?php if ($complaint['is_anonymous']): ?>
                                                    <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-purple-100 text-purple-800 ring-1 ring-inset ring-purple-600/20">
                                                        <i class="ph-user-circle-gear text-purple-600"></i>
                                                        Anónimo
                                                    </span>
                                                <?php endif; ?>
                                                <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-lg text-sm font-medium bg-gray-100 text-gray-800">
                                                    <i class="ph-tag text-gray-600"></i>
                                                    <?php echo $complaint['category_name'] ? htmlspecialchars($complaint['category_name']) : 'Sin categoría'; ?>
                                                </span>
                                            </div>
                                            <h3 class="text-lg font-bold text-gray-700">
                                                Folio #<?php echo $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT); ?>
                                            </h3>
                                            <?php
                                                $rawDesc = isset($complaint['description']) ? $complaint['description'] : '';
                                                $cleanDesc = trim(strip_tags($rawDesc));
                                                $maxLen = 160;
                                                if (function_exists('mb_strlen')) {
                                                    $descLen = mb_strlen($cleanDesc, 'UTF-8');
                                                    $shortDesc = $descLen > $maxLen
                                                        ? mb_substr($cleanDesc, 0, $maxLen, 'UTF-8') . '…'
                                                        : $cleanDesc;
                                                } else {
                                                    $descLen = strlen($cleanDesc);
                                                    $shortDesc = $descLen > $maxLen
                                                        ? substr($cleanDesc, 0, $maxLen) . '…'
                                                        : $cleanDesc;
                                                }
                                            ?>
                                            <?php if (!empty($shortDesc)): ?>
                                            <div class="mt-2 text-sm text-gray-700">
                                                <p class="leading-6"><?php echo htmlspecialchars($shortDesc); ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Right Side: Date & Action -->
                                        <div class="flex-shrink-0 text-left sm:text-right flex flex-col items-start sm:items-end">
                                            <p class="text-sm text-gray-500 mb-2">
                                                <?php 
                                                    $date = new DateTime($complaint['created_at']);
                                                    echo $date->format('d/m/Y \a \l\a\s H:i'); 
                                                ?>
                                            </p>
                                            <?php
                                                $createdDate = new DateTime($complaint['created_at']);
                                                $now = new DateTime();
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
                                            <div class="mb-2">
                                                <span class="px-2 inline-flex items-center gap-x-1.5 text-xs leading-5 font-medium rounded-full bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-600/20">
                                                    <i class="ph-clock text-gray-600"></i>
                                                    <?php echo $timeText; ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center text-blue-600 font-semibold group">
                                                <span>Ver Detalles</span>
                                                <i class="ph-arrow-right text-lg ml-1 group-hover:translate-x-1 transition-transform"></i>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
             <p class="text-center mt-8 text-sm text-gray-500">
                 Los reportes marcados como anónimos mantendrán tu identidad oculta para los administradores, pero podrás verlos aquí para darles seguimiento.
             </p>
        </div>
    </main>
</div>

<?php include 'components/footer.php'; ?>