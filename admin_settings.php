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
    $disable_email_check = (isset($_POST['disable_institutional_email_check']) && $_POST['disable_institutional_email_check'] === '1') ? '1' : '0';
    $restrict_dashboard = (isset($_POST['restrict_dashboard_access']) && $_POST['restrict_dashboard_access'] === '1') ? '1' : '0';
    
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
            
            // Actualizar restricción de correo institucional
            $stmt_check = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value, updated_by) VALUES ('disable_institutional_email_check', ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
            $stmt_check->bind_param("sisi", $disable_email_check, $_SESSION['user_id'], $disable_email_check, $_SESSION['user_id']);
            $stmt_check->execute();
            
            // Actualizar restricción de acceso al dashboard
            $stmt_dashboard = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value, updated_by) VALUES ('restrict_dashboard_access', ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
            $stmt_dashboard->bind_param("sisi", $restrict_dashboard, $_SESSION['user_id'], $restrict_dashboard, $_SESSION['user_id']);
            $stmt_dashboard->execute();
            
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
            
            // Crear nuevos departamentos
            if (isset($_POST['new_departments']) && is_array($_POST['new_departments'])) {
                $stmt_new = $conn->prepare("INSERT INTO departments (name, email, manager) VALUES (?, ?, ?)");
                foreach ($_POST['new_departments'] as $new_dept) {
                    $new_dept_data = json_decode($new_dept, true);
                    if ($new_dept_data && !empty($new_dept_data['name']) && !empty($new_dept_data['email']) && !empty($new_dept_data['manager'])) {
                        $name = trim($new_dept_data['name']);
                        $email = trim($new_dept_data['email']);
                        $manager = trim($new_dept_data['manager']);
                        
                        // Validar dominio del correo
                        $domain = '@cdconstitucion.tecnm.mx';
                        if (substr($email, -strlen($domain)) === $domain) {
                            $stmt_new->bind_param("sss", $name, $email, $manager);
                            $stmt_new->execute();
                        }
                    }
                }
            }
            
            // Actualizar visibilidad de departamentos
            // Primero, marcar todos como visibles
            $conn->query("UPDATE departments SET is_hidden = 0");
            
            // Luego, ocultar los seleccionados (si hay alguno)
            if (isset($_POST['hidden_departments']) && is_array($_POST['hidden_departments']) && !empty($_POST['hidden_departments'])) {
                $stmt_hide = $conn->prepare("UPDATE departments SET is_hidden = 1 WHERE id = ?");
                foreach ($_POST['hidden_departments'] as $dept_id) {
                    $dept_id = intval($dept_id);
                    $stmt_hide->bind_param("i", $dept_id);
                    $stmt_hide->execute();
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

// Obtener configuración de restricción de correo
$stmt_check = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'disable_institutional_email_check'");
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$disable_email_check = false;
if ($row_check = $result_check->fetch_assoc()) {
    $disable_email_check = $row_check['setting_value'] == '1';
}

// Obtener configuración de restricción de acceso al dashboard
$stmt_dashboard = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'restrict_dashboard_access'");
$stmt_dashboard->execute();
$result_dashboard = $stmt_dashboard->get_result();
$restrict_dashboard_access = false;
if ($row_dashboard = $result_dashboard->fetch_assoc()) {
    $restrict_dashboard_access = $row_dashboard['setting_value'] == '1';
}

// Obtener departamentos y encargados
try {
    $departments_query = $conn->query("SELECT id, name, manager, email, COALESCE(is_hidden, 0) as is_hidden FROM departments ORDER BY name");
    $departments = $departments_query->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Si la columna is_hidden no existe, usar consulta sin ella
    $departments_query = $conn->query("SELECT id, name, manager, email, 0 as is_hidden FROM departments ORDER BY name");
    $departments = $departments_query->fetch_all(MYSQLI_ASSOC);
}

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

            <!-- Header Simplificado -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="ph-gear text-blue-600"></i>
                    Configuración de Administrador
                </h1>
                <p class="text-gray-500 mt-1">Gestiona las configuraciones globales del sistema</p>
            </div>

            <!-- Formulario de Configuración Mejorado -->
            <?php
            // Prepare data for Alpine.js
            // Get hidden department IDs - ensure proper type conversion
            $hidden_depts = [];
            foreach ($departments as $dept) {
                // Check if is_hidden is 1 or '1' (handle both int and string)
                if (isset($dept['is_hidden']) && ($dept['is_hidden'] == 1 || $dept['is_hidden'] === '1')) {
                    $hidden_depts[] = intval($dept['id']);
                }
            }
            
            // Convert department IDs to integers to match hiddenDepts type
            foreach ($departments as &$dept) {
                $dept['id'] = intval($dept['id']);
            }
            unset($dept); // Break reference
            
            // Use json_encode with flags to avoid escaping issues
            $departments_json = json_encode($departments, JSON_HEX_APOS | JSON_HEX_QUOT);
            $hidden_depts_json = json_encode($hidden_depts);
            ?>
            <div x-data='{
                "showPasswordModal": false,
                "testMode": <?php echo $test_mode_enabled ? 'true' : 'false'; ?>,
                "notifyBuzon": <?php echo $notify_buzon_enabled ? 'true' : 'false'; ?>,
                "disableEmailCheck": <?php echo $disable_email_check ? 'true' : 'false'; ?>,
                "restrictDashboard": <?php echo $restrict_dashboard_access ? 'true' : 'false'; ?>,
                "editDeptModal": false,
                "currentDept": null,
                "addDeptModal": false,
                "newDept": { "name": "", "email": "", "manager": "" },
                "newDeptError": "",
                "newDepartments": [],
                "departments": <?php echo $departments_json; ?>,
                "hiddenDepts": <?php echo $hidden_depts_json; ?>,
                "hasPendingChanges": false,
                "editDepartment": function(dept) {
                    this.currentDept = JSON.parse(JSON.stringify(dept));
                    this.editDeptModal = true;
                },
                "addDepartment": function() {
                    this.newDept = { "name": "", "email": "", "manager": "" };
                    this.newDeptError = "";
                    this.addDeptModal = true;
                },
                "saveNewDept": function() {
                    this.newDeptError = "";
                    
                    if (!this.newDept.name || !this.newDept.email || !this.newDept.manager) {
                        this.newDeptError = "Todos los campos son obligatorios";
                        return;
                    }

                    if (!this.newDept.email.endsWith("@cdconstitucion.tecnm.mx")) {
                        this.newDeptError = "El correo debe ser @cdconstitucion.tecnm.mx";
                        return;
                    }

                    // Add to pending new departments
                    this.newDepartments.push(JSON.parse(JSON.stringify(this.newDept)));
                    // Add to display list with temporary negative ID
                    const tempDept = JSON.parse(JSON.stringify(this.newDept));
                    tempDept.id = -1 * (this.newDepartments.length);
                    tempDept.is_hidden = "0";
                    this.departments.push(tempDept);
                    this.addDeptModal = false;
                    this.hasPendingChanges = true;
                },
                "saveDeptChanges": function() {
                    const index = this.departments.findIndex(d => d.id === this.currentDept.id);
                    if (index !== -1) {
                        this.departments[index] = JSON.parse(JSON.stringify(this.currentDept));
                    }
                    this.editDeptModal = false;
                    this.hasPendingChanges = true;
                },
                "toggleHidden": function(deptId) {
                    const index = this.hiddenDepts.indexOf(deptId);
                    if (index > -1) {
                        this.hiddenDepts.splice(index, 1);
                    } else {
                        this.hiddenDepts.push(deptId);
                    }
                    this.hasPendingChanges = true;
                },
                "isHidden": function(deptId) {
                    return this.hiddenDepts.includes(deptId);
                },
                "isNewDept": function(deptId) {
                    return deptId < 0;
                }
            }' class="max-w-5xl mx-auto">
                
                <form method="POST" action="admin_settings.php" id="settingsForm" class="space-y-8">
                    <input type="hidden" name="apply_settings" value="1">
                    <input type="hidden" name="test_mode" :value="testMode ? '1' : '0'">
                    <input type="hidden" name="notify_buzon_on_new_report" :value="notifyBuzon ? '1' : '0'">
                    <input type="hidden" name="disable_institutional_email_check" :value="disableEmailCheck ? '1' : '0'">
                    <input type="hidden" name="restrict_dashboard_access" :value="restrictDashboard ? '1' : '0'">
                    <template x-for="deptId in hiddenDepts" :key="deptId">
                        <input type="hidden" name="hidden_departments[]" :value="deptId">
                    </template>
                    <template x-for="(newDept, index) in newDepartments" :key="index">
                        <input type="hidden" name="new_departments[]" :value="JSON.stringify(newDept)">
                    </template>

                    <!-- Tarjeta: Configuración General -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-blue-50/50 flex items-center gap-3">
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                                <i class="ph-sliders text-xl leading-none"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800">Configuración General</h2>
                                <p class="text-sm text-gray-500">Control de acceso y notificaciones globales</p>
                            </div>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <!-- Toggle: Registro -->
                            <div class="flex items-start justify-between">
                                <div class="flex-1 pr-4">
                                    <h3 class="font-medium text-gray-900 flex items-center gap-2">
                                        Desactivar Verificación de Correo Institucional
                                        <span class="text-xs font-normal px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">Seguridad</span>
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                                        Por defecto, el sistema solo permite registros con el dominio <strong>@cdconstitucion.tecnm.mx</strong>. 
                                        Si activas esta opción, <strong>cualquier persona</strong> podrá registrarse con correos externos (Gmail, Outlook, etc.).
                                    </p>
                                    <div x-show="disableEmailCheck" class="mt-3 inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium bg-red-50 text-red-700 border border-red-100">
                                        <i class="ph-warning mr-1.5"></i> 
                                        Verificación de Correo Institucional desactivada.
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" class="sr-only peer" x-model="disableEmailCheck">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <hr class="border-gray-100">

                            <!-- Toggle: Notificaciones -->
                            <div class="flex items-start justify-between">
                                <div class="flex-1 pr-4">
                                    <h3 class="font-medium text-gray-900 flex items-center gap-2">
                                        Notificar al Departamento de Buzón
                                        <span class="text-xs font-normal px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">Monitoreo</span>
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                                        Envía una copia automática de <strong>cada nuevo reporte</strong> generado al correo electrónico asignado al departamento "Buzón de Quejas". 
                                        Esto permite un monitoreo centralizado de toda la actividad del sistema en tiempo real.
                                    </p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" class="sr-only peer" x-model="notifyBuzon">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            
                            <!-- Toggle: Restringir Acceso al Dashboard -->
                            <div class="flex items-start justify-between pt-6 border-t border-gray-100">
                                <div class="flex-1 pr-4">
                                    <h3 class="font-medium text-gray-900 flex items-center gap-2">
                                        Restringir Acceso al Dashboard
                                        <span class="text-xs font-normal px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">Acceso</span>
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                                        Cuando está activado, <strong>solo los administradores</strong> podrán acceder al dashboard. 
                                        Los estudiantes y encargados de departamento no verán la opción en el menú ni podrán acceder directamente.
                                    </p>
                                    <div x-show="restrictDashboard" class="mt-3 inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium bg-orange-50 text-orange-700 border border-orange-100">
                                        <i class="ph-warning mr-1.5"></i>
                                        Dashboard restringido solo a administradores
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" class="sr-only peer" x-model="restrictDashboard">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta: Modo de Pruebas -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-purple-50/50 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                                    <i class="ph-flask text-xl leading-none"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-800">Entorno de Pruebas</h2>
                                    <p class="text-sm text-gray-500">Herramientas para desarrollo y mantenimiento</p>
                                </div>
                            </div>
                            <div x-show="testMode" class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700 border border-purple-200 flex items-center gap-1 animate-pulse">
                                <i class="ph-circle bg-purple-500 rounded-full w-2 h-2"></i>
                                MODO PRUEBA ACTIVO
                            </div>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 pr-4">
                                    <h3 class="font-medium text-gray-900">Activar Modo de Prueba</h3>
                                    <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                                        Intercepta <strong>todos</strong> los correos electrónicos salientes del sistema (notificaciones de reportes, recuperaciones de contraseña, etc.) y evita que lleguen a los usuarios reales.
                                    </p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="checkbox" class="sr-only peer" x-model="testMode">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>

                            <div x-show="testMode" x-transition class="bg-purple-50 rounded-xl p-5 border border-purple-100">
                                <label for="test_email" class="block text-sm font-semibold text-purple-900 mb-2">Correo de Destino para Pruebas</label>
                                <div class="flex gap-2">
                                    <div class="relative flex-grow">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="ph-envelope text-purple-400"></i>
                                        </div>
                                        <input type="email" id="test_email" name="test_email" value="<?php echo htmlspecialchars($test_email); ?>" class="block w-full pl-10 rounded-lg border-purple-200 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm py-2.5" placeholder="tu.correo@ejemplo.com">
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-purple-700 flex items-start gap-1.5">
                                    <i class="ph-info text-base shrink-0"></i>
                                    <span>Todos los correos interceptados se redirigirán únicamente a esta dirección. Asegúrate de tener acceso a ella.</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta: Encargados -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-emerald-50/50 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center">
                                    <i class="ph-users-three text-xl leading-none"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-800">Encargados de Departamentos</h2>
                                    <p class="text-sm text-gray-500">Gestión de firmas y responsables</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-gray-800" x-text="departments.length"></p>
                                    <p class="text-xs text-gray-500">Total</p>
                                </div>
                                <div class="w-px h-10 bg-gray-300"></div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-gray-500" x-text="hiddenDepts.length"></p>
                                    <p class="text-xs text-gray-500">Ocultos</p>
                                </div>
                                <div class="w-px h-10 bg-gray-300"></div>
                                <button type="button" 
                                        @click="addDepartment()"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                                    <i class="ph-plus-circle text-lg"></i>
                                    Agregar
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-600 mb-6">
                                Define los nombres de los responsables para cada área. Estos nombres se utilizarán para personalizar las firmas en los correos electrónicos automáticos y en la interfaz de seguimiento de reportes.
                            </p>
                            
                            <!-- Pending Changes Alert -->
                            <div x-show="hasPendingChanges" x-transition class="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 flex items-center gap-2">
                                <i class="ph-warning text-yellow-600 text-lg"></i>
                                <p class="text-sm text-yellow-800 font-medium">Hay cambios pendientes. Haz clic en "Guardar Cambios" para aplicarlos.</p>
                            </div>
                            
                            <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="dept in departments" :key="dept.id">
                                    <div class="p-4 rounded-xl border transition-all group relative"
                                         :class="{
                                             'border-gray-200 bg-gray-50': isHidden(dept.id) && !isNewDept(dept.id),
                                             'border-blue-200 bg-blue-50': isNewDept(dept.id),
                                             'border-gray-200 bg-white hover:border-emerald-400 hover:shadow-md': !isHidden(dept.id) && !isNewDept(dept.id)
                                         }">
                                        
                                        <!-- Edit Button -->
                                        <button type="button" 
                                                @click="editDepartment(dept)"
                                                x-show="!isNewDept(dept.id)"
                                                class="absolute top-2 right-2 w-8 h-8 rounded-lg bg-white border border-gray-200 hover:border-blue-400 hover:bg-blue-50 flex items-center justify-center text-gray-400 hover:text-blue-600 transition-all shadow-sm">
                                            <i class="ph-pencil-simple text-sm"></i>
                                        </button>
                                        
                                        <div class="flex items-center gap-3 mb-3 pr-8">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors flex-shrink-0"
                                                 :class="{
                                                     'bg-gray-100 text-gray-400': isHidden(dept.id) && !isNewDept(dept.id),
                                                     'bg-blue-100 text-blue-600': isNewDept(dept.id),
                                                     'bg-gray-50 group-hover:bg-emerald-50 text-gray-400 group-hover:text-emerald-600': !isHidden(dept.id) && !isNewDept(dept.id)
                                                 }">
                                                <i class="ph-buildings text-xl leading-none"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h4 class="text-sm font-bold truncate"
                                                    :class="{
                                                        'text-gray-500': isHidden(dept.id) && !isNewDept(dept.id),
                                                        'text-blue-700': isNewDept(dept.id),
                                                        'text-gray-800': !isHidden(dept.id) && !isNewDept(dept.id)
                                                    }"
                                                    x-text="dept.name"></h4>
                                                <p class="text-xs truncate"
                                                   :class="isNewDept(dept.id) ? 'text-blue-600' : 'text-gray-500'"
                                                   x-text="dept.email"></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Manager Info -->
                                        <div class="mb-2">
                                            <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Encargado</label>
                                            <p class="text-sm font-medium truncate"
                                               :class="{
                                                   'text-gray-500': isHidden(dept.id) && !isNewDept(dept.id),
                                                   'text-blue-700': isNewDept(dept.id),
                                                   'text-gray-700': !isHidden(dept.id) && !isNewDept(dept.id)
                                               }"
                                               x-text="dept.manager || 'Sin asignar'"></p>
                                        </div>
                                        
                                        <!-- Hidden input for form submission -->
                                        <input type="hidden" 
                                               :name="'managers[' + dept.id + ']'" 
                                               x-model="dept.manager">
                                        
                                        <!-- Hidden Badge -->
                                        <div x-show="isHidden(dept.id) && !isNewDept(dept.id)" class="mt-2 inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-200 text-gray-600 text-xs font-medium">
                                            <i class="ph-eye-slash"></i>
                                            Oculto
                                        </div>
                                        
                                        <!-- New Badge -->
                                        <div x-show="isNewDept(dept.id)" class="mt-2 inline-flex items-center gap-1 px-2 py-1 rounded-md bg-blue-200 text-blue-700 text-xs font-medium">
                                            <i class="ph-sparkle"></i>
                                            Nuevo
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Botón Guardar -->
                    <div class="flex items-center justify-between pt-4">
                        <p class="text-sm text-gray-500 italic">
                            <i class="ph-lock-key mr-1"></i>
                            Se requerirá contraseña para guardar
                        </p>
                        <button type="button" 
                                @click="showPasswordModal = true" 
                                class="inline-flex items-center px-6 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm hover:shadow"
                                :class="hasPendingChanges ? 'ring-2 ring-yellow-400 ring-offset-2' : ''">
                            <i class="ph-floppy-disk text-lg mr-2"></i>
                            Guardar Cambios
                            <span x-show="hasPendingChanges" class="ml-2 w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
                        </button>
                    </div>
                </form>
                
                <!-- Modal: Edit Department -->
                <div x-show="editDeptModal" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @keydown.escape.window="editDeptModal = false"
                     x-cloak
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                     style="display: none;"
                     x-init="$watch('editDeptModal', value => { document.body.style.overflow = value ? 'hidden' : 'auto' })">
                    
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="editDeptModal = false"></div>
                    
                    <!-- Modal Content -->
                    <div @click.away="editDeptModal = false"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 z-10">
                        
                        <template x-if="currentDept">
                            <div>
                                <div class="text-center mb-6">
                                    <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="ph-buildings text-3xl text-emerald-600"></i>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Editar Departamento</h3>
                                    <p class="text-gray-600" x-text="currentDept.name"></p>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Encargado
                                        </label>
                                        <input type="text" 
                                               x-model="currentDept.manager"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                               placeholder="Nombre del encargado">
                                    </div>
                                    
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900">Ocultar departamento</p>
                                            <p class="text-sm text-gray-500 mt-0.5">No aparecerá al asignar reportes</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" 
                                                   class="sr-only peer" 
                                                   :checked="isHidden(currentDept.id)"
                                                   @change="toggleHidden(currentDept.id)">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-gray-600"></div>
                                        </label>
                                    </div>

                                    <div class="flex gap-3 mt-6">
                                        <button type="button" 
                                                @click="editDeptModal = false"
                                                class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                                            Cancelar
                                        </button>
                                        <button type="button"
                                                @click="saveDeptChanges()"
                                                class="flex-1 px-4 py-3 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition-colors">
                                            Guardar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Modal: Add Department -->
                <div x-show="addDeptModal" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @keydown.escape.window="addDeptModal = false"
                     x-cloak
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                     style="display: none;"
                     x-init="$watch('addDeptModal', value => { document.body.style.overflow = value ? 'hidden' : 'auto' })">
                    
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="addDeptModal = false"></div>
                    
                    <!-- Modal Content -->
                    <div @click.away="addDeptModal = false"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 z-10">
                        
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="ph-plus-circle text-3xl text-emerald-600"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">Nuevo Departamento</h3>
                            <p class="text-gray-600">Completa la información del departamento</p>
                        </div>

                        <div class="space-y-4">
                            <!-- Error Message -->
                            <div x-show="newDeptError" class="p-3 rounded-lg bg-red-50 text-red-600 text-sm flex items-center gap-2">
                                <i class="ph-warning-circle"></i>
                                <span x-text="newDeptError"></span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre del Departamento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       x-model="newDept.name"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                       placeholder="Ej: Departamento de Recursos Humanos">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Correo Electrónico <span class="text-red-500">*</span>
                                </label>
                                <input type="email" 
                                       x-model="newDept.email"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                       placeholder="departamento@cdconstitucion.tecnm.mx">
                                <p class="mt-1 text-xs text-gray-500">Debe terminar en @cdconstitucion.tecnm.mx</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Encargado <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       x-model="newDept.manager"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                       placeholder="Nombre del encargado">
                            </div>

                            <div class="flex gap-3 mt-6">
                                <button type="button" 
                                        @click="addDeptModal = false"
                                        class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                                    Cancelar
                                </button>
                                <button type="button"
                                        @click="saveNewDept()"
                                        :disabled="!newDept.name || !newDept.email || !newDept.manager"
                                        :class="(newDept.name && newDept.email && newDept.manager) ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-400 cursor-not-allowed'"
                                        class="flex-1 px-4 py-3 text-white font-semibold rounded-lg transition-colors">
                                    Crear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal de Confirmación de Contraseña -->
                <div x-show="showPasswordModal" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @keydown.escape.window="showPasswordModal = false"
                     x-cloak
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                     style="display: none;"
                     x-init="$watch('showPasswordModal', value => { document.body.style.overflow = value ? 'hidden' : 'auto' })">
                    
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showPasswordModal = false"></div>
                    
                    <!-- Modal Content -->
                    <div @click.away="showPasswordModal = false"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 z-10">
                        
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="ph-lock-key text-3xl text-blue-600"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirmar Cambios</h3>
                            <p class="text-gray-600">Ingresa tu contraseña para guardar la configuración</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña de Administrador</label>
                                <input type="password" 
                                       id="admin_password" 
                                       form="settingsForm"
                                       name="admin_password" 
                                       required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                       placeholder="••••••••">
                            </div>

                            <div class="flex gap-3 mt-6">
                                <button type="button" 
                                        @click="showPasswordModal = false"
                                        class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-colors">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        form="settingsForm"
                                        class="flex-1 px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl">
                                    Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>
</div>

<?php include 'components/footer.php'; ?>
