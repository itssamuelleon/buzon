<?php
// Cargar configuración ANTES de cualquier salida
require_once 'config.php';
require_once 'config/email_config.php';

// Solo admins pueden acceder
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_settings'])) {
    $password = $_POST['admin_password'] ?? '';
    $test_mode = (isset($_POST['test_mode']) && $_POST['test_mode'] === '1') ? '1' : '0';
    $test_email = trim($_POST['test_email'] ?? '');
    $notify_buzon = (isset($_POST['notify_buzon_on_new_report']) && $_POST['notify_buzon_on_new_report'] === '1') ? '1' : '0';
    
    // Verificar contraseña del admin actual
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Validar email de pruebas
            if (!empty($test_email) && !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = 'El correo electrónico de pruebas no es válido.';
                header('Location: admin_settings.php');
                exit;
            }
            
            // Contraseña correcta, actualizar configuración
            $stmt_update = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value, updated_by) VALUES ('test_mode', ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
            $stmt_update->bind_param("sisi", $test_mode, $_SESSION['user_id'], $test_mode, $_SESSION['user_id']);
            $stmt_update->execute();
            
            // Actualizar correo de pruebas si se proporcionó
            if (!empty($test_email)) {
                $stmt_email = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value, updated_by) VALUES ('test_email', ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
                $stmt_email->bind_param("sisi", $test_email, $_SESSION['user_id'], $test_email, $_SESSION['user_id']);
                $stmt_email->execute();
            }
            
            // Actualizar notificación de buzón
            $stmt_notify = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value, updated_by) VALUES ('notify_buzon_on_new_report', ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
            $stmt_notify->bind_param("sisi", $notify_buzon, $_SESSION['user_id'], $notify_buzon, $_SESSION['user_id']);
            $stmt_notify->execute();
            
            // Actualizar encargados de departamentos
            if (isset($_POST['managers']) && is_array($_POST['managers'])) {
                $stmt_dept = $conn->prepare("UPDATE departments SET manager = ? WHERE id = ?");
                foreach ($_POST['managers'] as $dept_id => $manager_name) {
                    $manager_name = trim($manager_name);
                    if (!empty($manager_name)) {
                        $stmt_dept->bind_param("si", $manager_name, $dept_id);
                        $stmt_dept->execute();
                    }
                }
            }
            
            $_SESSION['success_message'] = 'Configuración actualizada exitosamente.';
            header('Location: admin_settings.php');
            exit;
        } else {
            $_SESSION['error_message'] = 'Contraseña incorrecta. No se aplicaron los cambios.';
            header('Location: admin_settings.php');
            exit;
        }
    }
}

// Obtener configuración actual
$stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'test_mode'");
$stmt->execute();
$result = $stmt->get_result();
$test_mode_enabled = false;
if ($row = $result->fetch_assoc()) {
    $test_mode_enabled = $row['setting_value'] == '1';
}

// Obtener correo de pruebas actual
$stmt_email = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'test_email'");
$stmt_email->execute();
$result_email = $stmt_email->get_result();
$test_email = '';
if ($row_email = $result_email->fetch_assoc()) {
    $test_email = $row_email['setting_value'];
}

// Obtener configuración de notificación de buzón
$stmt_notify = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'notify_buzon_on_new_report'");
$stmt_notify->execute();
$result_notify = $stmt_notify->get_result();
$notify_buzon_enabled = false;
if ($row_notify = $result_notify->fetch_assoc()) {
    $notify_buzon_enabled = $row_notify['setting_value'] == '1';
}

// Obtener departamentos y encargados
$departments_query = $conn->query("SELECT id, name, manager, email FROM departments ORDER BY name");
$departments = $departments_query->fetch_all(MYSQLI_ASSOC);

// Recuperar mensajes
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

$page_title = 'Configuración de Administrador - ITSCC Buzón';
include 'components/header.php';
?>

<div class="bg-gray-50 min-h-screen py-12">
    <main class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            
            <!-- Breadcrumb -->
            <div class="mb-6">
                <a href="dashboard.php" class="flex items-center text-gray-500 hover:text-blue-600 font-semibold transition-colors group">
                    <i class="ph-arrow-left text-lg mr-2 group-hover:-translate-x-1 transition-transform"></i>
                    Volver al Dashboard
                </a>
            </div>

            <!-- Mensajes -->
            <?php if ($success): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg flex items-center gap-3 shadow-md">
                    <i class="ph-check-circle text-2xl"></i>
                    <span class="font-medium"><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg flex items-center gap-3 shadow-md">
                    <i class="ph-warning-circle text-2xl"></i>
                    <span class="font-medium"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-8 text-white">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="ph-gear text-4xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold">Configuración de Administrador</h1>
                            <p class="text-blue-100 mt-1">Gestiona las configuraciones del sistema</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Configuración -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden" x-data="{ showPasswordModal: false, testMode: <?php echo $test_mode_enabled ? 'true' : 'false'; ?>, notifyBuzon: <?php echo $notify_buzon_enabled ? 'true' : 'false'; ?> }">
                <div class="p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                        <i class="ph-envelope text-blue-600"></i>
                        Configuración de Correos Electrónicos
                    </h2>

                    <form method="POST" action="admin_settings.php" id="settingsForm">
                        <input type="hidden" name="apply_settings" value="1">
                        <!-- Modo de Prueba -->
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <i class="ph-flask text-2xl text-purple-600"></i>
                                        <h3 class="text-lg font-semibold text-gray-800">Modo de Prueba</h3>
                                    </div>
                                    <p class="text-gray-600 mb-4">
                                        Cuando está activado, todos los correos se enviarán a <strong><?php echo !empty($test_email) ? htmlspecialchars($test_email) : SMTP_USERNAME; ?></strong> en lugar de a los departamentos correspondientes.
                                    </p>
                                    
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                                        <p class="text-sm text-blue-800">
                                            <i class="ph-info mr-2"></i>
                                            <strong>Uso recomendado:</strong> Activa este modo para probar el sistema de correos sin enviar notificaciones reales a los departamentos.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="ml-6">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" 
                                               name="test_mode" 
                                               value="1"
                                               class="sr-only peer"
                                               x-model="testMode"
                                               :checked="testMode">
                                        <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Estado actual -->
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-700">Estado actual:</span>
                                    <span x-show="testMode" class="px-3 py-1 bg-purple-100 text-purple-700 text-sm font-semibold rounded-full">
                                        <i class="ph-flask mr-1"></i>Modo Prueba Activado
                                    </span>
                                    <span x-show="!testMode" class="px-3 py-1 bg-green-100 text-green-700 text-sm font-semibold rounded-full">
                                        <i class="ph-check-circle mr-1"></i>Modo Normal
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Correo de Pruebas -->
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-6">
                            <div class="flex items-center gap-3 mb-4">
                                <i class="ph-envelope-simple text-2xl text-cyan-600"></i>
                                <h3 class="text-lg font-semibold text-gray-800">Correo Electrónico de Pruebas</h3>
                            </div>
                            
                            <p class="text-gray-600 mb-4">
                                Configura el correo electrónico al que se enviarán todas las notificaciones cuando el <strong>Modo de Prueba</strong> esté activado.
                            </p>
                            
                            <div class="mb-4">
                                <label for="test_email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Correo de Pruebas
                                </label>
                                <input type="email" 
                                       id="test_email" 
                                       name="test_email" 
                                       value="<?php echo htmlspecialchars($test_email); ?>"
                                       placeholder="ejemplo@correo.com"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded">
                                <p class="text-sm text-amber-800">
                                    <i class="ph-warning mr-2"></i>
                                    <strong>Nota:</strong> Este correo solo se usará cuando el Modo de Prueba esté activado. Asegúrate de que sea una dirección válida.
                                </p>
                            </div>
                        </div>

                        <!-- Sección de Notificaciones -->
                        <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6 border-2 border-indigo-100">
                            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-white">
                                <div class="flex items-center gap-3">
                                    <i class="ph-bell-ringing text-3xl"></i>
                                    <div>
                                        <h3 class="text-xl font-bold">Notificaciones del Sistema</h3>
                                        <p class="text-indigo-100 text-sm mt-1">Configura las notificaciones automáticas</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-6 bg-gray-50">
                                <!-- Notificar al Buzón -->
                                <div class="bg-white border border-gray-200 rounded-xl p-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <i class="ph-envelope-open text-2xl text-indigo-600"></i>
                                                <h4 class="text-lg font-semibold text-gray-800">Notificar al Departamento de Buzón</h4>
                                            </div>
                                            <p class="text-gray-600 mb-4">
                                                Cuando está activado, se enviará un correo de notificación al departamento <strong>"Buzón de Quejas, Sugerencias y Felicitaciones"</strong> cada vez que se cree un nuevo reporte en el sistema.
                                            </p>
                                            
                                            <div class="bg-indigo-50 border-l-4 border-indigo-400 p-4 rounded">
                                                <p class="text-sm text-indigo-800">
                                                    <i class="ph-info mr-2"></i>
                                                    <strong>Nota:</strong> Si el Modo de Prueba está activado, la notificación se enviará al correo de pruebas configurado en lugar del correo del departamento.
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="ml-6">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" 
                                                       name="notify_buzon_on_new_report" 
                                                       value="1"
                                                       class="sr-only peer"
                                                       x-model="notifyBuzon"
                                                       :checked="notifyBuzon">
                                                <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Estado actual -->
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-700">Estado actual:</span>
                                            <span x-show="notifyBuzon" class="px-3 py-1 bg-indigo-100 text-indigo-700 text-sm font-semibold rounded-full">
                                                <i class="ph-bell-ringing mr-1"></i>Notificaciones Activadas
                                            </span>
                                            <span x-show="!notifyBuzon" class="px-3 py-1 bg-gray-100 text-gray-700 text-sm font-semibold rounded-full">
                                                <i class="ph-bell-slash mr-1"></i>Notificaciones Desactivadas
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Encargados de Departamentos -->
                        <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6 border-2 border-emerald-100">
                            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-6 text-white">
                                <div class="flex items-center gap-3">
                                    <i class="ph-users-three text-3xl"></i>
                                    <div>
                                        <h3 class="text-xl font-bold">Encargados de Departamentos</h3>
                                        <p class="text-emerald-100 text-sm mt-1">Gestiona los responsables de cada área</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-6 bg-gray-50">
                                <p class="text-gray-600 mb-6">
                                    Actualiza los nombres de los encargados de cada departamento. Estos nombres aparecerán en los correos electrónicos y reportes.
                                </p>
                                
                                <div class="grid gap-6 md:grid-cols-2">
                                    <?php foreach ($departments as $dept): ?>
                                    <div class="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow">
                                        <div class="flex items-start gap-3 mb-3">
                                            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="ph-buildings text-xl text-emerald-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($dept['name']); ?></h4>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($dept['email']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label for="manager_<?php echo $dept['id']; ?>" class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wider">
                                                Encargado Actual
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <i class="ph-user text-gray-400"></i>
                                                </div>
                                                <input type="text" 
                                                       id="manager_<?php echo $dept['id']; ?>" 
                                                       name="managers[<?php echo $dept['id']; ?>]" 
                                                       value="<?php echo htmlspecialchars($dept['manager']); ?>"
                                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm transition-colors"
                                                       placeholder="Nombre del encargado">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>


                        <!-- Información adicional -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-6">
                            <div class="flex items-start gap-3">
                                <i class="ph-warning text-2xl text-yellow-600 flex-shrink-0"></i>
                                <div>
                                    <h4 class="font-semibold text-yellow-900 mb-2">Importante</h4>
                                    <p class="text-sm text-yellow-800">
                                        Para aplicar los cambios, deberás ingresar tu contraseña de administrador por seguridad. 
                                        Los cambios se aplicarán inmediatamente después de la verificación.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Botón de aplicar -->
                        <div class="flex justify-end">
                            <button type="button" @click="showPasswordModal = true" class="inline-flex items-center px-8 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl transform hover:scale-105">
                                <i class="ph-check text-xl mr-2"></i>
                                Aplicar Cambios
                            </button>
                        </div>
                <!-- Modal de Confirmación de Contraseña -->
                <div x-show="showPasswordModal" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                     style="display: none;"
                     @keydown.escape.window="showPasswordModal = false">
                    
                    <div @click.away="showPasswordModal = false"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
                        
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="ph-lock-key text-3xl text-blue-600"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirmar Cambios</h3>
                            <p class="text-gray-600">Ingresa tu contraseña de administrador para aplicar los cambios</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Contraseña de Administrador
                                </label>
                                <input type="password" 
                                       id="admin_password" 
                                       name="admin_password" 
                                       required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Ingresa tu contraseña">
                            </div>

                            <div class="flex gap-3 mt-6">
                                <button type="button" 
                                        @click="showPasswordModal = false"
                                        class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                                    Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        </div>
    </main>
</div>

<?php include 'components/footer.php'; ?>
