<?php 
$page_title = 'Buzón de Quejas - ITSCC'; 
include 'components/header.php'; 
?>

<!-- Phosphor Icons CDN -->
<script src="https://unpkg.com/@phosphor-icons/web"></script>

<style>
@keyframes blob {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(20px, -50px) scale(1.1); }
    50% { transform: translate(-20px, 20px) scale(0.9); }
    75% { transform: translate(50px, 50px) scale(1.05); }
}

@keyframes gradient {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes bounce-slow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-blob {
    animation: blob 7s infinite;
}

.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

.animate-gradient {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}

.animate-float {
    animation: float 3s ease-in-out infinite;
}

.animate-bounce-slow {
    animation: bounce-slow 2s ease-in-out infinite;
}

.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

.card-hover-effect {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-hover-effect:hover {
    transform: translateY(-4px);
}
</style>

<!-- Hero Section with Animated Background -->
<section class="relative pt-24 pb-20 overflow-hidden">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        <div class="absolute top-0 left-0 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-purple-400/20 rounded-full blur-3xl animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-0 left-1/2 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl animate-blob animation-delay-4000"></div>
    </div>
    
    <div class="container mx-auto px-4 relative z-10">
        <div class="text-center animate-fade-in-up">
            <!-- Logo with Glow Effect -->
            <div class="inline-block relative mb-8">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full blur-2xl opacity-30 animate-pulse-slow"></div>
                <div class="relative p-6 bg-white rounded-3xl shadow-2xl">
                    <img src="assets/logo.png" alt="ITSCC Logo" class="h-20 w-20 mx-auto">
                </div>
            </div>
            
            <!-- Title with Gradient -->
            <h1 class="text-5xl md:text-7xl font-black mb-4 leading-tight">
                <span class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 bg-clip-text text-transparent animate-gradient">
                    Buzón Digital XD
                </span>
            </h1>
            <h2 class="text-xl md:text-2xl font-semibold text-slate-700 mb-3">
                Quejas, Sugerencias y Felicitaciones
            </h2>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-6">
                Instituto Tecnológico Superior de Ciudad Constitución
            </p>
            
            <!-- Decorative Line -->
            <div class="flex justify-center items-center space-x-2">
                <div class="h-px w-16 bg-gradient-to-r from-transparent to-blue-500"></div>
                <div class="h-2 w-2 bg-blue-500 rounded-full animate-pulse"></div>
                <div class="h-px w-16 bg-gradient-to-l from-transparent to-purple-500"></div>
            </div>
        </div>
    </div>
</section>

<!-- Recent Reports Section -->
<section class="relative z-10 -mt-8 mb-20">
    <div class="container mx-auto px-4">
        <!-- Section Header -->
        <div class="text-center mb-12 animate-fade-in-up" style="animation-delay: 0.2s;">
            <div class="inline-flex items-center space-x-3 bg-white/80 backdrop-blur-sm rounded-full px-6 py-3 shadow-lg mb-4">
                <div class="h-2 w-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm font-semibold text-slate-700 uppercase tracking-wider flex items-center">
                    <i class="ph-bold ph-broadcast mr-2"></i>
                    En Tiempo Real
                </span>
            </div>
            <h3 class="text-3xl md:text-4xl font-bold text-slate-800 mb-3">
                Reportes Recientes por Categoría
            </h3>
            <p class="text-slate-600 text-lg flex items-center justify-center">
                <i class="ph ph-calendar-blank mr-2"></i>
                Actividad en los últimos 30 días
            </p>
        </div>
        
        <!-- Compact Report Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 animate-fade-in-up" style="animation-delay: 0.3s;">
            <?php
            $stmt = $conn->prepare("SELECT COALESCE(c.category_id, 0) as category_id, 
                                         cat.name as category_name,
                                         COUNT(*) as count 
                                  FROM complaints c
                                  LEFT JOIN categories cat ON c.category_id = cat.id 
                                  WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                  GROUP BY c.category_id, cat.name 
                                  ORDER BY c.category_id IS NULL, count DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $category_info = [
                0  => ['from' => 'from-gray-500',    'to' => 'to-slate-500',   'icon' => 'ph-file-text'],
                1  => ['from' => 'from-blue-500',    'to' => 'to-cyan-500',    'icon' => 'ph-wifi-high'],
                2  => ['from' => 'from-indigo-500',  'to' => 'to-purple-500',  'icon' => 'ph-chair'],
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

            while ($row = $result->fetch_assoc()) {
                $category_name = $row['category_id'] == 0 ? "Sin Categoría" : 
                               ($row['category_name'] ?? "Categoría Desconocida");
                
                $info = $category_info[$row['category_id']] ?? $default_info;
            ?>
            <!-- Compact Horizontal Card -->
            <div class="group relative bg-white rounded-2xl shadow-md hover:shadow-xl card-hover-effect overflow-hidden border border-slate-100">
                <!-- Gradient Background -->
                <div class="absolute inset-0 bg-gradient-to-br <?php echo $info['from'] . ' ' . $info['to']; ?> opacity-5 group-hover:opacity-10 transition-opacity"></div>
                
                <!-- Content -->
                <div class="relative p-4 flex items-center space-x-4">
                    <!-- Icon Circle -->
                    <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br <?php echo $info['from'] . ' ' . $info['to']; ?> rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                        <i class="<?php echo $info['icon']; ?> ph-fill text-white text-2xl"></i>
                    </div>
                    
                    <!-- Text Content -->
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-bold text-slate-700 mb-1 truncate group-hover:text-slate-900 transition-colors" title="<?php echo htmlspecialchars($category_name); ?>">
                            <?php echo htmlspecialchars($category_name); ?>
                        </h4>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-black bg-gradient-to-r <?php echo $info['from'] . ' ' . $info['to']; ?> bg-clip-text text-transparent">
                                <?php echo $row['count']; ?>
                            </span>
                            <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">reportes</span>
                        </div>
                    </div>
                    
                    <!-- Arrow Indicator -->
                    <div class="flex-shrink-0">
                        <i class="ph-bold ph-caret-right text-slate-400 group-hover:text-slate-600 group-hover:translate-x-1 transition-all text-xl"></i>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>

<!-- Main CTA Section -->
<main class="container mx-auto px-4 pb-20">
    <div class="max-w-5xl mx-auto animate-fade-in-up" style="animation-delay: 0.4s;">
        <!-- Large CTA Card -->
        <div class="relative bg-gradient-to-br from-white to-blue-50/50 rounded-3xl shadow-2xl overflow-hidden border border-blue-100">
            <!-- Decorative Elements -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-blue-400/10 to-purple-400/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-br from-indigo-400/10 to-pink-400/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10 p-12 md:p-16 text-center">
                <?php if (isLoggedIn()): ?>
                <!-- Logged In State -->
                <div class="mb-10">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-xl mb-6 animate-bounce-slow">
                        <i class="ph-fill ph-check-circle text-white text-5xl"></i>
                    </div>
                    <h3 class="text-3xl md:text-4xl font-black text-slate-800 mb-4">
                        ¡Bienvenido de vuelta!
                    </h3>
                    <p class="text-lg md:text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed">
                        Tu voz es importante para nosotros. Comparte tus sugerencias o reporta cualquier situación para ayudarnos a mejorar nuestra institución.
                    </p>
                </div>
                
                <!-- Primary Action Button -->
                <a href="submit_complaint.php" class="group inline-flex items-center px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-lg rounded-2xl shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3 group-hover:rotate-90 transition-transform duration-300">
                        <i class="ph-bold ph-plus text-xl"></i>
                    </div>
                    <span>Crear Nuevo Reporte</span>
                    <i class="ph-bold ph-arrow-right ml-3 text-xl group-hover:translate-x-2 transition-transform duration-300"></i>
                </a>
                
                <?php else: ?>
                <!-- Not Logged In State -->
                <div class="mb-10">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl shadow-xl mb-6 animate-float">
                        <i class="ph-fill ph-lock-key text-white text-5xl"></i>
                    </div>
                    <h3 class="text-3xl md:text-4xl font-black text-slate-800 mb-4">
                        Accede a tu cuenta
                    </h3>
                    <p class="text-lg md:text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed">
                        Inicia sesión para acceder al sistema de reportes. Tu participación es fundamental para crear un mejor ambiente educativo.
                    </p>
                </div>
                
                <!-- Login Button -->
                <a href="login.php" class="group inline-flex items-center px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-lg rounded-2xl shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3 group-hover:rotate-12 transition-transform duration-300">
                        <i class="ph-bold ph-sign-in text-xl"></i>
                    </div>
                    <span>Iniciar Sesión</span>
                    <i class="ph-bold ph-arrow-right ml-3 text-xl group-hover:translate-x-2 transition-transform duration-300"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Features Section -->
<section class="bg-gradient-to-b from-slate-50 to-white py-24">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16 animate-fade-in-up" style="animation-delay: 0.5s;">
            <div class="inline-block px-4 py-2 bg-blue-100 rounded-full mb-4">
                <span class="text-sm font-semibold text-blue-600 uppercase tracking-wider flex items-center">
                    <i class="ph-bold ph-star-four mr-2"></i>
                    Características
                </span>
            </div>
            <h3 class="text-3xl md:text-4xl font-bold text-slate-800 mb-4">
                ¿Por qué usar nuestro sistema?
            </h3>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto">
                Un canal de comunicación confiable, seguro y eficiente
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <!-- Feature 1 -->
            <div class="group relative bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl card-hover-effect border border-slate-100 animate-fade-in-up" style="animation-delay: 0.6s;">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-indigo-500/5 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 shadow-lg">
                        <i class="ph-fill ph-lock-key text-white text-3xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-slate-800 mb-3">Seguro y Confidencial</h4>
                    <p class="text-slate-600 leading-relaxed">Tus reportes están protegidos con encriptación de nivel bancario y se manejan con total confidencialidad.</p>
                </div>
            </div>
            
            <!-- Feature 2 -->
            <div class="group relative bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl card-hover-effect border border-slate-100 animate-fade-in-up" style="animation-delay: 0.7s;">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative">
                    <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 shadow-lg">
                        <i class="ph-fill ph-lightning text-white text-3xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-slate-800 mb-3">Respuesta Rápida</h4>
                    <p class="text-slate-600 leading-relaxed">Procesamos y respondemos todos los reportes con la mayor agilidad posible para resolver tu situación.</p>
                </div>
            </div>
            
            <!-- Feature 3 -->
            <div class="group relative bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl card-hover-effect border border-slate-100 animate-fade-in-up" style="animation-delay: 0.8s;">
                <div class="absolute inset-0 bg-gradient-to-br from-sky-500/5 to-cyan-500/5 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative">
                    <div class="w-16 h-16 bg-gradient-to-br from-sky-500 to-sky-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 shadow-lg">
                        <i class="ph-fill ph-clipboard-text text-white text-3xl"></i>
                    </div>
                    <h4 class="text-xl font-bold text-slate-800 mb-3">Seguimiento Completo</h4>
                    <p class="text-slate-600 leading-relaxed">Monitorea el progreso de tu reporte en tiempo real desde el envío hasta la resolución final.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'components/footer.php'; ?>

</body>
</html>