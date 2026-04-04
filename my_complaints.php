<?php 
$page_title = 'Mis Reportes - Buzón de Quejas'; 
$show_global_blobs = false; 
require_once 'config.php';
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

<div class="bg-transparent flex-grow">
    <main class="container mx-auto px-4 py-12">
        <div class="max-w-5xl mx-auto">
            
            <div class="liquid-glass rounded-2xl shadow-xl overflow-hidden">
                <div class="p-8 md:p-12">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                        <div class="flex items-center gap-4">
                             <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 dark:bg-blue-500/20 rounded-xl flex-shrink-0 shadow-sm dark:shadow-none border border-transparent dark:border-blue-500/30">
                                <i class="ph-folder-open text-white dark:text-blue-400 text-2xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Mis Reportes</h1>
                                <p class="text-gray-500 mt-1">Aquí puedes ver el historial y estado de tus reportes.</p>
                            </div>
                        </div>
                        <a href="submit_complaint.php" class="w-full md:w-auto flex justify-center items-center bg-blue-600 hover:bg-blue-700 text-white dark:bg-blue-500/15 dark:text-blue-400 dark:border dark:border-blue-400/30 dark:hover:bg-blue-500/25 text-base font-semibold py-3 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-slate-900 shadow-lg dark:shadow-none transform hover:-translate-y-0.5 transition-all duration-300 ease-in-out">
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
                        <!-- Complaints Grid (Compact Cards) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($complaints as $complaint): ?>
                                <?php 
                                    $statusInfo = getStatusInfo($complaint['status']); 
                                    $createdDate = new DateTime($complaint['created_at']);
                                    $now = new DateTime();
                                    $interval = $createdDate->diff($now);
                                    $daysAgo = $interval->days;
                                    $timeAgoText = $daysAgo === 0 ? 'Hoy' : ($daysAgo === 1 ? 'Ayer' : "Hace $daysAgo días");
                                    
                                    // Short description
                                    $rawDesc = isset($complaint['description']) ? $complaint['description'] : '';
                                    $cleanDesc = trim(strip_tags($rawDesc));
                                    $maxLen = 100;
                                    if (function_exists('mb_strlen')) {
                                        $descLen = mb_strlen($cleanDesc, 'UTF-8');
                                        $shortDesc = $descLen > $maxLen ? mb_substr($cleanDesc, 0, $maxLen, 'UTF-8') . '…' : $cleanDesc;
                                    } else {
                                        $descLen = strlen($cleanDesc);
                                        $shortDesc = $descLen > $maxLen ? substr($cleanDesc, 0, $maxLen) . '…' : $cleanDesc;
                                    }
                                ?>
                                <div class="glass-inner rounded-xl p-5 border border-gray-200/50 dark:border-white/5 transition-all">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-500">#<?php echo $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                            <div class="flex items-center gap-1">
                                                <?php if ($complaint['is_anonymous']): ?>
                                                    <div class="relative group flex items-center justify-center">
                                                        <span class="cursor-help inline-flex items-center justify-center w-5 h-5 rounded-full bg-purple-100 text-purple-700 ring-1 ring-inset ring-purple-600/20">
                                                            <i class="ph-ghost text-[10px]"></i>
                                                        </span>
                                                        <div class="absolute bottom-full mb-2 opacity-0 group-hover:opacity-100 transition-all duration-200 bg-white text-gray-800 text-xs font-semibold rounded-lg py-2 px-3 whitespace-nowrap pointer-events-none z-50 shadow-xl border border-gray-100">
                                                            Tú lo enviaste como anónimo
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full <?php echo $statusInfo['class']; ?> ring-1 ring-inset whitespace-nowrap">
                                                <?php echo $statusInfo['text']; ?>
                                            </span>
                                        </div>
                                        <a href="view_complaint.php?id=<?php echo $complaint['id']; ?>" class="text-xs font-medium text-blue-600 dark:text-blue-400 flex items-center gap-1 hover:text-blue-800 dark:hover:text-blue-300">
                                            Detalles <i class="ph-caret-right font-bold"></i>
                                        </a>
                                    </div>

                                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 line-clamp-2 leading-snug"><?php echo htmlspecialchars($shortDesc); ?></h3>
                                    
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-900 dark:text-gray-200 mt-auto">
                                        <div class="flex items-center gap-1.5">
                                            <i class="ph-tag text-gray-400"></i>
                                            <span class="truncate max-w-[120px]"><?php echo $complaint['category_name'] ? htmlspecialchars($complaint['category_name']) : 'Sin categoría'; ?></span>
                                        </div>
                                        <div class="text-gray-300 dark:text-gray-600 hidden sm:block">•</div>
                                        <div class="flex items-center gap-1.5">
                                            <i class="ph-clock text-gray-400"></i>
                                            <span><?php echo $timeAgoText; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'components/footer.php'; ?>