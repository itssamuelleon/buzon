<?php
// --- PHP LOGIC FIRST ---

require_once 'config.php';

// Capturar URL de redirección (para volver a la página original después del login)
$redirect_url = '';
if (isset($_GET['redirect'])) {
    $redirect_url = $_GET['redirect'];
} elseif (isset($_POST['redirect'])) {
    $redirect_url = $_POST['redirect'];
}
// Validar que sea una URL interna (prevenir open redirect)
if ($redirect_url && !preg_match('/^[a-zA-Z0-9_\-\/\.\?\=\&\%]+$/', $redirect_url)) {
    $redirect_url = '';
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . ($redirect_url ?: 'index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            // Check if user email belongs to any department and update role if not admin
            if ($user['role'] !== 'admin') {
                $dept_check = $conn->prepare("SELECT id FROM departments WHERE email = ?");
                $dept_check->bind_param("s", $email);
                $dept_check->execute();
                $dept_result = $dept_check->get_result();
                
                if ($dept_result->num_rows > 0) {
                    // User email matches a department, update role to manager
                    $update_role = $conn->prepare("UPDATE users SET role = 'manager' WHERE id = ?");
                    $update_role->bind_param("i", $user['id']);
                    $update_role->execute();
                    $user['role'] = 'manager'; // Update for session
                } else {
                    // User email does NOT match any department
                    if ($user['role'] === 'manager') {
                        // Demote from manager to student
                        $update_role = $conn->prepare("UPDATE users SET role = 'student' WHERE id = ?");
                        $update_role->bind_param("i", $user['id']);
                        $update_role->execute();
                        $user['role'] = 'student';
                    }
                }
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            header('Location: ' . ($redirect_url ?: 'index.php'));
            exit;
        }
    }
    $error = 'Email o contraseña incorrectos';
}
// --- END OF PHP LOGIC ---

// --- HTML PRESENTATION SECOND ---
$page_title = 'Iniciar Sesión - ITSCC Buzón'; 
include 'components/header.php'; 
?>

<!-- Alpine.js for interactive form validation -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Animated Background -->
<div class="min-h-screen relative overflow-hidden flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <!-- Gradient Background with Animated Blobs -->
    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute top-1/2 right-1/4 w-96 h-96 bg-purple-400/20 rounded-full blur-3xl animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-0 left-1/2 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl animate-blob animation-delay-4000"></div>
    </div>

    <!-- Main Container -->
    <div class="container mx-auto max-w-6xl relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            
            <!-- Left Side: Login Form -->
            <div class="order-1 lg:order-1">
                <div class="bg-white/90 backdrop-blur-xl rounded-3xl shadow-2xl p-8 md:p-12 border border-white/20 animate-slide-in-left">
                    <!-- Logo and Header -->
                    <div class="text-center mb-8">
                        <div class="inline-block relative mb-6">
                            <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full blur-xl opacity-30 animate-pulse-slow"></div>
                            <div class="relative p-4 bg-white rounded-2xl shadow-lg">
                                <img src="assets/logo.png" alt="ITSCC Logo" class="h-16 w-16 mx-auto">
                            </div>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-black bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-2">
                            Bienvenido
                        </h1>
                        <p class="text-slate-600 text-lg">Ingresa al Buzón Digital</p>
                    </div>

                    <!-- Error Message -->
                    <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 rounded-xl p-4 animate-shake">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="font-bold text-red-800">Error de Autenticación</p>
                                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div x-data="{ showManualLogin: <?php echo $error ? 'true' : 'false'; ?>, email: '', password: '', showPassword: false }">
                        
                        <!-- Microsoft Login Button (Primary) -->
                        <div class="mb-8">
                            <a href="login_microsoft.php<?php echo $redirect_url ? '?redirect=' . urlencode($redirect_url) : ''; ?>" 
                               class="w-full flex justify-center items-center py-4 px-6 border border-transparent rounded-xl shadow-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-lg font-bold hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-500/50 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98]">
                                <svg class="h-6 w-6 mr-3" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                                    <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                                    <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                                </svg>
                                <span>Ingresar con Cuenta Institucional</span>
                            </a>
                            <p class="text-center text-sm text-slate-500 mt-3">
                                Acceso unificado para estudiantes y personal del ITSCC
                            </p>
                        </div>

                        <!-- Divider / Toggle -->
                        <div class="relative my-6">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <button @click="showManualLogin = !showManualLogin" 
                                        class="px-4 py-1 bg-white text-slate-500 hover:text-blue-600 font-medium transition-colors focus:outline-none">
                                    <span x-text="showManualLogin ? 'Ocultar acceso manual' : 'Usar credenciales manuales'"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Manual Login Form (Hidden by default) -->
                        <div x-show="showManualLogin" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 transform -translate-y-4"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             x-cloak>
                            
                            <form method="POST" action="login.php<?php echo $redirect_url ? '?redirect=' . urlencode($redirect_url) : ''; ?>" class="space-y-6">
                                
                                <!-- Email Input -->
                                <div>
                                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">
                                        Correo Electrónico
                                    </label>
                                    <div class="relative group">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                        <input 
                                            type="email" 
                                            id="email" 
                                            name="email" 
                                            required
                                            x-model="email"
                                            placeholder="tucorreo@ejemplo.com"
                                            class="block w-full pl-12 pr-4 py-3.5 text-slate-900 bg-white border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-200 placeholder:text-slate-400">
                                    </div>
                                </div>

                                <!-- Password Input -->
                                <div>
                                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">
                                        Contraseña
                                    </label>
                                    <div class="relative group">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-slate-400 group-focus-within:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                            </svg>
                                        </div>
                                        <input 
                                            :type="showPassword ? 'text' : 'password'"
                                            id="password" 
                                            name="password" 
                                            required
                                            x-model="password"
                                            placeholder="••••••••"
                                            class="block w-full pl-12 pr-12 py-3.5 text-slate-900 bg-white border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all duration-200 placeholder:text-slate-400">
                                        <button 
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                            <svg x-show="!showPassword" class="h-5 w-5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            <svg x-show="showPassword" class="h-5 w-5 text-slate-400 hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex justify-end mt-2 hidden">
                                        <a href="forgot_password.php" class="text-sm font-medium text-blue-600 hover:text-indigo-600 transition-colors">
                                            ¿Olvidaste tu contraseña?
                                        </a>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button 
                                    type="submit"
                                    class="group relative w-full flex justify-center items-center py-4 px-6 border border-transparent rounded-xl text-lg font-bold text-white bg-slate-800 hover:bg-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-500/50 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl">
                                    <svg class="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                                    </svg>
                                    Iniciar Sesión
                                    <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Welcome Message -->
            <div class="order-2 lg:order-2 text-center lg:text-left animate-slide-in-right">
                <div class="relative">
                    <!-- Decorative Elements -->
                    <div class="absolute -top-10 -left-10 w-72 h-72 bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-full blur-3xl"></div>
                    
                    <div class="relative bg-white/40 backdrop-blur-lg rounded-3xl p-12 border border-white/50 shadow-2xl">
                        <!-- Icon Grid -->
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

                        <!-- Text Content -->
                        <h2 class="text-4xl md:text-5xl font-black text-slate-800 mb-6 leading-tight">
                            Tu Voz<br/>
                            <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                                Importa
                            </span>
                        </h2>
                        <p class="text-lg text-slate-600 leading-relaxed mb-8">
                            Reporta incidencias, comparte sugerencias y contribuye a construir una mejor institución educativa para toda la comunidad.
                        </p>

                        <!-- Stats -->
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