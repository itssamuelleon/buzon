<?php
require_once 'config.php';
require_once 'send_verification_email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = 'request'; // 'request', 'verify', 'reset', or 'complete'
$pending_email = '';

// Limpiar códigos expirados
$conn->query("DELETE FROM email_verifications WHERE expires_at < NOW() AND verified = FALSE");

// Recuperar estado de la sesión si existe
if (isset($_SESSION['pending_reset_email'])) {
    $pending_email = $_SESSION['pending_reset_email'];
    if (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === true) {
        $step = 'reset';
    } elseif ($step === 'request') { // Solo avanzar a verify si no estamos forzando otro paso
        $step = 'verify';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // PASO 1: Solicitar recuperación (Enviar código)
    if (isset($_POST['send_code'])) {
        $email = trim($_POST['email']);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Por favor, ingresa un correo electrónico válido.';
        } else {
            // Verificar si el usuario existe
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'No encontramos una cuenta asociada a este correo electrónico.';
            } else {
                $user = $result->fetch_assoc();
                $name = $user['name'];
                
                // Generate 6-digit code
                $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                // Usamos un marcador temporal ya que la contraseña real se pedirá después
                $temp_hash = 'PENDING_RESET'; 
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Delete any previous verification attempts for this email
                $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");
                
                // Store verification data
                $stmt = $conn->prepare("INSERT INTO email_verifications (email, name, password_hash, verification_code, expires_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $email, $name, $temp_hash, $verification_code, $expires_at);
                
                if ($stmt->execute()) {
                    // Send verification email with type 'password_reset'
                    if (sendVerificationEmail($email, $name, $verification_code, 'password_reset')) {
                        $step = 'verify';
                        $pending_email = $email;
                        $_SESSION['pending_reset_email'] = $email;
                        // Asegurarnos de limpiar cualquier verificación previa
                        unset($_SESSION['reset_verified']);
                        $success = 'Se ha enviado un código de recuperación a tu correo electrónico.';
                    } else {
                        $error = 'No se pudo enviar el correo. Por favor, intenta de nuevo.';
                    }
                } else {
                    $error = 'Ocurrió un error al procesar tu solicitud. Por favor, intenta de nuevo.';
                }
            }
        }
    }
    
    // PASO 2: Verificar código
    elseif (isset($_POST['verify_code'])) {
        $email = $_POST['email'];
        $code = trim($_POST['verification_code']);
        
        if (empty($code)) {
            $error = 'Por favor, ingresa el código de verificación.';
            $step = 'verify';
        } else {
            // Verify code
            $stmt = $conn->prepare("SELECT expires_at FROM email_verifications WHERE email = ? AND verification_code = ? AND verified = FALSE");
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Código de verificación inválido. Por favor, verifica e intenta de nuevo.';
                $step = 'verify';
            } else {
                $row = $result->fetch_assoc();
                
                // Check if code expired
                if (strtotime($row['expires_at']) < time()) {
                    $error = 'El código de verificación ha expirado. Por favor, solicita uno nuevo.';
                    $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");
                    unset($_SESSION['pending_reset_email']);
                    unset($_SESSION['reset_verified']);
                    $step = 'request';
                } else {
                    // Código válido, marcar en sesión y avanzar
                    $_SESSION['reset_verified'] = true;
                    $step = 'reset';
                    $success = 'Código verificado correctamente. Ahora puedes establecer tu nueva contraseña.';
                }
            }
        }
    }

    // PASO 3: Establecer nueva contraseña
    elseif (isset($_POST['reset_password'])) {
        $email = $_SESSION['pending_reset_email'] ?? '';
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (!isset($_SESSION['reset_verified']) || !$_SESSION['reset_verified']) {
            $error = 'Sesión no válida. Por favor, inicia el proceso nuevamente.';
            $step = 'request';
        } elseif (empty($email)) {
            $error = 'Error de sesión. Por favor, inicia el proceso nuevamente.';
            $step = 'request';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
            $step = 'reset';
        } elseif ($password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden.';
            $step = 'reset';
        } else {
            // Update user password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if ($stmt->execute()) {
                // Clean up
                $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");
                unset($_SESSION['pending_reset_email']);
                unset($_SESSION['reset_verified']);
                
                $success = 'Tu contraseña ha sido restablecida exitosamente. Ahora puedes iniciar sesión con tu nueva contraseña.';
                $step = 'complete';
            } else {
                $error = 'Ocurrió un error al actualizar tu contraseña. Por favor, intenta de nuevo.';
                $step = 'reset';
            }
        }
    }
}

// Limpiar sesión si se solicita reset manual del flujo
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    unset($_SESSION['pending_reset_email']);
    unset($_SESSION['reset_verified']);
    header('Location: forgot_password.php');
    exit;
}

$page_title = 'Recuperar Contraseña - ITSCC Buzón'; 
include 'components/header.php'; 
?>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div class="min-h-screen relative overflow-hidden flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute top-1/2 right-1/4 w-96 h-96 bg-purple-400/20 rounded-full blur-3xl animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-0 left-1/2 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl animate-blob animation-delay-4000"></div>
    </div>

    <div class="container mx-auto max-w-md relative z-10">
        <div class="bg-white/90 backdrop-blur-xl rounded-3xl shadow-2xl p-8 md:p-12 border border-white/20 animate-slide-in-left">
            <div class="text-center mb-8">
                <div class="inline-block relative mb-6">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full blur-xl opacity-30 animate-pulse-slow"></div>
                    <div class="relative p-4 bg-white rounded-2xl shadow-lg">
                        <img src="assets/logo.png" alt="ITSCC Logo" class="h-16 w-16 mx-auto">
                    </div>
                </div>
                <h1 class="text-2xl md:text-3xl font-black bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-2">
                    <?php 
                    if ($step === 'verify') echo 'Verificar Código';
                    elseif ($step === 'reset') echo 'Nueva Contraseña';
                    elseif ($step === 'complete') echo '¡Contraseña Restablecida!';
                    else echo 'Recuperar Contraseña';
                    ?>
                </h1>
                <p class="text-slate-600 text-sm">
                    <?php 
                    if ($step === 'verify') echo 'Ingresa el código enviado a tu correo.';
                    elseif ($step === 'reset') echo 'Ingresa tu nueva contraseña.';
                    elseif ($step === 'complete') echo 'Tu cuenta está lista.';
                    else echo 'Ingresa tu correo para recibir un código.';
                    ?>
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
                        <p class="font-bold text-blue-800">Información</p>
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
                        <p class="font-bold text-green-800">¡Éxito!</p>
                        <p class="text-green-700 text-sm"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
                <a href="login.php" class="mt-4 inline-block w-full text-center py-3 px-4 rounded-xl text-lg font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                    Iniciar Sesión
                </a>
            </div>
            
            <?php elseif ($step === 'verify'): ?>
            <!-- FORMULARIO DE VERIFICACIÓN -->
            <form method="POST" action="forgot_password.php" class="space-y-6">
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
                    Verificar Código
                    <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </button>

                <div class="text-center">
                    <a href="forgot_password.php?reset=true" class="text-sm text-blue-600 hover:text-indigo-600 transition-colors">
                        ← Volver al inicio
                    </a>
                </div>
            </form>

            <?php elseif ($step === 'reset'): ?>
            <!-- FORMULARIO DE NUEVA CONTRASEÑA -->
            <form method="POST" action="forgot_password.php" autocomplete="off" class="space-y-6" x-data="{ 
                password: '', 
                confirm_password: '', 
                showPassword: false, 
                showConfirmPassword: false
            }">
                
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">Nueva Contraseña</label>
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
                </div>
                
                <button type="submit" name="reset_password"
                    :disabled="password.length < 8 || password !== confirm_password"
                    :class="{ 'opacity-50 cursor-not-allowed': password.length < 8 || password !== confirm_password }"
                    class="group relative w-full flex justify-center items-center py-4 px-6 border border-transparent rounded-xl text-lg font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-500/50 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl disabled:hover:scale-100">
                    Restablecer Contraseña
                    <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </button>
            </form>
            
            <?php else: ?>
            <!-- FORMULARIO DE SOLICITUD -->
            <form method="POST" action="forgot_password.php" autocomplete="off" class="space-y-6" x-data="{ email: '' }">
                
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">Correo Electrónico</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <input type="email" id="email" name="email" autocomplete="off" required x-model="email" placeholder="tu.correo@ejemplo.com"
                            class="block w-full pl-12 pr-4 py-3.5 text-slate-900 bg-white border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-200 placeholder:text-slate-400">
                    </div>
                </div>
                
                <button type="submit" name="send_code"
                    :disabled="!email"
                    :class="{ 'opacity-50 cursor-not-allowed': !email }"
                    class="group relative w-full flex justify-center items-center py-4 px-6 border border-transparent rounded-xl text-lg font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-500/50 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl disabled:hover:scale-100">
                    Enviar Código de Recuperación
                    <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-8 text-center">
                <p class="text-slate-600">
                    <a href="login.php" class="font-bold text-blue-600 hover:text-indigo-600 transition-colors ml-1">
                        Volver al inicio de sesión
                    </a>
                </p>
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

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.animate-blob {
    animation: blob 7s infinite;
}

.animate-slide-in-left {
    animation: slideInLeft 0.6s ease-out;
}

.animate-shake {
    animation: shake 0.5s ease-in-out;
}

.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
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
