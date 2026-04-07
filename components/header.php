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

// Global variable to show/hide decorative blobs (can be overridden in individual pages)
if (!isset($show_global_blobs)) {
    $show_global_blobs = true;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Buzón de Quejas'; ?></title>
    
    <link rel="icon" href="./assets/logo.png" type="image/png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="css/output.css">

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
        // Check theme immediately to prevent FOUC
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        window.toggleDarkMode = function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        };
    </script>
    
    <style>
        [x-cloak] { display: none !important; }
        :root {
            --glass-opacity: 0.8;
            --glass-border: rgba(255, 255, 255, 0.05);
            --glass-inner-opacity: 0.05;
            
            --glass-inner-blur: 20px;
            --glass-blur: 5px;

            /* Background image controls */
            --bg-opacity: 0.7;
            --bg-blur: 3px;
            --bg-brightness: 1;
        }

        .dark {
            --glass-opacity: 0.8;
            --glass-border: rgba(255, 255, 255, 0.05);
            --glass-inner-opacity: 0.05;

            /* Background image controls (dark) */
            --bg-opacity: 0.35;
            --bg-blur: 3px;
            --bg-brightness: 1;
        }

        /* Institutional background image utility class */
        .bg-institutional {
            opacity: var(--bg-opacity);
            filter: blur(var(--bg-blur)) brightness(var(--bg-brightness));
            transition: opacity 0.7s ease, filter 0.7s ease;
            background-image: url('assets/tecnm.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Global Liquid Glass Utility Classes */
        .liquid-glass {
            background: rgba(255, 255, 255, var(--glass-opacity)) !important;
            backdrop-filter: blur(var(--glass-blur)) saturate(180%) !important;
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(180%) !important;
            border: 1px solid var(--glass-border) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07), 
                        inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .glass-inner {
            background: rgba(255, 255, 255, var(--glass-inner-opacity)) !important;
            backdrop-filter: blur(var(--glass-inner-blur)) !important;
            -webkit-backdrop-filter: blur(var(--glass-inner-blur)) !important;
            border: 1px solid var(--glass-border) !important;
        }

        .dark .liquid-glass {
            background: rgba(15, 23, 42, var(--glass-opacity)) !important;
        }

        .dark .glass-inner {
            background: rgba(255, 255, 255, var(--glass-inner-opacity)) !important;
        }

        /* GLOBAL DARK MODE OVERRIDES */
        html.dark body { background-color: #0f172a !important; color: #e2e8f0 !important; }
        html.dark .bg-white, html.dark .bg-slate-50, html.dark .bg-gray-50 {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            background-image: none !important;
        }
        
        /* Soft tinted backgrounds for specific colors in dark mode instead of full slate */
        html.dark .bg-blue-50, html.dark .bg-blue-100, html.dark [class*="bg-blue-50/"], html.dark [class*="bg-blue-100/"] { background-color: rgba(59, 130, 243, 0.15) !important; border-color: rgba(59, 130, 243, 0.2) !important; color: #93c5fd !important; background-image: none !important; }
        html.dark .bg-indigo-50, html.dark .bg-indigo-100, html.dark [class*="bg-indigo-50/"], html.dark [class*="bg-indigo-100/"] { background-color: rgba(99, 102, 241, 0.15) !important; border-color: rgba(99, 102, 241, 0.2) !important; background-image: none !important; }
        html.dark .bg-purple-50, html.dark .bg-purple-100, html.dark [class*="bg-purple-50/"], html.dark [class*="bg-purple-100/"] { background-color: rgba(168, 85, 247, 0.15) !important; border-color: rgba(168, 85, 247, 0.2) !important; background-image: none !important; }
        html.dark .bg-emerald-50, html.dark .bg-green-50, html.dark .bg-emerald-100, html.dark .bg-green-100, html.dark [class*="bg-emerald-50/"], html.dark [class*="bg-green-50/"], html.dark [class*="bg-emerald-100/"], html.dark [class*="bg-green-100/"], html.dark .text-emerald-50 { background-color: rgba(16, 185, 129, 0.15) !important; border-color: rgba(16, 185, 129, 0.2) !important; color: #6ee7b7 !important; background-image: none !important; }
        html.dark .bg-amber-50, html.dark .bg-orange-50, html.dark .bg-yellow-50, html.dark .bg-amber-100, html.dark .bg-orange-100, html.dark .bg-yellow-100, html.dark [class*="bg-amber-50/"], html.dark [class*="bg-orange-50/"], html.dark [class*="bg-yellow-50/"], html.dark [class*="bg-amber-100/"], html.dark [class*="bg-orange-100/"], html.dark [class*="bg-yellow-100/"] { background-color: rgba(245, 158, 11, 0.15) !important; border-color: rgba(245, 158, 11, 0.2) !important; color: #fcd34d !important; background-image: none !important; }
        html.dark .bg-red-50, html.dark .bg-rose-50, html.dark .bg-red-100, html.dark .bg-rose-100, html.dark [class*="bg-red-50/"], html.dark [class*="bg-rose-50/"], html.dark [class*="bg-red-100/"], html.dark [class*="bg-rose-100/"] { background-color: rgba(239, 68, 68, 0.15) !important; border-color: rgba(239, 68, 68, 0.2) !important; color: #fca5a5 !important; background-image: none !important; }
        
        /* Gradients overrides - using precise classes to avoid matching blue-500 with blue-50* wildcard */
        html.dark .from-blue-50, html.dark .from-slate-50, html.dark .from-green-50, html.dark .from-amber-50, html.dark .from-indigo-50, html.dark .from-blue-100, html.dark [class*="from-blue-50/"], html.dark [class*="from-blue-100/"] { 
            background-color: #0f172a !important; 
            background-image: none !important; 
        }
        
        /* Body overrides just in case tailwind rules try to win via specificity */
        html.dark body { 
            background-color: #0f172a !important; 
            background-image: none !important;
            color: #e2e8f0 !important;
        }

        /* Typography overrides */
        html.dark .text-slate-900, html.dark .text-gray-800, html.dark .text-gray-900, html.dark .text-gray-700 { color: #f8fafc !important; }
        html.dark .text-slate-800, html.dark .text-slate-700, html.dark .text-slate-600, html.dark .text-gray-600 { color: #cbd5e1 !important; }
        html.dark .text-gray-500 { color: #94a3b8 !important; }

        html.dark .text-amber-900, html.dark .text-amber-800, html.dark .text-amber-700 { color: #fde68a !important; }
        html.dark .text-blue-900, html.dark .text-blue-800, html.dark .text-blue-700 { color: #bfdbfe !important; }
        html.dark .text-green-900, html.dark .text-green-800, html.dark .text-green-700, html.dark .text-green-600, html.dark .text-emerald-800, html.dark .text-emerald-700, html.dark .text-emerald-600 { color: #6ee7b7 !important; }
        html.dark .text-purple-900, html.dark .text-purple-800, html.dark .text-purple-700 { color: #d8b4fe !important; }
        html.dark .text-orange-900, html.dark .text-orange-800, html.dark .text-orange-700 { color: #ffb366 !important; }
        html.dark .text-red-900, html.dark .text-red-800, html.dark .text-red-700 { color: #fca5a5 !important; }
        
        nav .bg-gradient-to-r,
        footer,
        .blue-footer,
        nav > div:first-child {
            background: linear-gradient(to right, #2563eb, #1e40af) !important;
            transition: background 0.5s ease-out !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        html.dark nav .bg-gradient-to-r,
        html.dark footer,
        html.dark .blue-footer,
        html.dark nav > div:first-child {
            background: linear-gradient(to right, #111827, #0f172a) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        /* Dim footer blobs in dark mode - slightly more visible now */
        html.dark footer .bg-gradient-to-br,
        html.dark footer .bg-gradient-to-tl {
            opacity: 0.15 !important;
        }

        /* Mobile Menu Dark Mode */
        html.dark .mobile-menu-glass {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        
        html.dark .mobile-profile-card {
            background: rgba(51, 65, 85, 0.4) !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }
        
        html.dark .mobile-menu-item-gradient {
            background: rgba(51, 65, 85, 0.2) !important;
        }
        
        html.dark .mobile-menu-item-gradient:hover {
            background: rgba(51, 65, 85, 0.4) !important;
        }

        /* Borders & Special Containers */
        html.dark .border-gray-100, html.dark .border-gray-200, html.dark .border-slate-200, html.dark .border-gray-300 { border-color: #334155 !important; }
        html.dark .border-gray-50 { border-color: transparent !important; }
        
        html.dark .glass-card, html.dark .glass-effect {
            background: rgba(30, 41, 59, 0.7) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Forms & Inputs & TomSelect */
        html.dark input[type="text"], html.dark input[type="password"], html.dark input[type="email"], html.dark select, html.dark textarea,
        html.dark .ts-control, html.dark .ts-dropdown, html.dark .ts-dropdown .option {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #475569 !important;
            box-shadow: none !important;
        }
        html.dark .ts-wrapper.multi .ts-control > div {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        html.dark .ts-dropdown .active { background-color: #1e293b !important; color: #60a5fa !important; }
        
        html.dark input::-webkit-input-placeholder, html.dark textarea::-webkit-input-placeholder { color: #64748b !important; }
        html.dark input::placeholder, html.dark textarea::placeholder { color: #64748b !important; }
        html.dark input:focus, html.dark select:focus, html.dark textarea:focus, html.dark .ts-control.focus {
            border-color: #60a5fa !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5) !important;
            --tw-ring-color: transparent !important;
        }
        
        /* Checkboxes */
        html.dark input[type="checkbox"] {
            background-color: #1e293b !important;
            border-color: #475569 !important;
            border-width: 2px !important;
            color: #3b82f6 !important;
        }

        /* Dropdowns, Buttons & States */
        html.dark .bg-gray-100, html.dark .bg-gray-200 { background-color: #334155 !important; color: #f8fafc !important; border-color: #475569 !important; }
        html.dark .hover\:bg-gray-100:hover, html.dark .hover\:bg-gray-200:hover, html.dark .hover\:bg-gray-300:hover { background-color: #334155 !important; color: #ffffff !important; }
        html.dark .hover\:bg-gray-50:hover { background-color: #334155 !important; }
        html.dark .hover\:bg-red-50:hover { background-color: rgba(239, 68, 68, 0.2) !important; }
        html.dark .hover\:bg-blue-50:hover { background-color: rgba(59, 130, 246, 0.2) !important; }
        html.dark .hover\:bg-indigo-50:hover { background-color: rgba(99, 102, 241, 0.2) !important; }
        .icon-sun  { display: block; }
        .icon-moon { display: none;  }
        html.dark .icon-sun  { display: none;  }
        html.dark .icon-moon { display: block; }
        
        /* Shadows adjustment */
        html.dark .shadow-xl, html.dark .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.7), 0 4px 6px -2px rgba(0, 0, 0, 0.5) !important; }
        html.dark .shadow-sm, html.dark .shadow { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.5), 0 1px 2px -1px rgba(0, 0, 0, 0.3) !important; }

        * { font-family: 'Inter', sans-serif; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(-5deg); }
            66% { transform: translateY(-10px) rotate(5deg); }
        }
        
        .blob {
            position: absolute;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
            animation: float-slow 10s infinite ease-in-out alternate;
        }
        
        @keyframes float-slow {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 30px) scale(1.1); }
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
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen relative overflow-x-hidden flex flex-col">
    
<?php if ($show_global_blobs): ?>
<!-- ══════════════════════════════════
     GLOBAL FIXED BACKGROUND BLOBS
     ══════════════════════════════════ -->
<div class="fixed inset-0 pointer-events-none overflow-hidden" style="z-index: -5;">
    <div class="blob bg-blue-500 w-[500px] h-[500px] sm:w-96 sm:h-96 rounded-full top-[-5%] left-[-5%] mix-blend-multiply opacity-40 dark:opacity-50 dark:mix-blend-screen animate-float-slow"></div>
    <div class="blob bg-purple-500 w-[600px] h-[600px] sm:w-[500px] sm:h-[500px] rounded-full bottom-[-15%] right-[-15%] animation-delay-2000 mix-blend-multiply opacity-40 dark:opacity-50 dark:mix-blend-screen animate-float-slow"></div>
    <div class="blob bg-pink-500 w-[400px] h-[400px] sm:w-80 sm:h-80 rounded-full top-[30%] left-[35%] mix-blend-multiply opacity-25 dark:opacity-30 dark:mix-blend-screen animate-float-slow" style="animation-delay: 4000ms;"></div>
</div>
<?php endif; ?>

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
        <div class="shadow-2xl">
            <div class="container mx-auto px-4">
                <div class="flex justify-between items-center h-20">
                        <!-- Logo Section -->
                        <div class="flex items-center space-x-4 min-w-0">
                            <a href="index.php" class="flex items-center group min-w-0">
                                <div class="relative flex-shrink-0">
                                    <div class="absolute inset-0 bg-white rounded-xl blur-xl opacity-50 group-hover:opacity-70 transition-opacity"></div>
                                    <img src="assets/logo.png" alt="ITSCC Logo" class="relative h-14 w-auto transform group-hover:scale-105 transition-transform duration-300">
                                </div>
                                <div class="ml-3 min-w-0">
                                    <span class="text-white text-lg sm:text-xl lg:text-2xl font-bold tracking-tight whitespace-nowrap">Buzón de Quejas</span>
                                    <span class="block text-white/80 text-[11px] sm:text-xs lg:text-sm font-light whitespace-nowrap">TecNM - Ciudad Constitución</span>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Desktop Navigation -->
                        <div class="hidden md:flex items-center space-x-1 lg:space-x-2">
                            <!-- Dark Mode Toggle (Desktop) -->
                            <button onclick="toggleDarkMode()" 
                                    class="nav-item flex items-center justify-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 h-10 w-10 rounded-xl transition-all duration-300 group icon-animated leading-none" 
                                    title="Alternar Modo Oscuro">
                                <i class="ph-sun  text-xl icon-sun  leading-none"></i>
                                <i class="ph-moon text-xl icon-moon leading-none"></i>
                            </button>
                            <div class="h-8 w-px bg-white/20 mx-2"></div>
                            <?php if (isLoggedIn()): ?>
                                <?php if (canAccessDashboard()): ?>
                                    <a href="dashboard.php" class="nav-item flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-3 lg:px-5 py-2.5 rounded-xl transition-all duration-300 group" title="Dashboard">
                                        <i class="ph-chart-line text-xl lg:mr-2 icon-animated icon-wiggle-on-hover"></i>
                                        <span class="font-medium hidden lg:inline">Dashboard</span>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (isAdmin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager')): ?>
                                    <!-- Admin/Manager: icon-only buttons -->
                                    <a href="submit_complaint.php" class="nav-item flex items-center justify-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-2.5 rounded-xl transition-all duration-300 group" title="Nuevo Reporte">
                                        <i class="ph-plus-circle text-xl icon-animated icon-rotate-on-hover"></i>
                                    </a>
                                    
                                    <a href="my_complaints.php" class="nav-item relative flex items-center justify-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-2.5 rounded-xl transition-all duration-300 group" title="Mis Reportes">
                                        <i class="ph-folder-open text-xl icon-animated icon-bounce-on-hover"></i>
                                        <?php
                                        $unread_count = 0;
                                        if ($unread_count > 0): ?>
                                            <span class="notification-dot"></span>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <!-- Regular user: full buttons -->
                                    <a href="submit_complaint.php" class="nav-item flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-3 lg:px-5 py-2.5 rounded-xl transition-all duration-300 group" title="Nuevo Reporte">
                                        <i class="ph-plus-circle text-xl lg:mr-2 icon-animated icon-rotate-on-hover"></i>
                                        <span class="font-medium hidden lg:inline">Nuevo Reporte</span>
                                    </a>
                                    
                                    <a href="my_complaints.php" class="nav-item relative flex items-center text-white/90 hover:text-white bg-white/10 hover:bg-white/20 px-3 lg:px-5 py-2.5 rounded-xl transition-all duration-300 group" title="Mis Reportes">
                                        <i class="ph-folder-open text-xl lg:mr-2 icon-animated icon-bounce-on-hover"></i>
                                        <span class="font-medium hidden lg:inline">Mis Reportes</span>
                                        <?php
                                        $unread_count = 0;
                                        if ($unread_count > 0): ?>
                                            <span class="notification-dot"></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="h-8 w-px bg-white/20 mx-2"></div>
                                
                                <!-- User Dropdown -->
                                <div class="relative">
                                    <button @click="userDropdownOpen = !userDropdownOpen" 
                                            class="flex items-center space-x-3 text-white bg-white/10 hover:bg-white/20 px-4 py-2.5 rounded-xl transition-all duration-300">
                                        <?php if ($user_profile_photo): ?>
                                            <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center border-2 border-white/20 shadow-sm">
                                                <img src="data:image/jpeg;base64,<?php echo $user_profile_photo; ?>" 
                                                     alt="Profile" 
                                                     class="w-full h-full object-cover"
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center\'><span class=\'text-white font-bold text-sm\'><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span></div>';">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-sm">
                                                <span class="text-white font-bold text-sm">
                                                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <span class="font-medium"><?php echo htmlspecialchars(mb_strlen($_SESSION['name']) > 20 ? mb_substr($_SESSION['name'], 0, 20) . '...' : $_SESSION['name']); ?></span>
                                        <i class="ph-caret-down text-sm transition-transform icon-animated" :class="{'rotate-180': userDropdownOpen}"></i>
                                    </button>
                                    
                                    <!-- Dropdown Menu -->
                                    <div x-show="userDropdownOpen" x-cloak 
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

                            <?php endif; ?>
                        </div>
                        
                        <div class="md:hidden flex items-center space-x-3">
                            <button onclick="toggleDarkMode()" 
                                    class="flex items-center justify-center text-white bg-white/10 hover:bg-white/20 h-10 w-10 rounded-lg transition-colors leading-none">
                                <i class="ph-sun  text-xl icon-sun  leading-none"></i>
                                <i class="ph-moon text-xl icon-moon leading-none"></i>
                            </button>
                            <button @click="mobileMenuOpen = !mobileMenuOpen" 
                                    class="p-2 rounded-lg flex items-center justify-center text-white bg-white/10 hover:bg-white/20 transition-colors">
                                <span class="sr-only">Abrir menú principal</span>
                                <i class="ph-list-bold text-2xl icon-animated icon-pulse-soft" x-show="!mobileMenuOpen" x-cloak></i>
                                <i class="ph-x-bold text-2xl icon-animated icon-wiggle-on-hover" x-show="mobileMenuOpen" x-cloak></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile Menu -->
                <div x-show="mobileMenuOpen" x-cloak
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
                                            <div class="w-12 h-12 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 shadow-lg border-2 border-white/20">
                                                <img src="data:image/jpeg;base64,<?php echo $user_profile_photo; ?>" 
                                                     alt="Profile" 
                                                     class="w-full h-full object-cover"
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center\'><span class=\'text-white font-bold text-lg\'><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span></div>';">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center flex-shrink-0 shadow-lg">
                                                <span class="text-white font-bold text-lg">
                                                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4 flex-1 min-w-0">
                                            <div class="text-white text-sm font-semibold truncate"><?php echo htmlspecialchars(mb_strlen($_SESSION['name']) > 20 ? mb_substr($_SESSION['name'], 0, 20) . '...' : $_SESSION['name']); ?></div>
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
                                <div class="mt-6 pt-4 border-t border-white/10">
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