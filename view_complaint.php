<?php 
// Cargar configuración y dependencias ANTES de cualquier salida HTML
require_once 'config.php';
require_once 'config/email_config.php';
require_once 'status_helper.php';
require_once 'services/gemini_service.php';

// Verificar autenticación
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($complaint_id === 0) {
    header('Location: index.php');
    exit;
}



// Handle response evidence upload
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['response_evidence'])) {
    $upload_dir = 'uploads/response_evidence/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $files = $_FILES['response_evidence'];
    $file_count = count($files['name']);
    
    $conn->begin_transaction();
    try {
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = basename($files['name'][$i]);
                $file_tmp = $files['tmp_name'][$i];
                $file_type = $files['type'][$i];
                $file_size = $files['size'][$i];
                
                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $unique_name;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    // Insert into database
                    $stmt = $conn->prepare("INSERT INTO response_evidence (complaint_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issiii", $complaint_id, $file_name, $file_path, $file_type, $file_size, $_SESSION['user_id']);
                    $stmt->execute();
                }
            }
        }
        
        // IMPORTANTE: Solo marcar como atendido si es la primera vez que se sube evidencia
        // Verificar si ya existe evidencia previa
        $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM response_evidence WHERE complaint_id = ?");
        $stmt_check->bind_param("i", $complaint_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $evidence_count = $result_check->fetch_assoc()['count'];
        
        // Solo marcar como atendido si es la primera evidencia (antes de insertar, count era 0)
        if ($evidence_count == $file_count) {
            markComplaintAsAttended($conn, $complaint_id);
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Evidencia de respuesta subida exitosamente.";
        header("Location: view_complaint.php?id=" . $complaint_id);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error al subir evidencia: " . $e->getMessage();
        header("Location: view_complaint.php?id=" . $complaint_id);
        exit;
    }
}

// Handle status update and department assignments
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['status'])) {
        $new_status = $_POST['status'];
        
        // Si se selecciona 'unattended', calcular automáticamente si es a tiempo o tarde
        if ($new_status === 'unattended') {
            // Obtener fecha de creación del reporte
            $stmt_get = $conn->prepare("SELECT created_at FROM complaints WHERE id = ?");
            $stmt_get->bind_param("i", $complaint_id);
            $stmt_get->execute();
            $result = $stmt_get->get_result();
            $complaint_data = $result->fetch_assoc();
            
            // Calcular el estado correcto basado en días hábiles
            $new_status = determineReportStatus($complaint_data['created_at'], null);
            
            // Limpiar attended_at
            $stmt = $conn->prepare("UPDATE complaints SET status = ?, attended_at = NULL WHERE id = ?");
            $stmt->bind_param("si", $new_status, $complaint_id);
            $stmt->execute();
            $_SESSION['success_message'] = "Estado actualizado a 'Sin atender'. El sistema calculó automáticamente si está a tiempo o tarde.";
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } else {
            $valid_statuses = ['attended_ontime', 'attended_late', 'invalid', 'duplicate'];
            if (in_array($new_status, $valid_statuses)) {
                $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $complaint_id);
                $stmt->execute();
                $_SESSION['success_message'] = "Estado actualizado correctamente.";
                header("Location: view_complaint.php?id=" . $complaint_id);
                exit;
            }
        }
    } elseif (isset($_POST['assign_departments'])) {
        // Handle department assignments
        $selected_departments = isset($_POST['departments']) ? $_POST['departments'] : [];
        
        $conn->begin_transaction();
        try {
            // Remove all current assignments
            $stmt = $conn->prepare("DELETE FROM complaint_departments WHERE complaint_id = ?");
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            
            // Add new assignments and queue emails for background processing
            $queued_count = 0;
            
            if (!empty($selected_departments)) {
                $stmt = $conn->prepare("INSERT INTO complaint_departments (complaint_id, department_id) VALUES (?, ?)");
                $stmt_queue = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status) VALUES (?, ?, 'pending')");
                
                foreach ($selected_departments as $dept_id) {
                    if (is_numeric($dept_id)) {
                        $dept_id = intval($dept_id);
                        
                        // Add to complaint_departments
                        $stmt->bind_param("ii", $complaint_id, $dept_id);
                        $stmt->execute();
                        
                        // Queue email for background processing
                        $stmt_queue->bind_param("ii", $complaint_id, $dept_id);
                        $stmt_queue->execute();
                        $queued_count++;
                    }
                }
            }
            
            $conn->commit();
            
            // Prepare success message
            $success_msg = "Departamentos asignados correctamente.";
            $is_test_mode = function_exists('isTestMode') && isTestMode();
            if ($is_test_mode) {
                $success_msg .= " Modo de prueba activado: los correos se enviarán a " . SMTP_USERNAME . " en lugar de los departamentos.";
            } else {
                $success_msg .= " Los correos de notificación se enviarán en segundo plano.";
            }
            
            // Trigger background email processing
            if ($queued_count > 0) {
                // Try to trigger async processing (non-blocking)
                $this_file = $_SERVER['PHP_SELF'];
                $process_url = str_replace(basename($this_file), 'process_email_queue.php', $this_file);
                
                // Attempt async call using file_get_contents with timeout
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => 'http://localhost' . $process_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT_MS => 100,
                        CURLOPT_CONNECTTIMEOUT_MS => 100,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
            
            $_SESSION['success_message'] = $success_msg;
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error al asignar departamentos: " . $e->getMessage();
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
    } elseif (isset($_POST['update_category'])) {
        // Handle category update
        $new_category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
        
        try {
            $stmt = $conn->prepare("UPDATE complaints SET category_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_category_id, $complaint_id);
            $stmt->execute();
            
            // Get the new category name for the success message
            $category_name = 'Sin categoría';
            if ($new_category_id) {
                $stmt_cat = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                $stmt_cat->bind_param("i", $new_category_id);
                $stmt_cat->execute();
                $cat_result = $stmt_cat->get_result()->fetch_assoc();
                if ($cat_result) {
                    $category_name = $cat_result['name'];
                }
            }
            
            $_SESSION['success_message'] = "Categoría actualizada a: " . htmlspecialchars($category_name);
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error al actualizar la categoría: " . $e->getMessage();
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
    } elseif (isset($_POST['apply_gemini_suggestions'])) {
        // Handle applying Gemini suggestions
        $categoria_id = isset($_POST['gemini_categoria_id']) ? intval($_POST['gemini_categoria_id']) : null;
        $departamentos_json = isset($_POST['gemini_departamentos']) ? $_POST['gemini_departamentos'] : '[]';
        
        $conn->begin_transaction();
        try {
            // 1. Update category if provided
            if ($categoria_id && $categoria_id > 0) {
                $stmt = $conn->prepare("UPDATE complaints SET category_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $categoria_id, $complaint_id);
                $stmt->execute();
            }
            
            // 2. Update departments (remove all and add new ones)
            $departamentos_data = json_decode($departamentos_json, true);
            $department_ids = [];
            
            if (is_array($departamentos_data) && !empty($departamentos_data)) {
                // Extract department IDs from the data
                foreach ($departamentos_data as $dept) {
                    if (isset($dept['id']) && is_numeric($dept['id'])) {
                        $department_ids[] = intval($dept['id']);
                    }
                }
            }
            
            // Remove all current department assignments
            $stmt = $conn->prepare("DELETE FROM complaint_departments WHERE complaint_id = ?");
            $stmt->bind_param("i", $complaint_id);
            $stmt->execute();
            
            // Add new department assignments and queue emails for background processing
            $queued_count = 0;
            
            if (!empty($department_ids)) {
                $stmt = $conn->prepare("INSERT INTO complaint_departments (complaint_id, department_id) VALUES (?, ?)");
                $stmt_queue = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status) VALUES (?, ?, 'pending')");
                
                foreach ($department_ids as $dept_id) {
                    $stmt->bind_param("ii", $complaint_id, $dept_id);
                    $stmt->execute();
                    
                    // Queue email for background processing
                    $stmt_queue->bind_param("ii", $complaint_id, $dept_id);
                    $stmt_queue->execute();
                    $queued_count++;
                }
            }
            
            $conn->commit();
            
            // Prepare success message
            $success_msg = "Sugerencias de Gemini aplicadas correctamente. ";
            if ($categoria_id && $categoria_id > 0) {
                $success_msg .= "Categoría actualizada. ";
            }
            if (!empty($department_ids)) {
                $success_msg .= "Departamentos asignados.";
                $is_test_mode = function_exists('isTestMode') && isTestMode();
                if ($is_test_mode) {
                    $success_msg .= " Modo de prueba activado: los correos se enviarán a " . SMTP_USERNAME . " en lugar de los departamentos.";
                } else {
                    $success_msg .= " Los correos de notificación se enviarán en segundo plano.";
                }
            }
            
            // Trigger background email processing
            if ($queued_count > 0) {
                // Try to trigger async processing (non-blocking)
                $this_file = $_SERVER['PHP_SELF'];
                $process_url = str_replace(basename($this_file), 'process_email_queue.php', $this_file);
                
                // Attempt async call using file_get_contents with timeout
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => 'http://localhost' . $process_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT_MS => 100,
                        CURLOPT_CONNECTTIMEOUT_MS => 100,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
            
            $_SESSION['success_message'] = $success_msg;
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error al aplicar sugerencias de Gemini: " . $e->getMessage();
            header("Location: view_complaint.php?id=" . $complaint_id);
            exit;
        }
    }
}

// Get complaint details
$stmt = $conn->prepare("
    SELECT c.*, u.name as user_name, u.email as user_email, cat.name as category_name 
    FROM complaints c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN categories cat ON c.category_id = cat.id 
    WHERE c.id = ?
");
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header('Location: dashboard.php');
    exit;
}

$stmt_att = $conn->prepare("SELECT * FROM attachments WHERE complaint_id = ?");
$stmt_att->bind_param("i", $complaint_id);
$stmt_att->execute();
$attachments = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);

// Get response evidence
$stmt_resp = $conn->prepare("SELECT re.*, u.name as uploaded_by_name FROM response_evidence re LEFT JOIN users u ON re.uploaded_by = u.id WHERE re.complaint_id = ? ORDER BY re.uploaded_at DESC");
$stmt_resp->bind_param("i", $complaint_id);
$stmt_resp->execute();
$response_evidence = $stmt_resp->get_result()->fetch_all(MYSQLI_ASSOC);

// Recuperar mensajes de sesión
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
$gemini_result_data = isset($_SESSION['gemini_result_data']) ? $_SESSION['gemini_result_data'] : null;
$gemini_result_raw = isset($_SESSION['gemini_result_raw']) ? $_SESSION['gemini_result_raw'] : null;

// Limpiar mensajes de sesión después de recuperarlos
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
unset($_SESSION['gemini_result_data']);
unset($_SESSION['gemini_result_raw']);

// Correos enviados (para toast)
$emails_sent_count = isset($_SESSION['emails_sent']) ? intval($_SESSION['emails_sent']) : 0;
unset($_SESSION['emails_sent']);

// Usar la función del helper para obtener información del estado
function getStatusInfo($status) {
    return getStatusDisplayInfo($status);
}

function getFileIcon($file_type) {
    if (str_contains($file_type, 'image')) return 'ph-image';
    if (str_contains($file_type, 'pdf')) return 'ph-file-pdf';
    if (str_contains($file_type, 'word')) return 'ph-file-doc';
    return 'ph-file';
}

// AHORA sí incluir el header después de todo el procesamiento
$page_title = 'Ver Reporte - ITSCC Buzón';
include 'components/header.php';
?>

<div class="bg-gray-50 min-h-screen" 
     x-data="{ 
         isModalOpen: false, 
         modalImageUrl: '', 
         isAdminPanelOpen: false,
         activeTab: 'departments',
         isUploadModalOpen: false,
         adminModalMode: 'full'
     }" 
     @keydown.escape.window="isModalOpen = false; isAdminPanelOpen = false; isUploadModalOpen = false; adminModalMode = 'full'">
    
    <!-- Image Preview Modal -->
    <div x-show="isModalOpen" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isModalOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-4xl max-h-full">
            <img :src="modalImageUrl" alt="Vista previa de la evidencia" class="w-full h-auto object-contain rounded-lg shadow-2xl" style="max-height: 90vh;">
            <button @click="isModalOpen = false" class="absolute -top-4 -right-4 w-10 h-10 bg-white text-gray-700 rounded-full flex items-center justify-center shadow-lg hover:bg-gray-200 transition">
                <i class="ph-x text-2xl"></i>
            </button>
        </div>
    </div>

    <!-- Upload Evidence Modal -->
    <div x-show="isUploadModalOpen" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isUploadModalOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white w-full max-w-2xl rounded-xl shadow-2xl" @click.stop>
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between rounded-t-xl">
                <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="ph-upload-simple text-blue-600"></i>
                    Agregar Evidencia de Respuesta
                </h3>
                <button @click="isUploadModalOpen = false" class="text-gray-400 hover:text-gray-500">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <div class="p-6" x-data="{ 
                isDragging: false, 
                hasFiles: false,
                fileCount: 0,
                previews: [],
                updateFileCount() {
                    const input = document.getElementById('response_evidence_input_modal');
                    this.fileCount = input.files.length;
                    this.hasFiles = this.fileCount > 0;
                    this.generatePreviews(input.files);
                },
                generatePreviews(files) {
                    this.previews = [];
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                this.previews.push(e.target.result);
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                }
            }">
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <!-- Drag & Drop Zone -->
                    <div @drop.prevent="isDragging = false; $refs.fileInputModal.files = $event.dataTransfer.files; updateFileCount()"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @click="$refs.fileInputModal.click()"
                         :class="{'border-blue-500 bg-blue-50': isDragging, 'border-gray-300 bg-white': !isDragging}"
                         class="relative border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-all hover:border-blue-400 hover:bg-blue-50">
                        
                        <input type="file" 
                               id="response_evidence_input_modal"
                               name="response_evidence[]" 
                               multiple 
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                               @change="updateFileCount()"
                               x-ref="fileInputModal"
                               class="hidden">
                        
                        <div class="flex flex-col items-center gap-3">
                            <div x-show="previews.length === 0" class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="ph-upload-simple text-3xl text-blue-600"></i>
                            </div>
                            
                            <!-- Image Previews -->
                            <div x-show="previews.length > 0" class="grid grid-cols-3 gap-2 w-full max-w-md">
                                <template x-for="(preview, index) in previews" :key="index">
                                    <div class="relative aspect-square rounded-lg overflow-hidden border-2 border-blue-200">
                                        <img :src="preview" class="w-full h-full object-cover">
                                    </div>
                                </template>
                            </div>
                            
                            <div>
                                <p class="text-base font-semibold text-gray-700 mb-1">
                                    <span x-show="!isDragging && !hasFiles">Arrastra archivos aquí o haz clic para seleccionar</span>
                                    <span x-show="isDragging" class="text-blue-600">Suelta los archivos aquí</span>
                                    <span x-show="hasFiles && !isDragging" class="text-green-600">
                                        <i class="ph-check-circle mr-1"></i>
                                        <span x-text="fileCount"></span> archivo<span x-show="fileCount > 1">s</span> listo<span x-show="fileCount > 1">s</span> para subir
                                    </span>
                                </p>
                                <p class="text-sm text-gray-500">Imágenes, PDF, Word, Excel</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-sm text-blue-700 flex items-center gap-2">
                            <i class="ph-info"></i>
                            Agregar más evidencia al reporte
                        </p>
                        <button type="submit" 
                                :disabled="!hasFiles"
                                :class="hasFiles ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                                class="inline-flex items-center px-6 py-2.5 text-white font-semibold rounded-lg transition-colors shadow-md disabled:opacity-50">
                            <i class="ph-upload-simple text-lg mr-2"></i>
                            Subir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Asignar Departamentos -->
    <div x-show="isAdminPanelOpen && adminModalMode === 'departments'" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isAdminPanelOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">Asignar Departamentos</h3>
                <button @click="isAdminPanelOpen = false" class="text-gray-400 hover:text-gray-500">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <!-- Departments Form -->
                <div class="space-y-4" x-data="{ searchQuery: '' }">
                    <?php
                    // Get all departments for admin form
                    $all_departments = $conn->query("SELECT * FROM departments ORDER BY name");
                    
                    // Get current assignments for checkbox states
                    $assigned_dept_ids = [];
                    $stmt_curr = $conn->prepare("SELECT department_id FROM complaint_departments WHERE complaint_id = ?");
                    $stmt_curr->bind_param("i", $complaint_id);
                    $stmt_curr->execute();
                    $result = $stmt_curr->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $assigned_dept_ids[] = $row['department_id'];
                    }
                    ?>
                    <form method="POST" class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Selecciona los departamentos responsables:</label>
                            
                            <!-- Search Bar -->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="ph-magnifying-glass text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       x-model="searchQuery"
                                       placeholder="Buscar por nombre, encargado o email..."
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="grid gap-3 max-h-[40vh] overflow-y-auto pr-2">
                                <?php while ($dept = $all_departments->fetch_assoc()): ?>
                                    <label x-show="searchQuery === '' || 
                                                   '<?php echo strtolower(htmlspecialchars($dept['name'])); ?>'.includes(searchQuery.toLowerCase()) || 
                                                   '<?php echo strtolower(htmlspecialchars($dept['manager'])); ?>'.includes(searchQuery.toLowerCase()) || 
                                                   '<?php echo strtolower(htmlspecialchars($dept['email'])); ?>'.includes(searchQuery.toLowerCase())"
                                           class="relative flex items-start py-2.5 px-3 rounded-lg border border-gray-200 bg-white hover:border-blue-200 transition-colors">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" 
                                                   name="departments[]" 
                                                   value="<?php echo $dept['id']; ?>"
                                                   <?php echo in_array($dept['id'], $assigned_dept_ids) ? 'checked' : ''; ?>
                                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($dept['name']); ?></p>
                                            <div class="text-sm text-gray-500 mt-0.5">
                                                <span class="font-medium"><?php echo htmlspecialchars($dept['manager']); ?></span> · 
                                                <a href="mailto:<?php echo htmlspecialchars($dept['email']); ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?php echo htmlspecialchars($dept['email']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <button type="submit" 
                                name="assign_departments" 
                                class="w-full bg-blue-600 text-white font-semibold py-2.5 px-6 rounded-lg hover:bg-blue-700 transition-colors shadow">
                            Guardar Asignaciones
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Cambiar Estado del Reporte -->
    <div x-show="isAdminPanelOpen && adminModalMode === 'status'" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isAdminPanelOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-800">Cambiar Estado del Reporte</h3>
                <button @click="isAdminPanelOpen = false" class="text-gray-400 hover:text-gray-500">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <!-- Status Form -->
                <div class="space-y-4">
                    <!-- Nota informativa -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="ph-info text-blue-600 text-xl flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-semibold text-blue-900 mb-1">Actualización Manual de Estado</h4>
                                <p class="text-sm text-blue-700">
                                    Este apartado permite actualizar manualmente el estado del reporte en caso de que exista alguna discrepancia con el cálculo automático de días hábiles. 
                                    El sistema calcula automáticamente los estados basándose en días hábiles (lunes a viernes), pero puedes ajustarlo manualmente desde aquí si es necesario.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Estado actual del reporte:</label>
                            <select name="status" id="status" class="w-full rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition p-2.5">
                                <option value="unattended" <?php echo (in_array($complaint['status'], ['unattended_ontime', 'unattended_late'])) ? 'selected' : ''; ?>>Sin atender</option>
                                <option value="attended_ontime" <?php echo $complaint['status'] == 'attended_ontime' ? 'selected' : ''; ?>>Atendido (a tiempo)</option>
                                <option value="attended_late" <?php echo $complaint['status'] == 'attended_late' ? 'selected' : ''; ?>>Atendido (a destiempo)</option>
                                <option value="invalid" <?php echo $complaint['status'] == 'invalid' ? 'selected' : ''; ?>>Inválido</option>
                                <option value="duplicate" <?php echo $complaint['status'] == 'duplicate' ? 'selected' : ''; ?>>Duplicado</option>
                            </select>
                            <p class="mt-2 text-xs text-gray-500">
                                <i class="ph-info mr-1"></i>
                                Al seleccionar "Sin atender", el sistema calculará automáticamente si está a tiempo o tarde según los días hábiles transcurridos.
                            </p>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2.5 px-6 rounded-lg hover:bg-blue-700 transition-colors shadow">
                            Actualizar Estado
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Editar Categoría -->
    <div x-show="isAdminPanelOpen && adminModalMode === 'category'" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" 
         style="display: none;">
        <div @click="isAdminPanelOpen = false" class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative bg-white w-full max-w-2xl max-h-[90vh] rounded-xl shadow-2xl flex flex-col">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                <h3 class="text-xl font-bold text-gray-800">Editar Categoría</h3>
                <button @click="isAdminPanelOpen = false" class="text-gray-400 hover:text-gray-500">
                    <i class="ph-x text-2xl"></i>
                </button>
            </div>

            <form method="POST" class="flex flex-col flex-1 min-h-0">
                <div class="p-6 overflow-y-auto flex-1" x-data="{ searchQuery: '' }">
                    <?php
                    // Get all categories for admin form
                    $all_categories = $conn->query("SELECT id, name, description FROM categories ORDER BY name");
                    $current_category_id = $complaint['category_id'] ?? null;
                    
                    // Category info with icons and colors (same as index.php)
                    $category_info = [
                        0  => ['from' => 'from-gray-500',    'to' => 'to-slate-500',   'icon' => 'ph-file-text'],
                        1  => ['from' => 'from-blue-500',    'to' => 'to-cyan-500',    'icon' => 'ph-wifi-high'],
                        2  => ['from' => 'from-indigo-500',  'to' => 'to-purple-500',  'icon' => 'ph-armchair'],
                        3  => ['from' => 'from-emerald-500', 'to' => 'to-teal-500',    'icon' => 'ph-books'],
                        4  => ['from' => 'from-amber-500',   'to' => 'to-orange-500',  'icon' => 'ph-flask'],
                        5  => ['from' => 'from-green-500',   'to' => 'to-emerald-600', 'icon' => 'ph-basketball'],
                        6  => ['from' => 'from-amber-500',   'to' => 'to-orange-600',  'icon' => 'ph-fork-knife'],
                        7  => ['from' => 'from-sky-500',     'to' => 'to-blue-500',    'icon' => 'ph-toilet'],
                        8  => ['from' => 'from-zinc-500',    'to' => 'to-slate-600',   'icon' => 'ph-car'],
                        9  => ['from' => 'from-fuchsia-500', 'to' => 'to-purple-600',  'icon' => 'ph-chalkboard-teacher'],
                        10 => ['from' => 'from-indigo-500',  'to' => 'to-blue-600',    'icon' => 'ph-book-open'],
                        11 => ['from' => 'from-yellow-500',  'to' => 'to-amber-600',   'icon' => 'ph-exam'],
                        12 => ['from' => 'from-blue-500',    'to' => 'to-indigo-600',  'icon' => 'ph-folders'],
                        13 => ['from' => 'from-emerald-500', 'to' => 'to-teal-600',    'icon' => 'ph-handshake'],
                        14 => ['from' => 'from-rose-500',    'to' => 'to-pink-600',    'icon' => 'ph-credit-card'],
                        15 => ['from' => 'from-sky-500',     'to' => 'to-cyan-600',    'icon' => 'ph-user-sound'],
                        16 => ['from' => 'from-violet-500',  'to' => 'to-purple-600',  'icon' => 'ph-megaphone'],
                        17 => ['from' => 'from-red-600',     'to' => 'to-rose-700',    'icon' => 'ph-prohibit'],
                        18 => ['from' => 'from-red-500',     'to' => 'to-orange-600',  'icon' => 'ph-warning'],
                        19 => ['from' => 'from-green-600',   'to' => 'to-emerald-700', 'icon' => 'ph-shield-check'],
                        20 => ['from' => 'from-pink-500',    'to' => 'to-fuchsia-600', 'icon' => 'ph-target'],
                    ];
                    $default_info = ['from' => 'from-gray-500', 'to' => 'to-slate-500', 'icon' => 'ph-file-text'];
                    ?>
                    
                    <!-- Search Input -->
                    <div class="relative mb-4">
                        <input type="text" 
                               x-model="searchQuery" 
                               placeholder="Buscar categoría..." 
                               class="w-full rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition p-3 pl-10">
                        <i class="ph-magnifying-glass absolute left-3 top-3.5 text-gray-400 text-lg"></i>
                    </div>

                    <!-- Categories List (scrollable) -->
                    <div class="space-y-2">
                        <!-- No Category Option -->
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-gray-300 hover:bg-gray-50 cursor-pointer transition">
                            <input type="radio" name="category_id" value="" <?php echo $current_category_id == null ? 'checked' : ''; ?> class="w-4 h-4">
                            <div class="flex items-center gap-2 flex-1">
                                <div class="w-10 h-10 bg-gradient-to-br from-gray-500 to-slate-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="ph-file-text ph-fill text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-700">Sin categoría</p>
                                    <p class="text-xs text-gray-500">No asignar categoría</p>
                                </div>
                            </div>
                        </label>

                        <?php 
                        $all_categories->data_seek(0);
                        while ($cat = $all_categories->fetch_assoc()): 
                            $cat_id = $cat['id'];
                            $info = $category_info[$cat_id] ?? $default_info;
                            $cat_name = htmlspecialchars($cat['name']);
                            $cat_desc = htmlspecialchars($cat['description'] ?? '');
                        ?>
                            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-gray-300 hover:bg-gray-50 cursor-pointer transition"
                                   x-show="searchQuery === '' || '<?php echo $cat_name; ?>'.toLowerCase().includes(searchQuery.toLowerCase()) || '<?php echo $cat_desc; ?>'.toLowerCase().includes(searchQuery.toLowerCase())">
                                <input type="radio" name="category_id" value="<?php echo $cat_id; ?>" <?php echo $current_category_id == $cat_id ? 'checked' : ''; ?> class="w-4 h-4">
                                <div class="flex items-center gap-2 flex-1">
                                    <div class="w-10 h-10 bg-gradient-to-br <?php echo $info['from'] . ' ' . $info['to']; ?> rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="<?php echo $info['icon']; ?> ph-fill text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-700"><?php echo $cat_name; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $cat_desc; ?></p>
                                    </div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Sticky Button at Bottom -->
                <div class="sticky bottom-0 bg-white border-t border-gray-200 p-6 flex-shrink-0 rounded-b-xl">
                    <button type="submit" 
                            name="update_category"
                            class="w-full bg-blue-600 text-white font-semibold py-2.5 px-6 rounded-lg hover:bg-blue-700 transition-colors shadow">
                        Actualizar Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>

    <main class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            
            <!-- Mensajes de éxito/error globales -->
            <?php if ($success_message): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg flex items-center gap-3 shadow-md">
                    <i class="ph-check-circle text-2xl"></i>
                    <span class="font-medium"><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg flex items-center gap-3 shadow-md">
                    <i class="ph-warning-circle text-2xl"></i>
                    <span class="font-medium"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            

            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="p-8 md:p-12">
                    <div class="border-b border-gray-200 pb-8 mb-8">
                        <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                            <div>
                                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Detalles del Reporte</h1>
                                <p class="text-gray-500 mt-1 text-lg">
                                    Folio #<?php echo $complaint['folio'] ?? str_pad($complaint['id'], 6, '0', STR_PAD_LEFT); ?>
                                </p>
                            </div>
                            <?php $statusInfo = getStatusInfo($complaint['status']); ?>
                            <div class="inline-flex items-center gap-x-2 py-2 px-4 rounded-full text-base font-medium <?php echo $statusInfo['class']; ?> ring-1 ring-inset">
                                <i class="<?php echo $statusInfo['icon']; ?> text-lg"></i>
                                <?php echo $statusInfo['text']; ?>
                                <?php if (isAdmin()): ?>
                                    <button @click="isAdminPanelOpen = true; adminModalMode = 'status';"
                                            class="ml-1 transition-all hover:scale-110" title="Editar estado">
                                        <i class="ph-pencil-simple text-lg"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (isAdmin()): ?>
                        <div class="mb-8 flex flex-col gap-4" 
                             x-data="{ 
                                isLoading: false, 
                                result: <?php echo $gemini_result_data ? htmlspecialchars(json_encode($gemini_result_data)) : 'null'; ?>, 
                                error: null,
                                analyze() {
                                    this.isLoading = true;
                                    this.error = null;
                                    this.result = null;
                                    
                                    fetch('ajax_gemini_analyze.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                        },
                                        body: JSON.stringify({ complaint_id: <?php echo $complaint_id; ?> })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            this.result = data.data;
                                        } else {
                                            this.error = data.error || 'Ocurrió un error desconocido.';
                                        }
                                    })
                                    .catch(err => {
                                        this.error = 'Error de conexión: ' + err.message;
                                    })
                                    .finally(() => {
                                        this.isLoading = false;
                                    });
                                }
                             }">
                            
                            <!-- Analyze Button -->
                            <div class="inline-flex self-start">
                                <button type="button"
                                        @click="analyze()"
                                        :disabled="isLoading"
                                        class="inline-flex items-center gap-2 bg-purple-600 text-white font-semibold py-2.5 px-6 rounded-lg hover:bg-purple-700 transition-colors shadow disabled:opacity-70 disabled:cursor-not-allowed">
                                    <template x-if="!isLoading">
                                        <div class="flex items-center gap-2">
                                            <i class="ph-sparkle text-lg"></i>
                                            <span>Analizar con Gemini AI</span>
                                        </div>
                                    </template>
                                    <template x-if="isLoading">
                                        <div class="flex items-center gap-2">
                                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Analizando...</span>
                                        </div>
                                    </template>
                                </button>
                            </div>

                            <!-- Error Message -->
                            <div x-show="error" style="display: none;" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3">
                                <i class="ph-warning-circle text-xl"></i>
                                <span x-text="error"></span>
                            </div>

                            <!-- Results Container -->
                            <div x-show="result" style="display: none;" class="bg-purple-50 border border-purple-200 rounded-xl p-6 transition-all duration-500 ease-in-out">
                                <div class="flex items-start gap-3 mb-4">
                                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                                        <i class="ph-sparkle text-2xl"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold text-purple-900">Sugerencias automáticas de Gemini</h2>
                                        <p class="text-sm text-purple-700">Revisa la categorización propuesta, departamentos sugeridos y resumen para agilizar la gestión del reporte.</p>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <!-- Tipo -->
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Tipo</h3>
                                        <p class="mt-1 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold"
                                           :class="{
                                                'bg-red-100 text-red-700': result?.tipo === 'queja',
                                                'bg-blue-100 text-blue-700': result?.tipo === 'sugerencia',
                                                'bg-green-100 text-green-700': result?.tipo === 'felicitacion',
                                                'bg-gray-100 text-gray-700': !['queja', 'sugerencia', 'felicitacion'].includes(result?.tipo)
                                           }">
                                            <i class="ph-seal-check"></i>
                                            <span x-text="result?.tipo ? result.tipo.charAt(0).toUpperCase() + result.tipo.slice(1) : 'Sin definir'"></span>
                                        </p>
                                    </div>

                                    <!-- Categoría Sugerida -->
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Categoría Sugerida</h3>
                                        <p class="mt-1 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold bg-purple-100 text-purple-700">
                                            <i class="ph-tag"></i>
                                            <span x-text="result?.categoria_nombre || 'ID: ' + (result?.categoria_id || 'N/A')"></span>
                                        </p>
                                    </div>

                                    <!-- Departamentos Sugeridos -->
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Departamentos sugeridos</h3>
                                        <template x-if="result?.lista_departamentos && result.lista_departamentos.length > 0">
                                            <div class="mt-2 grid gap-3 md:grid-cols-2">
                                                <template x-for="dept in result.lista_departamentos" :key="dept.id">
                                                    <div class="rounded-lg border border-purple-200 bg-white p-4">
                                                        <p class="font-semibold text-gray-900 flex items-center gap-2">
                                                            <i class="ph-buildings text-purple-500"></i>
                                                            <span x-text="dept.nombre"></span>
                                                        </p>
                                                        <p class="mt-2 text-sm text-gray-600" x-text="dept.motivo"></p>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!result?.lista_departamentos || result.lista_departamentos.length === 0">
                                            <p class="mt-2 text-sm text-gray-500 italic">No hay departamentos sugeridos para este reporte.</p>
                                        </template>
                                    </div>

                                    <!-- Resumen -->
                                    <template x-if="result?.resumen">
                                        <div>
                                            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Resumen generado</h3>
                                            <p class="mt-2 text-gray-700 leading-relaxed bg-white border border-purple-200 rounded-lg p-4" x-text="result.resumen"></p>
                                        </div>
                                    </template>
                                </div>

                                <!-- Apply Suggestions Form -->
                                <form method="POST" class="mt-6">
                                    <input type="hidden" name="apply_gemini_suggestions" value="1">
                                    <input type="hidden" name="gemini_categoria_id" :value="result?.categoria_id || 0">
                                    <input type="hidden" name="gemini_departamentos" :value="JSON.stringify(result?.lista_departamentos || [])">
                                    
                                    <div class="flex flex-col gap-3">
                                        <button type="submit" 
                                                class="inline-flex items-center justify-center gap-2 bg-green-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-700 transition-colors shadow">
                                            <i class="ph-check-circle text-lg"></i>
                                            Aplicar Sugerencias
                                        </button>
                                        <p class="text-xs text-gray-600 bg-green-50 border border-green-200 rounded-lg p-3">
                                            <i class="ph-info text-green-600 mr-2"></i>
                                            Se actualizarán la categoría y departamentos asignados. Se enviarán correos de notificación a los departamentos.
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-8 mb-8">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-calendar text-2xl text-gray-500"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-500 text-sm">Fecha de Envío</h3>
                                <p class="text-lg font-bold text-gray-800">
                                    <?php echo date('d/m/Y \a \l\a\s H:i', strtotime($complaint['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-user-circle text-2xl text-gray-500"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-500 text-sm">Enviado Por</h3>
                                <?php if ($complaint['is_anonymous']): ?>
                                    <?php if ($complaint['user_id'] == $_SESSION['user_id']): ?>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($complaint['user_name']); ?></p>
                                                <span class="inline-flex items-center gap-x-1.5 py-1 px-2 rounded-md text-xs font-medium bg-purple-100 text-purple-700">
                                                    <i class="ph-user-circle-gear"></i>
                                                    Enviado de forma anónima
                                                </span>
                                            </div>
                                            <p class="text-gray-500"><?php echo htmlspecialchars($complaint['user_email']); ?></p>
                                            <p class="text-sm text-purple-600">
                                                <i class="ph-info"></i>
                                                Solo tú puedes ver esta información
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-lg font-bold text-gray-800">Reporte Anónimo</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($complaint['user_name']); ?></p>
                                    <p class="text-gray-500"><?php echo htmlspecialchars($complaint['user_email']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-tag text-2xl text-gray-500"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-semibold text-gray-500 text-sm">Categoría</h3>
                                    <?php if (isAdmin()): ?>
                                        <button type="button"
                                                @click="isAdminPanelOpen = true; adminModalMode = 'category'; activeTab = 'category';"
                                                class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800">
                                            <i class="ph-pencil-simple text-base"></i>
                                            Editar
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <p class="text-lg font-bold text-gray-800 mt-2">
                                    <?php echo $complaint['category_name'] ? htmlspecialchars($complaint['category_name']) : 'Sin categoría'; ?>
                                </p>
                            </div>
                        </div>

                        <?php 
                        // Get assigned departments
                        if (!isset($assigned_departments)) {
                            $stmt_dept = $conn->prepare("
                                SELECT d.*, cd.assigned_at 
                                FROM departments d 
                                JOIN complaint_departments cd ON d.id = cd.department_id 
                                WHERE cd.complaint_id = ?
                                ORDER BY cd.assigned_at DESC
                            ");
                            $stmt_dept->bind_param("i", $complaint_id);
                            $stmt_dept->execute();
                            $assigned_departments = $stmt_dept->get_result();
                        } else {
                            $assigned_departments->data_seek(0);
                        }
                        ?>
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ph-buildings text-2xl text-gray-500"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-semibold text-gray-500 text-sm">Departamentos Asignados</h3>
                                    <?php if (isAdmin()): ?>
                                        <button type="button"
                                                @click="isAdminPanelOpen = true; adminModalMode = 'departments'; activeTab = 'departments';"
                                                class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800">
                                            <i class="ph-buildings text-base"></i>
                                            Asignar
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($assigned_departments->num_rows == 0): ?>
                                    <div class="mt-2 bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                                        <div class="flex items-center gap-3">
                                            <i class="ph-warning-circle text-yellow-600 text-xl"></i>
                                            <div>
                                                <p class="font-medium text-yellow-800">Reporte sin asignar</p>
                                                <?php if (isAdmin()): ?>
                                                    <p class="text-yellow-700 text-sm mt-0.5">
                                                        Usa el botón "Asignar Departamentos" para asignar departamentos responsables.
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-yellow-700 text-sm mt-0.5">
                                                        El reporte aún no ha sido asignado a ningún departamento.
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-2 space-y-3">
                                        <?php while ($dept = $assigned_departments->fetch_assoc()): ?>
                                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($dept['name']); ?></p>
                                                        <p class="text-gray-600 mt-1">
                                                            <span class="font-medium"><?php echo htmlspecialchars($dept['manager']); ?></span><br>
                                                            <a href="mailto:<?php echo htmlspecialchars($dept['email']); ?>" class="text-blue-600 hover:text-blue-800">
                                                                <?php echo htmlspecialchars($dept['email']); ?>
                                                            </a>
                                                        </p>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        Asignado: <?php echo date('d/m/Y H:i', strtotime($dept['assigned_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Descripción del Reporte</h2>
                        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                            <p class="text-gray-700 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($complaint['description']); ?></p>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Evidencia Adjunta</h2>
                        <?php if (empty($attachments)): ?>
                            <div class="bg-gray-50 rounded-lg p-6 text-center border-2 border-dashed border-gray-200">
                                <p class="text-gray-500">No se adjuntó ninguna evidencia para este reporte.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="border border-gray-200 rounded-lg overflow-hidden group transition-shadow hover:shadow-md">
                                        <?php if (str_contains($attachment['file_type'], 'image/')): ?>
                                            <button @click="isModalOpen = true; modalImageUrl = '<?php echo htmlspecialchars($attachment['file_path']); ?>'" class="w-full h-40 block">
                                                <img src="<?php echo htmlspecialchars($attachment['file_path']); ?>" alt="<?php echo htmlspecialchars($attachment['file_name']); ?>" class="w-full h-full object-cover">
                                            </button>
                                        <?php else: ?>
                                            <div class="w-full h-40 bg-gray-100 flex items-center justify-center">
                                                <i class="<?php echo getFileIcon($attachment['file_type']); ?> text-6xl text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="p-4 bg-white">
                                            <p class="text-sm font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($attachment['file_name']); ?></p>
                                            <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" download
                                               class="inline-flex items-center mt-2 text-sm text-blue-600 hover:text-blue-800 font-semibold group/link">
                                                Descargar <i class="ph-download-simple text-lg ml-1 group-hover/link:translate-y-0.5 transition-transform"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Response Evidence Section -->
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="ph-check-circle text-green-600"></i>
                            Evidencia de Respuesta
                        </h2>
                        
                        <?php if (isAdmin() && empty($response_evidence)): ?>
                            <!-- Admin: Inline Upload (solo cuando NO hay evidencia) -->
                            <div class="mb-6" x-data="{ 
                                isDragging: false, 
                                hasFiles: false,
                                fileCount: 0,
                                previews: [],
                                updateFileCount() {
                                    const input = document.getElementById('response_evidence_input');
                                    this.fileCount = input.files.length;
                                    this.hasFiles = this.fileCount > 0;
                                    this.generatePreviews(input.files);
                                },
                                generatePreviews(files) {
                                    this.previews = [];
                                    for (let i = 0; i < files.length; i++) {
                                        const file = files[i];
                                        if (file.type.startsWith('image/')) {
                                            const reader = new FileReader();
                                            reader.onload = (e) => {
                                                this.previews.push(e.target.result);
                                            };
                                            reader.readAsDataURL(file);
                                        }
                                    }
                                }
                            }">
                                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                    <div @drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; updateFileCount()"
                                         @dragover.prevent="isDragging = true"
                                         @dragleave.prevent="isDragging = false"
                                         @click="$refs.fileInput.click()"
                                         :class="{'border-blue-500 bg-blue-50': isDragging, 'border-gray-300 bg-white': !isDragging}"
                                         class="relative border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-all hover:border-blue-400 hover:bg-blue-50">
                                        
                                        <input type="file" 
                                               id="response_evidence_input"
                                               name="response_evidence[]" 
                                               multiple 
                                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                                               @change="updateFileCount()"
                                               x-ref="fileInput"
                                               class="hidden">
                                        
                                        <div class="flex flex-col items-center gap-3">
                                            <div x-show="previews.length === 0" class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="ph-upload-simple text-3xl text-blue-600"></i>
                                            </div>
                                            
                                            <div x-show="previews.length > 0" class="grid grid-cols-3 gap-2 w-full max-w-md">
                                                <template x-for="(preview, index) in previews" :key="index">
                                                    <div class="relative aspect-square rounded-lg overflow-hidden border-2 border-blue-200">
                                                        <img :src="preview" class="w-full h-full object-cover">
                                                    </div>
                                                </template>
                                            </div>
                                            
                                            <div>
                                                <p class="text-base font-semibold text-gray-700 mb-1">
                                                    <span x-show="!isDragging && !hasFiles">Arrastra archivos aquí o haz clic para seleccionar</span>
                                                    <span x-show="isDragging" class="text-blue-600">Suelta los archivos aquí</span>
                                                    <span x-show="hasFiles && !isDragging" class="text-green-600">
                                                        <i class="ph-check-circle mr-1"></i>
                                                        <span x-text="fileCount"></span> archivo<span x-show="fileCount > 1">s</span> listo<span x-show="fileCount > 1">s</span> para subir
                                                    </span>
                                                </p>
                                                <p class="text-sm text-gray-500">Imágenes, PDF, Word, Excel</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg p-3">
                                        <p class="text-sm text-blue-700 flex items-center gap-2">
                                            <i class="ph-info"></i>
                                            La primera evidencia marcará el reporte como atendido
                                        </p>
                                        <button type="submit" 
                                                :disabled="!hasFiles"
                                                :class="hasFiles ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                                                class="inline-flex items-center px-6 py-2.5 text-white font-semibold rounded-lg transition-colors shadow-md disabled:opacity-50">
                                            <i class="ph-upload-simple text-lg mr-2"></i>
                                            Subir
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Display Response Evidence -->
                        <?php if (empty($response_evidence)): ?>
                            <div class="bg-gray-50 rounded-lg p-6 text-center border-2 border-dashed border-gray-200">
                                <i class="ph-file-dashed text-5xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500">Aún no se ha adjuntado evidencia de respuesta para este reporte.</p>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-lg border border-gray-200 p-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Add More Evidence Card (Solo para admins) -->
                                    <?php if (isAdmin()): ?>
                                        <button type="button"
                                                @click="isUploadModalOpen = true"
                                                class="border-2 border-dashed border-blue-300 rounded-lg overflow-hidden group transition-all hover:shadow-md hover:border-blue-500 hover:bg-blue-50 cursor-pointer">
                                            <div class="w-full h-40 flex flex-col items-center justify-center gap-3 bg-gradient-to-br from-blue-50 to-indigo-50 group-hover:from-blue-100 group-hover:to-indigo-100 transition-all">
                                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
                                                    <i class="ph-plus text-3xl text-blue-600"></i>
                                                </div>
                                                <p class="text-sm font-semibold text-blue-700">Agregar Más Evidencia</p>
                                            </div>
                                            <div class="p-4 bg-white border-t border-blue-100">
                                                <p class="text-xs text-center text-gray-600">Haz clic para subir archivos adicionales</p>
                                            </div>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($response_evidence as $evidence): ?>
                                        <?php 
                                        // Verificar si es imagen
                                        $is_image = str_contains($evidence['file_type'], 'image') || 
                                                   preg_match('/\.(jpg|jpeg|png|gif|bmp|webp|svg)$/i', $evidence['file_name']);
                                        ?>
                                        <div class="border border-gray-200 rounded-lg overflow-hidden group transition-shadow hover:shadow-md">
                                            <?php if ($is_image): ?>
                                                <button type="button"
                                                        @click.prevent="console.log('Click en imagen:', '<?php echo addslashes($evidence['file_path']); ?>'); isModalOpen = true; modalImageUrl = '<?php echo addslashes($evidence['file_path']); ?>'; console.log('Modal abierto:', isModalOpen, 'URL:', modalImageUrl);" 
                                                        class="w-full h-40 block relative overflow-hidden group/img bg-gray-100 cursor-pointer">
                                                    <img src="<?php echo htmlspecialchars($evidence['file_path']); ?>" 
                                                         alt="<?php echo htmlspecialchars($evidence['file_name']); ?>" 
                                                         class="w-full h-full object-cover transition-transform group-hover/img:scale-110 pointer-events-none"
                                                         loading="lazy"
                                                         onerror="console.error('Error cargando imagen:', this.src); this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-red-50\'><div class=\'text-center\'><i class=\'ph-image-broken text-4xl text-red-400 mb-2\'></i><p class=\'text-xs text-red-600\'>Error al cargar imagen</p></div></div>'">
                                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover/img:bg-opacity-30 transition-all flex items-center justify-center pointer-events-none">
                                                        <i class="ph-magnifying-glass-plus text-white text-3xl opacity-0 group-hover/img:opacity-100 transition-opacity drop-shadow-lg"></i>
                                                    </div>
                                                </button>
                                            <?php else: ?>
                                                <div class="w-full h-40 bg-gradient-to-br from-green-50 to-blue-50 flex items-center justify-center">
                                                    <i class="<?php echo getFileIcon($evidence['file_type']); ?> text-6xl text-green-600"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="p-4 bg-white">
                                                <p class="text-sm font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($evidence['file_name']); ?></p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Subido por: <?php echo htmlspecialchars($evidence['uploaded_by_name']); ?><br>
                                                    <?php echo date('d/m/Y H:i', strtotime($evidence['uploaded_at'])); ?>
                                                </p>
                                                <a href="<?php echo htmlspecialchars($evidence['file_path']); ?>" target="_blank" download
                                                   class="inline-flex items-center mt-2 text-sm text-green-600 hover:text-green-800 font-semibold group/link">
                                                    Descargar <i class="ph-download-simple text-lg ml-1 group-hover/link:translate-y-0.5 transition-transform"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'components/footer.php'; ?>
