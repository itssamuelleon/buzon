<?php 
$page_title = 'Buzón de Quejas - ITSCC'; 
include 'components/header.php'; 
?>

<!-- Custom Styles for this page -->
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .glass-card:hover {
        background: rgba(255, 255, 255, 0.9);
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
    }
    .hero-gradient {
        background: radial-gradient(circle at 50% 0%, rgba(59, 130, 246, 0.15) 0%, rgba(255, 255, 255, 0) 50%),
                    radial-gradient(circle at 100% 0%, rgba(139, 92, 246, 0.1) 0%, rgba(255, 255, 255, 0) 50%);
    }
    .text-gradient {
        background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .blob {
        position: absolute;
        filter: blur(80px);
        z-index: -1;
        opacity: 0.6;
        animation: float-slow 10s infinite ease-in-out alternate;
    }
    @keyframes float-slow {
        0% { transform: translate(0, 0); }
        100% { transform: translate(20px, 20px); }
    }
    
    /* New Animations */
    @keyframes float-y {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    .animate-float-y {
        animation: float-y 4s ease-in-out infinite;
    }
    
    .step-connector::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 100%;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, #e2e8f0 50%, transparent 50%);
        background-size: 10px 100%;
        transform: translateY(-50%);
        z-index: -1;
    }
    @media (max-width: 768px) {
        .step-connector::after {
            display: none;
        }
    }
</style>

<!-- Hero Section -->
<section class="relative pt-16 pb-24 overflow-hidden hero-gradient">
    <!-- Decorative Blobs -->
    <div class="blob bg-blue-200 w-96 h-96 rounded-full top-0 left-0 -translate-x-1/2 -translate-y-1/2 mix-blend-multiply"></div>
    <div class="blob bg-purple-200 w-96 h-96 rounded-full bottom-0 right-0 translate-x-1/3 translate-y-1/3 animation-delay-2000 mix-blend-multiply"></div>
    <div class="blob bg-indigo-200 w-80 h-80 rounded-full top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 mix-blend-multiply opacity-40"></div>

    <div class="container mx-auto px-4 relative z-10 text-center">
        <div data-aos="fade-up" data-aos-duration="1000">
            <div class="inline-flex items-center space-x-2 py-1 px-3 rounded-full bg-blue-50 text-blue-600 text-sm font-semibold mb-8 border border-blue-100 shadow-sm animate-float-y">
                <i class="ph-fill ph-megaphone-simple"></i>
                <span>Sistema Oficial de Comunicación</span>
            </div>
            
            <h1 class="text-5xl md:text-7xl font-black text-slate-900 tracking-tight mb-6 leading-tight">
                Tu voz construye <br>
                <span class="text-gradient">nuestro futuro</span>
            </h1>
            
            <p class="text-xl text-slate-600 max-w-2xl mx-auto mb-10 leading-relaxed">
                Un espacio seguro, transparente y eficiente para compartir tus inquietudes, sugerencias y reconocimientos. Juntos hacemos del ITSCC una mejor institución.
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <?php if (isLoggedIn()): ?>
                    <a href="submit_complaint.php" class="group w-full sm:w-auto px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                        <i class="ph-bold ph-plus-circle text-xl group-hover:rotate-90 transition-transform duration-300"></i>
                        Crear Nuevo Reporte
                    </a>
                    <a href="dashboard.php" class="group w-full sm:w-auto px-8 py-4 bg-white text-slate-700 font-bold rounded-2xl shadow-md hover:shadow-lg border border-slate-100 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                        <i class="ph-bold ph-chart-line-up text-xl group-hover:scale-110 transition-transform duration-300"></i>
                        Ver Panel
                    </a>
                <?php else: ?>
                    <a href="login.php" class="group w-full sm:w-auto px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                        <i class="ph-bold ph-sign-in text-xl group-hover:translate-x-1 transition-transform duration-300"></i>
                        Iniciar Sesión
                    </a>
                    <a href="register.php" class="group w-full sm:w-auto px-8 py-4 bg-white text-slate-700 font-bold rounded-2xl shadow-md hover:shadow-lg border border-slate-100 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                        <i class="ph-bold ph-user-plus text-xl group-hover:scale-110 transition-transform duration-300"></i>
                        Registrarse
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- How it Works Section -->
<section class="py-20 bg-white relative z-10">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16" data-aos="fade-up">
            <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">¿Cómo funciona?</h2>
            <p class="text-slate-600 text-lg">Tu reporte en 3 simples pasos</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative max-w-5xl mx-auto">
            <!-- Step 1 -->
            <div class="relative text-center step-connector" data-aos="fade-up" data-aos-delay="0">
                <div class="w-24 h-24 mx-auto bg-blue-50 rounded-full flex items-center justify-center mb-6 relative z-10 border-4 border-white shadow-xl">
                    <i class="ph-duotone ph-sign-in text-4xl text-blue-600"></i>
                    <div class="absolute -top-2 -right-2 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold border-2 border-white">1</div>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Inicia Sesión</h3>
                <p class="text-slate-600 px-4">Accede a tu cuenta institucional para garantizar la seguridad y seguimiento.</p>
            </div>

            <!-- Step 2 -->
            <div class="relative text-center step-connector" data-aos="fade-up" data-aos-delay="150">
                <div class="w-24 h-24 mx-auto bg-indigo-50 rounded-full flex items-center justify-center mb-6 relative z-10 border-4 border-white shadow-xl">
                    <i class="ph-duotone ph-pencil-simple-line text-4xl text-indigo-600"></i>
                    <div class="absolute -top-2 -right-2 w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold border-2 border-white">2</div>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Crea tu Reporte</h3>
                <p class="text-slate-600 px-4">Describe la situación, selecciona una categoría y adjunta evidencia si es necesario.</p>
            </div>

            <!-- Step 3 -->
            <div class="relative text-center" data-aos="fade-up" data-aos-delay="300">
                <div class="w-24 h-24 mx-auto bg-emerald-50 rounded-full flex items-center justify-center mb-6 relative z-10 border-4 border-white shadow-xl">
                    <i class="ph-duotone ph-check-circle text-4xl text-emerald-600"></i>
                    <div class="absolute -top-2 -right-2 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold border-2 border-white">3</div>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Recibe Respuesta</h3>
                <p class="text-slate-600 px-4">Las autoridades revisarán tu caso y recibirás notificaciones sobre el progreso.</p>
            </div>
        </div>
    </div>
</section>

<!-- Stats / Categories Section -->
<section class="py-24 relative z-10 bg-slate-50">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 32px 32px;"></div>

    <div class="container mx-auto px-4 relative">
        <div class="text-center mb-16" data-aos="fade-up">
            <div class="inline-flex items-center space-x-2 text-blue-600 font-semibold mb-2">
                <i class="ph-fill ph-chart-bar"></i>
                <span class="uppercase tracking-wider text-sm">Estadísticas</span>
            </div>
            <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Transparencia en tiempo real</h2>
            <p class="text-slate-600 text-lg">Actividad registrada en los últimos 30 días</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
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
            
            // Using ph-fill for solid icons as requested/consistent with previous design
            $category_info = [
                0  => ['from' => 'from-gray-500',    'to' => 'to-slate-500',   'icon' => 'ph-file-text'],
                1  => ['from' => 'from-blue-500',    'to' => 'to-cyan-500',    'icon' => 'ph-wifi-high'],
                2  => ['from' => 'from-indigo-500',  'to' => 'to-purple-500',  'icon' => 'ph-chalkboard-teacher'],
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
                15 => ['from' => 'from-sky-500',     'to' => 'to-cyan-600',    'icon' => 'ph-headphones'],
                16 => ['from' => 'from-violet-500',  'to' => 'to-purple-600',  'icon' => 'ph-megaphone'],
                17 => ['from' => 'from-red-600',     'to' => 'to-rose-700',    'icon' => 'ph-prohibit'],
                18 => ['from' => 'from-red-500',     'to' => 'to-orange-600',  'icon' => 'ph-warning'],
                19 => ['from' => 'from-green-600',   'to' => 'to-emerald-700', 'icon' => 'ph-shield-check'],
                20 => ['from' => 'from-pink-500',    'to' => 'to-fuchsia-600', 'icon' => 'ph-target'],
            ];
            $default_info = ['from' => 'from-gray-500', 'to' => 'to-slate-500', 'icon' => 'ph-file-text'];

            $delay = 0;
            while ($row = $result->fetch_assoc()) {
                $category_name = $row['category_id'] == 0 ? "Sin Categoría" : ($row['category_name'] ?? "Categoría Desconocida");
                $info = $category_info[$row['category_id']] ?? $default_info;
                $delay += 50;
            ?>
                <div class="glass-card rounded-2xl p-6 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?php echo $info['from']; ?> <?php echo $info['to']; ?> flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <!-- Added ph-fill class explicitly for solid icons -->
                            <i class="<?php echo $info['icon']; ?> text-white ph-fill text-2xl"></i>
                        </div>
                        <span class="text-3xl font-black text-slate-800"><?php echo $row['count']; ?></span>
                    </div>
                    <h3 class="font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($category_name); ?>">
                        <?php echo htmlspecialchars($category_name); ?>
                    </h3>
                    <div class="flex items-center mt-2 text-xs font-medium text-slate-400">
                        <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                        Activos recientemente
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="pt-24 pb-56 bg-white relative overflow-hidden">
    <div class="absolute inset-0 bg-slate-50 skew-y-3 transform origin-top-left -z-10"></div>
    
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
            <div class="text-center group" data-aos="fade-up" data-aos-delay="0">
                <div class="w-20 h-20 mx-auto bg-blue-50 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg group-hover:shadow-blue-200">
                    <i class="ph-duotone ph-shield-check text-4xl text-blue-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-3">100% Confidencial</h3>
                <p class="text-slate-600 leading-relaxed">
                    Tu identidad y datos están protegidos con los más altos estándares de seguridad. Puedes realizar reportes anónimos si así lo deseas.
                </p>
            </div>
            
            <div class="text-center group" data-aos="fade-up" data-aos-delay="100">
                <div class="w-20 h-20 mx-auto bg-purple-50 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg group-hover:shadow-purple-200">
                    <i class="ph-duotone ph-lightning text-4xl text-purple-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-3">Respuesta Rápida</h3>
                <p class="text-slate-600 leading-relaxed">
                    Nuestro equipo se compromete a revisar y dar seguimiento a cada reporte en el menor tiempo posible, garantizando una atención oportuna.
                </p>
            </div>
            
            <div class="text-center group" data-aos="fade-up" data-aos-delay="200">
                <div class="w-20 h-20 mx-auto bg-emerald-50 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300 shadow-lg group-hover:shadow-emerald-200">
                    <i class="ph-duotone ph-chart-bar text-4xl text-emerald-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-3">Seguimiento Detallado</h3>
                <p class="text-slate-600 leading-relaxed">
                    Monitorea el estado de tus reportes en tiempo real y recibe notificaciones automáticas sobre los avances y la resolución final.
                </p>
            </div>
        </div>
    </div>
</section>

<?php include 'components/footer.php'; ?>