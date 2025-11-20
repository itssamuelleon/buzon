<?php
// --- PHP LOGIC FIRST ---
require_once 'config.php';
require_once 'send_verification_email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = 'register'; // 'register', 'verify', or 'complete'
$pending_email = '';

// Limpiar códigos expirados
$conn->query("DELETE FROM email_verifications WHERE expires_at < NOW() AND verified = FALSE");

// Obtener configuración de restricción de correo
$stmt_check = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'disable_institutional_email_check'");
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$disable_email_check = false;
if ($row_check = $result_check->fetch_assoc()) {
    $disable_email_check = $row_check['setting_value'] == '1';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // PASO 1: Enviar código de verificación
    if (isset($_POST['send_code'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Server-side validation
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Todos los campos son obligatorios.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El formato del correo electrónico no es válido.';
        } elseif (!$disable_email_check && substr(strtolower($email), -strlen('@cdconstitucion.tecnm.mx')) !== '@cdconstitucion.tecnm.mx') {
            $error = 'Solo se permiten correos institucionales del TecNM Campus Ciudad Constitución (@cdconstitucion.tecnm.mx).';
        } else {
            // Check if email already exists in users table
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Este correo electrónico ya está registrado.';
            } else {
                // Generate 6-digit code
                $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Delete any previous verification attempts for this email
                $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");
                
                // Store verification data
                $stmt = $conn->prepare("INSERT INTO email_verifications (email, name, password_hash, verification_code, expires_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $email, $name, $hashed_password, $verification_code, $expires_at);
                
                if ($stmt->execute()) {
                    // Send verification email
                    if (sendVerificationEmail($email, $name, $verification_code)) {
                        $step = 'verify';
                        $pending_email = $email;
                        $_SESSION['pending_verification_email'] = $email;
                        $success = 'Se ha enviado un código de verificación a tu correo electrónico. Por favor, revisa tu bandeja de entrada.';
                    } else {
                        $error = 'No se pudo enviar el correo de verificación. Por favor, intenta de nuevo.';
                    }
                } else {
                    $error = 'Ocurrió un error al procesar tu solicitud. Por favor, intenta de nuevo.';
                }
            }
        }
    }
    
    // PASO 2: Verificar código y crear cuenta
    elseif (isset($_POST['verify_code'])) {
        $email = $_POST['email'];
        $code = trim($_POST['verification_code']);
        
        if (empty($code)) {
            $error = 'Por favor, ingresa el código de verificación.';
            $step = 'verify';
            $pending_email = $email;
        } else {
            // Verify code
            $stmt = $conn->prepare("SELECT name, password_hash, expires_at FROM email_verifications WHERE email = ? AND verification_code = ? AND verified = FALSE");
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Código de verificación inválido. Por favor, verifica e intenta de nuevo.';
                $step = 'verify';
                $pending_email = $email;
            } else {
                $row = $result->fetch_assoc();
                
                // Check if code expired
                if (strtotime($row['expires_at']) < time()) {
                    $error = 'El código de verificación ha expirado. Por favor, solicita uno nuevo.';
                    $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");
                    unset($_SESSION['pending_verification_email']);
                    $step = 'register';
                } else {
                    // Determine user role based on department email
                    $role = 'student';
                    $dept_stmt = $conn->prepare("SELECT id FROM departments WHERE email = ?");
                    $dept_stmt->bind_param("s", $email);
                    $dept_stmt->execute();
                    $dept_result = $dept_stmt->get_result();
                    
                    if ($dept_result->num_rows > 0) {
                        $role = 'manager';
                    }
                    
                    // Create user account
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $row['name'], $email, $row['password_hash'], $role);
                    
                    if ($stmt->execute()) {
                        // Mark as verified and delete verification record
                        $conn->query("UPDATE email_verifications SET verified = TRUE WHERE email = '" . $conn->real_escape_string($email) . "'");
                        $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");
                        unset($_SESSION['pending_verification_email']);
                        
                        $success = '¡Registro exitoso! Tu cuenta ha sido verificada. Ahora puedes iniciar sesión con tus credenciales.';
                        $step = 'complete';
                    } else {
                        $error = 'Ocurrió un error al crear tu cuenta. Por favor, intenta de nuevo.';
                        $step = 'verify';
                        $pending_email = $email;
                    }
                }
            }
        }
    }
}

// Limpiar verificación pendiente si se solicita reset
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    unset($_SESSION['pending_verification_email']);
    header('Location: register.php');
    exit;
}

// Check if there's a pending verification
if (isset($_SESSION['pending_verification_email']) && $step === 'register') {
    $step = 'verify';
    $pending_email = $_SESSION['pending_verification_email'];
}

// --- END OF PHP LOGIC ---

// --- HTML PRESENTATION SECOND ---
$page_title = 'Registrarse - ITSCC Buzón'; 
include 'components/header.php'; 
?>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div class="min-h-screen relative overflow-hidden flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute top-1/2 right-1/4 w-96 h-96 bg-purple-400/20 rounded-full blur-3xl animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-0 left-1/2 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl animate-blob animation-delay-4000"></div>
    </div>

    <div class="container mx-auto max-w-6xl relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            
            <div class="order-1 lg:order-1">
                <div class="bg-white/90 backdrop-blur-xl rounded-3xl shadow-2xl p-8 md:p-12 border border-white/20 animate-slide-in-left">
                    <div class="text-center mb-8">
                        <div class="inline-block relative mb-6">
                            <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full blur-xl opacity-30 animate-pulse-slow"></div>
                            <div class="relative p-4 bg-white rounded-2xl shadow-lg">
                                <img src="assets/logo.png" alt="ITSCC Logo" class="h-16 w-16 mx-auto">
                            </div>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-black bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-2">
                            <?php echo ($step === 'verify') ? 'Verificar Correo' : 'Crea tu Cuenta'; ?>
                        </h1>
                        <p class="text-slate-600 text-lg">
                            <?php echo ($step === 'verify') ? 'Ingresa el código que enviamos a tu correo.' : 'Únete y haz que tu voz sea escuchada.'; ?>
                        </p>
                    </div>

                    <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 rounded-xl p-4 animate-shake">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="font-bold text-red-800">Error</p>
                                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($success && $step !== 'complete'): ?>
                    <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 rounded-xl p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="font-bold text-blue-800">Código Enviado</p>
                                <p class="text-blue-700 text-sm"><?php echo htmlspecialchars($success); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($step === 'complete'): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-500 rounded-xl p-4 text-center">
                         <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3 text-left">
                                <p class="font-bold text-green-800">¡Registro Exitoso!</p>
                                <p class="text-green-700 text-sm"><?php echo htmlspecialchars($success); ?></p>
                            </div>
                        </div>
                        <a href="login.php" class="mt-4 inline-block w-full text-center py-3 px-4 rounded-xl text-lg font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                            Ir a Iniciar Sesión
                        </a>
                    </div>
                    
                    <?php elseif ($step === 'verify'): ?>
                    <!-- FORMULARIO DE VERIFICACIÓN -->
                    <form method="POST" action="register.php" class="space-y-6">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($pending_email); ?>">
                        
                        <div>
                            <label for="verification_code" class="block text-sm font-semibold text-slate-700 mb-2">Código de Verificación</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <input type="text" id="verification_code" name="verification_code" required maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                                    class="block w-full pl-12 pr-4 py-3.5 text-slate-900 bg-white border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-200 placeholder:text-slate-400 text-center text-2xl tracking-widest font-mono">
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Ingresa el código de 6 dígitos que recibiste en tu correo</p>
                        </div>

                        <button type="submit" name="verify_code"
                            class="group relative w-full flex justify-center items-center py-4 px-6 border border-transparent rounded-xl text-lg font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-500/50 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl">
                            Verificar y Crear Cuenta
                            <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </button>

                        <div class="text-center">
                            <a href="register.php?reset=true" class="text-sm text-blue-600 hover:text-indigo-600 transition-colors">
                                ← Volver al formulario de registro
                            </a>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- FORMULARIO DE REGISTRO -->
                    <form method="POST" action="register.php" autocomplete="off" class="space-y-6" x-data="{ 
                        name: '', 
                        email: '', 
                        password: '', 
                        confirm_password: '', 
                        showPassword: false, 
                        showConfirmPassword: false,
                        disableEmailCheck: <?php echo $disable_email_check ? 'true' : 'false'; ?>,
                        isValidEmailDomain() {
                            if (this.disableEmailCheck) return true;
                            if (!this.email) return true;
                            return this.email.toLowerCase().endsWith('@cdconstitucion.tecnm.mx');
                        }
                    }">
                        
                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-700 mb-2">Nombre Completo</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <input type="text" id="name" name="name" required x-model="name" placeholder="Tu nombre completo"
                                    class="block w-full pl-12 pr-4 py-3.5 text-slate-900 bg-white border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-200 placeholder:text-slate-400">
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">Correo Electrónico</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <input type="email" id="email" name="email" autocomplete="off" required x-model="email" placeholder="tu.correo@cdconstitucion.tecnm.mx"
                                    :class="email && !isValidEmailDomain() ? 'border-red-500 focus:border-red-500 focus:ring-red-500/10' : 'border-slate-200 focus:border-blue-500 focus:ring-blue-500/10'"
                                    class="block w-full pl-12 pr-4 py-3.5 text-slate-900 bg-white border-2 rounded-xl focus:ring-4 transition-all duration-200 placeholder:text-slate-400">
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Solo correos institucionales del TecNM Campus Ciudad Constitución</p>
                            <template x-if="email && !isValidEmailDomain()">
                                <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Solo se permiten correos institucionales del TecNM Campus Ciudad Constitución (@cdconstitucion.tecnm.mx)
                                </p>
                            </template>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Contraseña</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <input :type="showPassword ? 'text' : 'password'" id="password" name="password" autocomplete="new-password" required minlength="8" x-model="password" placeholder="Mínimo 8 caracteres"
                                    class="block w-full pl-12 pr-12 py-3.5 text-slate-900 bg-white border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-200 placeholder:text-slate-400">
                                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                    <svg x-show="!showPassword" class="h-5 w-5 text-slate-400 hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <svg x-show="showPassword" class="h-5 w-5 text-slate-400 hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                </button>
                            </div>
                            <template x-if="password && confirm_password && password !== confirm_password">
                                <p class="text-red-600 text-sm mt-2">Las contraseñas no coinciden.</p>
                            </template>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-slate-700 mb-2">Confirmar Contraseña</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                </div>
                                <input :type="showConfirmPassword ? 'text' : 'password'" id="confirm_password" name="confirm_password" autocomplete="new-password" required minlength="8" x-model="confirm_password" placeholder="Repite tu contraseña"
                                    class="block w-full pl-12 pr-12 py-3.5 text-slate-900 bg-white border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-200 placeholder:text-slate-400">
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                    <svg x-show="!showConfirmPassword" class="h-5 w-5 text-slate-400 hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <svg x-show="showConfirmPassword" class="h-5 w-5 text-slate-400 hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                </button>
                            </div>
                            <template x-if="password && confirm_password && password !== confirm_password">
                                <p class="text-red-600 text-sm mt-2">Las contraseñas no coinciden.</p>
                            </template>
                        </div>
                        
                        <button type="submit" name="send_code"
                            :disabled="!name || !email || !isValidEmailDomain() || password.length < 8 || password !== confirm_password"
                            :class="{ 'opacity-50 cursor-not-allowed': !name || !email || !isValidEmailDomain() || password.length < 8 || password !== confirm_password }"
                            class="group relative w-full flex justify-center items-center py-4 px-6 border border-transparent rounded-xl text-lg font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-500/50 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl disabled:hover:scale-100">
                            Enviar Código de Verificación
                            <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="mt-8 text-center">
                        <p class="text-slate-600">
                            ¿Ya tienes una cuenta?
                            <a href="login.php" class="font-bold text-blue-600 hover:text-indigo-600 transition-colors ml-1">
                                Inicia sesión aquí
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="order-2 lg:order-2 text-center lg:text-left animate-slide-in-right">
                <div class="relative">
                    <div class="absolute -top-10 -left-10 w-72 h-72 bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-full blur-3xl"></div>
                    
                    <div class="relative bg-white/40 backdrop-blur-lg rounded-3xl p-12 border border-white/50 shadow-2xl">
                        <div class="grid grid-cols-3 gap-4 mb-8">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 shadow-lg animate-float">
                                <svg class="w-12 h-12 text-white mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-6 shadow-lg animate-float animation-delay-1000">
                                <svg class="w-12 h-12 text-white mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 shadow-lg animate-float animation-delay-2000">
                                <svg class="w-12 h-12 text-white mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>

                        <h2 class="text-4xl md:text-5xl font-black text-slate-800 mb-6 leading-tight">
                            Tu Voz<br/>
                            <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                                Importa
                            </span>
                        </h2>
                        <p class="text-lg text-slate-600 leading-relaxed mb-8">
                            Reporta incidencias, comparte sugerencias y contribuye a construir una mejor institución educativa para toda la comunidad.
                        </p>

                        <div class="grid grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-3xl font-black bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-1">
                                    100%
                                </div>
                                <div class="text-sm text-slate-600 font-medium">Seguro</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-black bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent mb-1">
                                    24/7
                                </div>
                                <div class="text-sm text-slate-600 font-medium">Disponible</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-black bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent mb-1">
                                    Rápido
                                </div>
                                <div class="text-sm text-slate-600 font-medium">Respuesta</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<style>
@keyframes blob {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(20px, -50px) scale(1.1); }
    50% { transform: translate(-20px, 20px) scale(0.9); }
    75% { transform: translate(50px, 50px) scale(1.05); }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.animate-blob {
    animation: blob 7s infinite;
}

.animate-float {
    animation: float 3s ease-in-out infinite;
}

.animate-slide-in-left {
    animation: slideInLeft 0.6s ease-out;
}

.animate-slide-in-right {
    animation: slideInRight 0.6s ease-out;
}

.animate-shake {
    animation: shake 0.5s ease-in-out;
}

.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.animation-delay-1000 {
    animation-delay: 0.5s;
}

.animation-delay-2000 {
    animation-delay: 1s;
}

.animation-delay-4000 {
    animation-delay: 2s;
}
</style>

</body>
</html>