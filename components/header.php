<?php 
require_once __DIR__ . '/../config.php';

// Actualizar estados de reportes automáticamente en cada carga de página
// Solo se actualizan los reportes que están sin atender (eficiente)
require_once __DIR__ . '/../update_statuses.php';
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
    <nav class="relative z-50" x-data="{ mobileMenuOpen: false, userDropdownOpen: false }">
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
                                <a href="dashboard.php" class="nav-item flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-5 py-2.5 rounded-xl transition-all duration-300 group">
                                    <i class="ph-chart-line text-xl mr-2 icon-animated icon-wiggle-on-hover"></i>
                                    <span class="font-medium">Reportes</span>
                                </a>
                                
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
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                                            </span>
                                        </div>
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
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute top-20 inset-x-0 p-2 md:hidden">
                    <div class="rounded-lg shadow-lg ring-1 ring-black/5 bg-gradient-to-br from-blue-700 to-indigo-800 divide-y divide-white/10">
                        <div class="px-5 pt-5 pb-6">
                            <?php if (isLoggedIn()): ?>
                                <!-- User Profile Section -->
                                <div class="mb-6">
                                    <div class="flex items-center p-3 bg-white/10 rounded-lg">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center">
                                            <span class="text-white font-bold">
                                                <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-white text-sm font-medium"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                                            <div class="text-white/60 text-xs"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Links -->
                                <div class="space-y-2">
                                    <a href="dashboard.php" class="flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-4 py-3 rounded-lg transition-all group">
                                        <i class="ph-chart-line text-xl mr-3 icon-animated icon-wiggle-on-hover"></i>
                                        Reportes
                                    </a>
                                    
                                    <a href="submit_complaint.php" class="flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-4 py-3 rounded-lg transition-all group">
                                        <i class="ph-plus-circle text-xl mr-3 icon-animated icon-rotate-on-hover"></i>
                                        Nuevo Reporte
                                    </a>
                                    
                                    <a href="my_complaints.php" class="flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-4 py-3 rounded-lg transition-all group">
                                        <i class="ph-folder-open text-xl mr-3 icon-animated icon-bounce-on-hover"></i>
                                        Mis Reportes
                                    </a>

                                    <a href="profile.php" class="flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-4 py-3 rounded-lg transition-all group">
                                        <i class="ph-user-circle text-xl mr-3 icon-animated icon-pulse-soft"></i>
                                        Mi Perfil
                                    </a>
                                </div>

                                <!-- Logout Button -->
                                <div class="mt-6 pt-6 border-t border-white/10">
                                    <form action="logout.php" method="POST" class="block w-full">
                                        <button type="submit" 
                                                class="flex items-center justify-center w-full text-white bg-red-500/20 hover:bg-red-500/30 px-4 py-3 rounded-lg transition-all">
                                            <i class="ph-sign-out text-xl mr-2"></i>
                                            <span>Cerrar Sesión</span>
                                        </button>
                                    </form>
                                </div>
                                
                            <?php else: ?>
                                <div class="space-y-4">
                                    <a href="login.php" class="flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-4 py-3 rounded-lg transition-all group">
                                        <i class="ph-sign-in text-xl mr-3 icon-animated icon-bounce-on-hover"></i>
                                        Iniciar Sesión
                                    </a>
                                    
                                    <a href="register.php" class="flex items-center text-gray-800 bg-white hover:bg-gray-50 px-4 py-3 rounded-lg transition-all font-medium group">
                                        <i class="ph-user-plus text-xl mr-3 icon-animated icon-rotate-on-hover"></i>
                                        Registrarse
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