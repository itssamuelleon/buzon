<?php 
// Cargar configuración ANTES de cualquier salida
require_once 'config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description']);
    $is_anonymous = isset($_POST['anonymous']) ? 1 : 0;
    $user_id = $_SESSION['user_id']; // Siempre guardar el ID del usuario
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

    if (empty($description)) {
        $_SESSION['error_message'] = 'Por favor, completa la descripción del reporte.';
        header('Location: submit_complaint.php');
        exit;
    } elseif (empty($category_id)) {
        $_SESSION['error_message'] = 'Por favor, selecciona una categoría para el reporte.';
        header('Location: submit_complaint.php');
        exit;
    } else {
        // Verificar que la categoría existe en la base de datos
        $verify_category = $conn->prepare("SELECT id FROM categories WHERE id = ?");
        $verify_category->bind_param("i", $category_id);
        $verify_category->execute();
        if ($verify_category->get_result()->num_rows === 0) {
            $_SESSION['error_message'] = 'La categoría seleccionada no es válida. Por favor, selecciona una categoría válida.';
            header('Location: submit_complaint.php');
            exit;
        }
        $error_details = '';
        $conn->begin_transaction();
        try {
            // Verificar permisos de la carpeta uploads
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('No se pudo crear el directorio de uploads');
                }
            } elseif (!is_writable($upload_dir)) {
                throw new Exception('El directorio de uploads no tiene permisos de escritura');
            }

            $stmt = $conn->prepare("INSERT INTO complaints (user_id, description, is_anonymous, category_id) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Error al preparar la consulta: ' . $conn->error);
            }
            $stmt->bind_param("isii", $user_id, $description, $is_anonymous, $category_id);
            if (!$stmt->execute()) {
                throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
            }
            $complaint_id = $stmt->insert_id;

            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_OK) {
                        $upload_errors = array(
                            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
                            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el formulario',
                            UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente',
                            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
                            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
                        );
                        $error_code = $_FILES['attachments']['error'][$key];
                        throw new Exception('Error al subir archivo: ' . 
                            ($upload_errors[$error_code] ?? 'Error desconocido'));
                    }

                    $file_name = basename($_FILES['attachments']['name'][$key]);
                    $file_type = $_FILES['attachments']['type'][$key];
                    
                    // Validar tipo de archivo
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception('Tipo de archivo no permitido: ' . $file_type);
                    }

                    // Validar tamaño
                    $max_size = 5 * 1024 * 1024; // 5MB
                    if ($_FILES['attachments']['size'][$key] > $max_size) {
                        throw new Exception('El archivo excede el tamaño máximo permitido de 5MB');
                    }

                    $file_path = $upload_dir . uniqid('file_', true) . '_' . $file_name;
                    
                    if (!move_uploaded_file($tmp_name, $file_path)) {
                        throw new Exception('Error al mover el archivo subido');
                    }

                    $stmt_att = $conn->prepare("INSERT INTO attachments (complaint_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)");
                    if (!$stmt_att) {
                        throw new Exception('Error al preparar la consulta de adjuntos: ' . $conn->error);
                    }
                    $stmt_att->bind_param("isss", $complaint_id, $file_name, $file_path, $file_type);
                    if (!$stmt_att->execute()) {
                        throw new Exception('Error al guardar el archivo en la base de datos: ' . $stmt_att->error);
                    }
                }
            }
            
            $conn->commit();
            
            // Enviar notificación al departamento de Buzón si está activado
            require_once 'config/email_config.php';
            if (shouldNotifyBuzon()) {
                require_once 'send_email.php';
                
                // Obtener información del departamento de Buzón
                $buzon_dept_query = $conn->prepare("SELECT id, name, manager, email FROM departments WHERE name LIKE '%Buzón%' OR name LIKE '%Quejas%' LIMIT 1");
                $buzon_dept_query->execute();
                $buzon_dept_result = $buzon_dept_query->get_result();
                
                if ($buzon_dept = $buzon_dept_result->fetch_assoc()) {
                    // Obtener información del reporte recién creado
                    $complaint_query = $conn->prepare("SELECT c.id, c.folio, c.description, c.created_at, cat.name as category_name 
                                                       FROM complaints c 
                                                       LEFT JOIN categories cat ON c.category_id = cat.id 
                                                       WHERE c.id = ?");
                    $complaint_query->bind_param("i", $complaint_id);
                    $complaint_query->execute();
                    $complaint_data = $complaint_query->get_result()->fetch_assoc();
                    
                    // Enviar notificación
                    sendDepartmentNotification($buzon_dept, $complaint_data);
                }
            }
            
            $_SESSION['success_message'] = '¡Tu reporte ha sido enviado con éxito!';
            header('Location: submit_complaint.php?submitted=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error en submit_complaint.php: " . $e->getMessage());
            $_SESSION['error_message'] = 'Ocurrió un error al enviar tu reporte: ' . $e->getMessage();
            if (!headers_sent()) {
                header('Location: submit_complaint.php');
            } else {
                echo '<script>window.location.href = "submit_complaint.php";</script>';
            }
            exit;
        }
    }
}

// Recuperar mensajes de sesión
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$submitted = isset($_GET['submitted']) && $_GET['submitted'] == '1';

// Limpiar mensajes de sesión
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch categories for the form
$categories_query = $conn->query("SELECT id, name, description FROM categories ORDER BY name");
$categories = $categories_query->fetch_all(MYSQLI_ASSOC);

// AHORA sí incluir el header
$page_title = 'Enviar Reporte - ITSCC Buzón';
include 'components/header.php';

?>

<!-- Phosphor Icons CDN -->
<script src="https://unpkg.com/@phosphor-icons/web"></script>

<style>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

.gradient-bg {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card-shadow {
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
</style>

<div class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen py-12">
    <main class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            
            <!-- Header con gradiente -->
            <div class="text-center mb-10 animate-fade-in-up">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-lg mb-4">
                    <i class="ph-fill ph-file-text text-white text-4xl"></i>
                </div>
                <h1 class="text-4xl md:text-5xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent mb-3">
                    Enviar un Reporte
                </h1>
                <p class="text-gray-600 text-lg">Tu voz es importante. Ayúdanos a crear un mejor ambiente.</p>
            </div>

            <div class="bg-white rounded-3xl card-shadow overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
                <div class="p-8 md:p-12">

                    <?php if ($error): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-5 mb-8 rounded-r-xl flex items-start animate-fade-in-up" role="alert">
                            <i class="ph-fill ph-warning-circle text-red-500 text-2xl mr-3 flex-shrink-0"></i>
                            <div>
                                <p class="font-bold text-red-800">Error</p>
                                <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($submitted): ?>
                        <div class="text-center p-10 bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl border-2 border-green-200 animate-fade-in-up">
                            <div class="inline-flex items-center justify-center w-24 h-24 bg-green-500 rounded-full mb-6 animate-pulse">
                                <i class="ph-fill ph-check-circle text-white text-5xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold text-green-800 mb-3">¡Reporte Enviado con Éxito!</h2>
                            <p class="text-gray-700 text-lg mb-8 max-w-md mx-auto">
                                Gracias por tu contribución. Hemos recibido tu reporte y lo revisaremos a la brevedad.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                <a href="my_complaints.php" class="inline-flex items-center justify-center bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-4 px-8 rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                                    <i class="ph-bold ph-list-bullets mr-2 text-xl"></i>
                                    Ver Mis Reportes
                                </a>
                                <a href="submit_complaint.php" class="inline-flex items-center justify-center bg-white text-blue-600 font-semibold py-4 px-8 rounded-xl border-2 border-blue-200 hover:border-blue-300 hover:bg-blue-50 transition-all duration-300">
                                    <i class="ph-bold ph-plus-circle mr-2 text-xl"></i>
                                    Enviar Otro Reporte
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" class="space-y-8">

                            <!-- Categoría -->
                            <div class="space-y-2">
                                <label for="category_id" class="flex items-center text-lg font-semibold text-gray-800">
                                    <i class="ph-bold ph-tag text-blue-600 mr-2 text-xl"></i>
                                    Categoría del Reporte
                                </label>
                                <select id="category_id" name="category_id" required
                                    class="mt-1 block w-full text-base rounded-xl border-2 border-gray-200 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 py-3 px-4"
                                    oninvalid="this.setCustomValidity('Por favor, selecciona una categoría para el reporte')"
                                    oninput="this.setCustomValidity('')">
                                    <option value="">Selecciona una categoría...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['id']); ?>" title="<?php echo htmlspecialchars($category['description']); ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-sm text-gray-500 flex items-center mt-2">
                                    <i class="ph ph-info mr-1"></i>
                                    Selecciona la categoría que mejor describa tu reporte
                                </p>
                            </div>

                            <!-- Descripción -->
                            <div class="space-y-2">
                                <label for="description" class="flex items-center text-lg font-semibold text-gray-800">
                                    <i class="ph-bold ph-note-pencil text-blue-600 mr-2 text-xl"></i>
                                    Descripción del Reporte
                                </label>
                                <textarea id="description" name="description" rows="8" required
                                    class="mt-1 block w-full text-base rounded-xl border-2 border-gray-200 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 py-3 px-4"
                                    placeholder="Describe detalladamente la situación. Incluye fechas, personas involucradas y cualquier otro dato relevante..."></textarea>
                                <p class="text-sm text-gray-500 flex items-center mt-2">
                                    <i class="ph ph-lightbulb mr-1"></i>
                                    Sé lo más específico posible para ayudarnos a entender mejor la situación
                                </p>
                            </div>

                            <!-- Adjuntar archivos -->
                            <div x-data="fileUpload()" class="space-y-2">
                                <label class="flex items-center text-lg font-semibold text-gray-800">
                                    <i class="ph-bold ph-paperclip text-blue-600 mr-2 text-xl"></i>
                                    Adjuntar Evidencia (Opcional)
                                </label>
                                <div 
                                    class="mt-2 relative flex justify-center px-6 pt-8 pb-8 border-3 border-gray-300 border-dashed rounded-2xl transition-all duration-300 hover:border-blue-400 hover:bg-blue-50/30 cursor-pointer"
                                    :class="{ 'border-blue-500 bg-blue-50 shadow-lg': isDragging }"
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop.prevent="handleDrop($event)">
                                    
                                    <div class="space-y-3 text-center">
                                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-100 to-purple-100 rounded-2xl">
                                            <i class="ph ph-upload-simple text-blue-600 text-3xl"></i>
                                        </div>
                                        <div class="flex flex-col space-y-2">
                                            <label class="relative cursor-pointer group">
                                                <span class="text-base font-semibold text-blue-600 group-hover:text-blue-700 transition-colors">
                                                    Haz clic para seleccionar archivos
                                                </span>
                                                <input 
                                                    type="file" 
                                                    name="attachments[]" 
                                                    id="attachments" 
                                                    multiple 
                                                    class="sr-only"
                                                    @change="handleFileSelect($event)">
                                            </label>
                                            <p class="text-gray-600">o arrastra y suelta aquí</p>
                                        </div>
                                        <div class="flex items-center justify-center gap-2 text-xs text-gray-500 pt-2">
                                            <i class="ph ph-image"></i>
                                            <i class="ph ph-file-pdf"></i>
                                            <i class="ph ph-file-doc"></i>
                                            <span>Imágenes, PDF, DOCX (máx. 5MB)</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview de archivos -->
                                <div class="mt-4 space-y-2" x-show="files.length > 0" x-transition>
                                    <p class="text-sm font-semibold text-gray-700 flex items-center">
                                        <i class="ph-bold ph-files mr-1"></i>
                                        Archivos seleccionados (<span x-text="files.length"></span>)
                                    </p>
                                    <template x-for="(file, index) in files" :key="index">
                                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl border border-blue-100 hover:shadow-md transition-all duration-200">
                                            <div class="flex items-center space-x-3 flex-1 min-w-0">
                                                <div class="flex-shrink-0">
                                                    <i class="ph-fill ph-file text-blue-600 text-2xl"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-800 truncate" x-text="file.name"></p>
                                                    <p class="text-xs text-gray-500" x-text="formatFileSize(file.size)"></p>
                                                </div>
                                            </div>
                                            <button type="button" @click="removeFile(index)" 
                                                    class="flex-shrink-0 ml-3 text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-all duration-200">
                                                <i class="ph-bold ph-x-circle text-xl"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Checkbox anónimo -->
                            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400 p-5 rounded-r-xl">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-0.5">
                                        <input type="checkbox" id="anonymous" name="anonymous" 
                                            class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer">
                                    </div>
                                    <div class="ml-4">
                                        <label for="anonymous" class="flex items-center text-base font-semibold text-amber-900 cursor-pointer">
                                            <i class="ph-bold ph-user-circle-minus mr-2 text-xl"></i>
                                            Enviar de forma anónima
                                        </label>
                                        <p class="text-sm text-amber-800 mt-1">
                                            Si marcas esta opción, tu identidad no será revelada a los administradores, pero podrás ver y dar seguimiento a tu reporte desde "Mis Reportes".
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Botón de envío -->
                            <div class="pt-6">
                                <button type="submit"
                                    class="w-full flex justify-center items-center bg-gradient-to-r from-blue-600 to-purple-600 text-white text-lg font-bold py-5 px-6 rounded-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-blue-300 shadow-xl transform hover:-translate-y-1 hover:shadow-2xl transition-all duration-300 ease-out group">
                                    <i class="ph-bold ph-paper-plane-tilt mr-3 text-2xl group-hover:translate-x-1 transition-transform"></i>
                                    Enviar Mi Reporte
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer info -->
            <div class="text-center mt-8 animate-fade-in-up" style="animation-delay: 0.4s;">
                <p class="text-sm text-gray-600 flex items-center justify-center">
                    <i class="ph-fill ph-shield-check text-blue-600 mr-2 text-lg"></i>
                    Tu privacidad es importante. Toda la información será tratada de forma confidencial.
                </p>
            </div>
        </div>
    </main>
</div>

<script>
function fileUpload() {
    return {
        isDragging: false,
        files: [],
        
        handleDrop(e) {
            this.isDragging = false;
            this.addFiles(e.dataTransfer.files);
        },
        
        handleFileSelect(e) {
            this.addFiles(e.target.files);
        },
        
        addFiles(fileList) {
            const validFiles = Array.from(fileList).filter(file => {
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 
                                  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!validTypes.includes(file.type)) {
                    alert('❌ Tipo de archivo no permitido: ' + file.name);
                    return false;
                }
                
                if (file.size > maxSize) {
                    alert('❌ Archivo demasiado grande: ' + file.name + '\nEl tamaño máximo es 5MB');
                    return false;
                }
                
                return true;
            });
            
            this.files = [...this.files, ...validFiles];
            
            const dataTransfer = new DataTransfer();
            this.files.forEach(file => dataTransfer.items.add(file));
            document.getElementById('attachments').files = dataTransfer.files;
        },
        
        removeFile(index) {
            this.files.splice(index, 1);
            
            const dataTransfer = new DataTransfer();
            this.files.forEach(file => dataTransfer.items.add(file));
            document.getElementById('attachments').files = dataTransfer.files;
        },
        
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    }
}
</script>

<?php include 'components/footer.php'; ?>