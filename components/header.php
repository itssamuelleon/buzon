<?php 
require_once __DIR__ . '/../config.php';

// Actualizar estados de reportes automáticamente en cada carga de página
// Solo se actualizan los reportes que están sin atender (eficiente)
require_once __DIR__ . '/../update_statuses.php';

// Obtener foto de perfil del usuario si está logueado
$user_profile_photo = null;
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    $stmt_photo = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt_photo->bind_param("i", $_SESSION['user_id']);
    $stmt_photo->execute();
    $result_photo = $stmt_photo->get_result();
    if ($row_photo = $result_photo->fetch_assoc()) {
        $user_profile_photo = $row_photo['profile_photo'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Buzón de Quejas - ITSCC'; ?></title>
    
    <link rel="icon" href="./assets/x-icon.png" type="image/png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Phosphor Icons -->
    <script src="https://cdn.jsdelivr.net/npm/phosphor-icons"></script>
    
    <!-- Alpine.js for interactions -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Tom Select for multi-select filters -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite',
                        'slide-down': 'slide-down 0.3s ease-out',
                        'bounce-slow': 'bounce 3s ease-in-out infinite',
                    },
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                        'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
                    }
                }
            }
        }
    </script>
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(-5deg); }
            66% { transform: translateY(-10px) rotate(5deg); }
        }
        
        @keyframes glow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        @keyframes slide-down {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slide-in-mobile {
            from { 
                opacity: 0; 
                transform: translateY(-20px) scale(0.95);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1);
            }
        }

        @keyframes shine {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        
        /* Icon animations */
        @keyframes icon-rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes icon-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        
        @keyframes icon-wiggle {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-8deg); }
            75% { transform: rotate(8deg); }
        }
        
        @keyframes icon-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.06); opacity: 0.9; }
        }
        
        .icon-animated { transition: transform 200ms ease, color 200ms ease, opacity 200ms ease; }
        .icon-rotate-on-hover:hover { animation: icon-rotate 0.6s ease forwards; }
        .icon-bounce-on-hover:hover { animation: icon-bounce 0.5s ease; }
        .icon-wiggle-on-hover:hover { animation: icon-wiggle 0.4s ease; }
        .icon-pulse-soft { animation: icon-pulse 2s ease-in-out infinite; }
        /* Trigger animations when hovering the whole button (parent has .group) */
        .group:hover .icon-rotate-on-hover { animation: icon-rotate 0.6s ease forwards; }
        .group:hover .icon-bounce-on-hover { animation: icon-bounce 0.5s ease; }
        .group:hover .icon-wiggle-on-hover { animation: icon-wiggle 0.4s ease; }
        
        @media (prefers-reduced-motion: reduce) {
            .icon-rotate-on-hover:hover,
            .icon-bounce-on-hover:hover,
            .icon-wiggle-on-hover:hover,
            .icon-pulse-soft { animation: none !important; transform: none !important; }
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .mobile-menu-glass {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.90) 0%, rgba(79, 70, 229, 0.90) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .mobile-menu-item-gradient {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .mobile-menu-item-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }

        .mobile-menu-item-gradient:hover::before {
            left: 100%;
        }

        .mobile-menu-item-gradient:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.08) 100%);
            transform: translateX(4px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .mobile-profile-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.05) 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .mobile-section-divider {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        }

        .logout-button-gradient {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.25) 0%, rgba(220, 38, 38, 0.25) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .logout-button-gradient:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.35) 0%, rgba(220, 38, 38, 0.35) 100%);
            box-shadow: 0 8px 16px rgba(239, 68, 68, 0.15);
        }
        
        .nav-item {
            position: relative;
            overflow: hidden;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .nav-item:hover::before {
            left: 100%;
        }
        
        .floating-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.5;
            animation: float 8s ease-in-out infinite;
        }
        
        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        
        .dropdown-enter {
            animation: slide-down 0.3s ease-out;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen relative overflow-x-hidden">
    
<?php 
// Define helper functions if not already defined
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}
?>

    <!-- Modern Navigation Bar -->
    <nav class="sticky top-0 z-50" x-data="{ mobileMenuOpen: false, userDropdownOpen: false }">
        <!-- Main Navigation -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 shadow-2xl">
            <div class="glass-effect">
                <div class="container mx-auto px-4">
                    <div class="flex justify-between items-center h-20">
                        <!-- Logo Section -->
                        <div class="flex items-center space-x-4">
                            <a href="index.php" class="flex items-center group">
                                <div class="relative">
                                    <div class="absolute inset-0 bg-white rounded-xl blur-xl opacity-50 group-hover:opacity-70 transition-opacity"></div>
                                    <img src="assets/logo.png" alt="ITSCC Logo" class="relative h-14 w-auto transform group-hover:scale-105 transition-transform duration-300">
                                </div>
                                <div class="ml-4">
                                    <span class="text-white text-2xl font-bold tracking-tight">Buzón de Quejas</span>
                                    <span class="block text-white/80 text-sm font-light">ITSCC</span>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Desktop Navigation -->
                        <div class="hidden md:flex items-center space-x-2">
                            <?php if (isLoggedIn()): ?>
                                <?php if (canAccessDashboard()): ?>
                                    <a href="dashboard.php" class="nav-item flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl transition-all duration-300 group">
                                        <i class="ph-chart-line text-xl mr-2 icon-animated icon-wiggle-on-hover"></i>
                                        <span class="font-medium">Dashboard</span>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="submit_complaint.php" class="nav-item flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl transition-all duration-300 group">
                                    <i class="ph-plus-circle text-xl mr-2 icon-animated icon-rotate-on-hover"></i>
                                    <span class="font-medium">Nuevo Reporte</span>
                                </a>
                                
                                <a href="my_complaints.php" class="nav-item relative flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl transition-all duration-300 group">
                                    <i class="ph-folder-open text-xl mr-2 icon-animated icon-bounce-on-hover"></i>
                                    <span class="font-medium">Mis Reportes</span>
                                    <?php
                                    // Check for unread responses (optional feature)
                                    $unread_count = 0; // You can implement this logic
                                    if ($unread_count > 0): ?>
                                        <span class="notification-dot"></span>
                                    <?php endif; ?>
                                </a>
                                
                                <div class="h-8 w-px bg-white/20 mx-2"></div>
                                
                                <!-- User Dropdown -->
                                <div class="relative">
                                    <button @click="userDropdownOpen = !userDropdownOpen" 
                                            class="flex items-center space-x-3 text-white bg-white/10 hover:bg-white/20 px-4 py-2.5 rounded-xl transition-all duration-300">
                                        <?php if ($user_profile_photo): ?>
                                            <div class="w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center border-2 border-white/20">
                                                <img src="data:image/jpeg;base64,<?php echo $user_profile_photo; ?>" 
                                                     alt="Profile" 
                                                     class="w-full h-full object-cover"
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'text-white font-semibold text-sm\'><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span>';">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center">
                                                <span class="text-white font-semibold text-sm">
                                                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                                        <i class="ph-caret-down text-sm transition-transform icon-animated" :class="{'rotate-180': userDropdownOpen}"></i>
                                    </button>
                                    
                                    <!-- Dropdown Menu -->
                                    <div x-show="userDropdownOpen" 
                                         @click.away="userDropdownOpen = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 transform scale-95"
                                         x-transition:enter-end="opacity-100 transform scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 transform scale-100"
                                         x-transition:leave-end="opacity-0 transform scale-95"
                                         class="absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-xl ring-1 ring-black/5 divide-y divide-gray-100">
                                        
                                        <div class="px-4 py-3">
                                            <p class="text-sm text-gray-500">Conectado como</p>
                                            <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                                        </div>
                                        
                                        <div class="py-1">
                                            <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                                <i class="ph-user-circle text-lg mr-2"></i>
                                                Mi Perfil
                                            </a>
                                            <?php if (isAdmin()): ?>
                                                <a href="admin_settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                                    <i class="ph-gear text-lg mr-2"></i>
                                                    Configuración Admin
                                                </a>
                                            <?php endif; ?>
                                            <form action="logout.php" method="POST">
                                                <button type="submit" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                    <i class="ph-sign-out text-lg mr-2"></i>
                                                    Cerrar Sesión
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <a href="login.php" class="nav-item flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl transition-all duration-300 group">
                                    <i class="ph-sign-in text-xl mr-2 group-hover:translate-x-1 transition-transform"></i>
                                    <span class="font-medium">Iniciar Sesión</span>
                                </a>
                                
                                <a href="register.php" class="flex items-center text-gray-800 bg-white hover:bg-gray-50 px-5 py-2.5 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105 group">
                                    <i class="ph-user-plus text-xl mr-2 icon-animated icon-rotate-on-hover"></i>
                                    <span class="font-semibold">Registrarse</span>
                                </a>

                            <?php endif; ?>
                        </div>
                        
                        <!-- Mobile Menu Button -->
                        <button @click="mobileMenuOpen = !mobileMenuOpen" 
                                class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-white bg-white/10 hover:bg-white/20 transition-colors">
                            <span class="sr-only">Abrir menú principal</span>
                            <i class="ph-list-bold text-2xl icon-animated icon-pulse-soft" x-show="!mobileMenuOpen" x-cloak></i>
                            <i class="ph-x-bold text-2xl icon-animated icon-wiggle-on-hover" x-show="mobileMenuOpen" x-cloak></i>
                        </button>
                    </div>
                </div>

                <!-- Mobile Menu -->
                <div x-show="mobileMenuOpen"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 -translate-y-3 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 -translate-y-3 scale-95"
                     class="absolute top-20 inset-x-0 p-2 md:hidden z-40">
                    <div class="mobile-menu-glass rounded-2xl">
                        <div class="px-6 py-6">
                            <?php if (isLoggedIn()): ?>
                                <!-- User Profile Section -->
                                <div class="mb-8">
                                    <div class="mobile-profile-card flex items-center p-4 rounded-xl transition-all">
                                        <?php if ($user_profile_photo): ?>
                                            <div class="w-12 h-12 rounded-lg overflow-hidden flex items-center justify-center flex-shrink-0 shadow-lg border-2 border-white/20">
                                                <img src="data:image/jpeg;base64,<?php echo $user_profile_photo; ?>" 
                                                     alt="Profile" 
                                                     class="w-full h-full object-cover"
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'text-white font-bold text-lg\'><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span>';">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center flex-shrink-0 shadow-lg">
                                                <span class="text-white font-bold text-lg">
                                                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4 flex-1 min-w-0">
                                            <div class="text-white text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                                            <div class="text-white/70 text-xs truncate"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Links -->
                                <div class="space-y-3">
                                    <?php if (canAccessDashboard()): ?>
                                        <a href="dashboard.php" class="mobile-menu-item-gradient flex items-center text-white/95 px-5 py-3.5 rounded-xl transition-all duration-300">
                                            <i class="ph-chart-line text-2xl mr-4 icon-animated icon-wiggle-on-hover text-blue-300"></i>
                                            <span class="font-medium">Dashboard</span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="submit_complaint.php" class="mobile-menu-item-gradient flex items-center text-white/95 px-5 py-3.5 rounded-xl transition-all duration-300">
                                        <i class="ph-plus-circle text-2xl mr-4 icon-animated icon-rotate-on-hover text-emerald-300"></i>
                                        <span class="font-medium">Nuevo Reporte</span>
                                    </a>
                                    
                                    <a href="my_complaints.php" class="mobile-menu-item-gradient flex items-center text-white/95 px-5 py-3.5 rounded-xl transition-all duration-300">
                                        <i class="ph-folder-open text-2xl mr-4 icon-animated icon-bounce-on-hover text-orange-300"></i>
                                        <span class="font-medium">Mis Reportes</span>
                                    </a>

                                    <a href="profile.php" class="mobile-menu-item-gradient flex items-center text-white/95 px-5 py-3.5 rounded-xl transition-all duration-300">
                                        <i class="ph-user-circle text-2xl mr-4 icon-animated icon-pulse-soft text-pink-300"></i>
                                        <span class="font-medium">Mi Perfil</span>
                                    </a>

                                    <?php if (isAdmin()): ?>
                                        <a href="admin_settings.php" class="mobile-menu-item-gradient flex items-center text-white/95 px-5 py-3.5 rounded-xl transition-all duration-300">
                                            <i class="ph-gear text-2xl mr-4 icon-animated icon-rotate-on-hover text-yellow-300"></i>
                                            <span class="font-medium">Configuración Admin</span>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Logout Button -->
                                <div class="mt-8 pt-6 mobile-section-divider">
                                    <form action="logout.php" method="POST" class="block w-full">
                                        <button type="submit" 
                                                class="logout-button-gradient flex items-center justify-center w-full text-red-100 px-5 py-3.5 rounded-xl transition-all duration-300 font-semibold hover:text-red-50">
                                            <i class="ph-sign-out text-2xl mr-3"></i>
                                            <span>Cerrar Sesión</span>
                                        </button>
                                    </form>
                                </div>
                                
                            <?php else: ?>
                                <div class="space-y-3">
                                    <a href="login.php" class="mobile-menu-item-gradient flex items-center text-white/95 px-5 py-3.5 rounded-xl transition-all duration-300">
                                        <i class="ph-sign-in text-2xl mr-4 icon-animated icon-bounce-on-hover text-blue-300"></i>
                                        <span class="font-medium">Iniciar Sesión</span>
                                    </a>
                                    
                                    <a href="register.php" class="flex items-center text-gray-900 bg-gradient-to-r from-blue-200 to-purple-200 hover:from-blue-300 hover:to-purple-300 px-5 py-3.5 rounded-xl transition-all duration-300 font-semibold shadow-lg hover:shadow-xl">
                                        <i class="ph-user-plus text-2xl mr-4 icon-animated icon-rotate-on-hover"></i>
                                        <span>Registrarse</span>
                                    </a>

                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Initialize Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });
    </script>