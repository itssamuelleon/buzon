<?php 
$page_title = 'Acerca de - Buzón de Quejas'; 
include 'components/header.php'; 
?>

<style>
    .gradient-text {
        background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    /* Animated gradient border */
    .gradient-border {
        position: relative;
        border-radius: 1.5rem;
        overflow: hidden;
    }
    .gradient-border::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 1.5rem;
        padding: 2px;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6, #ec4899, #3b82f6);
        background-size: 300% 300%;
        animation: borderShift 6s ease infinite;
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
    }

    @keyframes borderShift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    /* Team card */
    .team-card {
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    .team-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.12);
    }
    .team-card:hover .team-avatar {
        transform: scale(1.08);
        box-shadow: 0 12px 30px rgba(99, 102, 241, 0.4);
    }
    .team-avatar {
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    /* Advisor card */
    .advisor-card {
        transition: all 0.3s ease;
    }
    .advisor-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
    }
    .advisor-card:hover .advisor-icon {
        transform: scale(1.1) rotate(5deg);
    }
    .advisor-icon {
        transition: all 0.3s ease;
    }

    /* Tech card */
    .tech-card {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .tech-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }
    html.dark .tech-card::after {
        background: linear-gradient(135deg, rgba(99,102,241,0) 0%, rgba(99,102,241,0.2) 100%);
    }
    .tech-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }
    html.dark .tech-card:hover {
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }
    .tech-card:hover::after {
        opacity: 1;
    }
    .tech-card:hover .tech-icon {
        transform: scale(1.2) rotate(-5deg);
    }
    .tech-icon {
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        position: relative;
        z-index: 1;
    }

    /* Floating animation */
    @keyframes about-float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        33% { transform: translateY(-12px) rotate(2deg); }
        66% { transform: translateY(-6px) rotate(-2deg); }
    }
    .about-float {
        animation: about-float 6s ease-in-out infinite;
    }
    .about-float-delay {
        animation: about-float 6s ease-in-out infinite;
        animation-delay: -2s;
    }
    .about-float-delay-2 {
        animation: about-float 6s ease-in-out infinite;
        animation-delay: -4s;
    }

    /* Glow effect */
    @keyframes glow-pulse {
        0%, 100% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.2); }
        50% { box-shadow: 0 0 40px rgba(99, 102, 241, 0.4); }
    }

    /* Shine sweep */
    @keyframes shine-sweep {
        0% { left: -100%; }
        100% { left: 100%; }
    }

    /* Counter animation */
    .stat-number {
        display: inline-block;
    }

    /* Stagger fade in */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Badge shine */
    .badge-shine {
        position: relative;
        overflow: hidden;
    }
    .badge-shine::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 50%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        animation: shine-sweep 3s infinite;
    }
</style>

<div class="min-h-screen">
    <!-- ========== HERO SECTION ========== -->
    <section class="relative pt-16 pb-28 overflow-hidden">
        <!-- Animated Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50/50 to-indigo-50/30 dark:from-slate-900 dark:via-slate-900/80 dark:to-slate-900/60"></div>
        <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(#cbd5e1 0.8px, transparent 0.8px); background-size: 24px 24px; opacity: 0.3;"></div>
        
        <!-- Floating decorative orbs -->
        <div class="absolute top-10 right-[10%] w-72 h-72 bg-gradient-to-br from-blue-400/20 to-indigo-500/20 rounded-full blur-3xl about-float"></div>
        <div class="absolute bottom-10 left-[5%] w-64 h-64 bg-gradient-to-br from-purple-400/20 to-pink-500/20 rounded-full blur-3xl about-float-delay"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-gradient-to-br from-indigo-300/10 to-cyan-300/10 rounded-full blur-3xl about-float-delay-2"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-4xl mx-auto text-center" data-aos="fade-up" data-aos-duration="1000">
                <!-- Badge -->
                <div class="badge-shine inline-flex items-center space-x-2 py-2 px-5 rounded-full bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm text-blue-600 dark:text-blue-400 text-sm font-semibold mb-8 border border-blue-100/80 dark:border-blue-900/50 shadow-sm">
                    <i class="ph-fill ph-graduation-cap text-lg"></i>
                    <span>Proyecto de Residencias Profesionales</span>
                </div>
                
                <h1 class="text-5xl md:text-7xl font-black text-slate-900 dark:text-white mb-6 leading-tight tracking-tight">
                    Acerca del <br class="md:hidden"><span class="gradient-text">Buzón Digital</span>
                </h1>
                
                <p class="text-lg md:text-xl text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl mx-auto mb-12">
                    Un sistema innovador desarrollado para fortalecer la comunicación institucional y promover 
                    la mejora continua en el Instituto Tecnológico Superior de Ciudad Constitución.
                </p>

                <!-- Quick stats row -->
                <div class="flex flex-wrap items-center justify-center gap-6 md:gap-10" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center gap-3 bg-white/70 dark:bg-slate-800/70 backdrop-blur-sm rounded-2xl px-5 py-3 shadow-sm border border-white/50 dark:border-slate-700/50">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                            <i class="ph-fill ph-calendar-check text-white text-lg"></i>
                        </div>
                        <div class="text-left">
                            <div class="text-lg font-black text-slate-800 dark:text-white">2025</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Año de Desarrollo</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-white/70 dark:bg-slate-800/70 backdrop-blur-sm rounded-2xl px-5 py-3 shadow-sm border border-white/50 dark:border-slate-700/50">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                            <i class="ph-fill ph-code text-white text-lg"></i>
                        </div>
                        <div class="text-left">
                            <div class="text-lg font-black text-slate-800 dark:text-white">ISC</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Ing. en Sistemas</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-white/70 dark:bg-slate-800/70 backdrop-blur-sm rounded-2xl px-5 py-3 shadow-sm border border-white/50 dark:border-slate-700/50">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                            <i class="ph-fill ph-users-three text-white text-lg"></i>
                        </div>
                        <div class="text-left">
                            <div class="text-lg font-black text-slate-800 dark:text-white">2021-2026</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Generación</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== PROJECT INFO SECTION ========== -->
    <section class="py-20 bg-slate-50/50 dark:bg-slate-900/40 relative overflow-hidden">
        <!-- Subtle background decoration -->
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-bl from-blue-50 to-transparent rounded-full -translate-y-1/2 translate-x-1/4"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <!-- Left: Visual Card -->
                    <div class="relative" data-aos="fade-right" data-aos-duration="800">
                        <div class="gradient-border shadow-2xl dark:shadow-blue-500/10 shadow-indigo-500/5">
                            <div class="bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 dark:from-blue-900 dark:via-indigo-900 dark:to-slate-900 rounded-3xl p-10 md:p-14 relative overflow-hidden">
                                <!-- Inner glass decoration -->
                                <div class="absolute top-0 right-0 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
                                <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/5 rounded-full blur-xl"></div>
                                
                                <div class="relative text-center">
                                    <div class="inline-flex items-center justify-center w-28 h-28 rounded-3xl bg-white/15 backdrop-blur-sm mb-8 shadow-lg" style="animation: glow-pulse 3s ease-in-out infinite;">
                                        <i class="ph-fill ph-graduation-cap text-white text-6xl"></i>
                                    </div>
                                    <h3 class="text-white text-3xl font-black mb-3 tracking-tight">Residencias Profesionales</h3>
                                    <p class="text-white/70 text-lg font-medium">Generación 2021-2026</p>
                                    
                                    <!-- Decorative line -->
                                    <div class="w-16 h-1 bg-gradient-to-r from-white/0 via-white/50 to-white/0 mx-auto mt-6"></div>
                                    
                                    <p class="text-white/50 text-sm mt-6 font-medium">Instituto Tecnológico Superior de Ciudad Constitución</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Floating accent elements -->
                        <div class="absolute -top-6 -right-6 w-20 h-20 bg-gradient-to-br from-yellow-400 to-amber-500 rounded-2xl opacity-20 blur-xl about-float"></div>
                        <div class="absolute -bottom-6 -left-6 w-24 h-24 bg-gradient-to-br from-pink-400 to-rose-500 rounded-2xl opacity-20 blur-xl about-float-delay"></div>
                    </div>
                    
                    <!-- Right: Description -->
                    <div data-aos="fade-left" data-aos-duration="800">
                        <div class="inline-flex items-center space-x-2 text-blue-600 dark:text-blue-400 font-semibold mb-4">
                            <i class="ph-fill ph-info text-lg"></i>
                            <span class="uppercase tracking-wider text-sm">Sobre el Proyecto</span>
                        </div>
                        <h2 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-8 tracking-tight leading-tight">
                            Modernizando la <span class="gradient-text">comunicación</span> institucional
                        </h2>
                        <div class="space-y-5 text-slate-600 dark:text-slate-400 leading-relaxed text-lg">
                            <p>
                                El <strong class="text-slate-800 dark:text-slate-200">Buzón de Quejas, Sugerencias y Felicitaciones</strong> es un sistema web desarrollado como parte del programa de 
                                Residencias Profesionales de la carrera de <strong class="text-slate-800 dark:text-slate-200">Ingeniería en Sistemas Computacionales</strong>.
                            </p>
                            <p>
                                Este proyecto nace con el objetivo de modernizar y optimizar el proceso de gestión de quejas, 
                                sugerencias y reconocimientos dentro de la institución, proporcionando una plataforma segura, 
                                transparente y eficiente para toda la comunidad tecnológica.
                            </p>
                            <p>
                                Desarrollado con tecnologías web modernas, el sistema integra características como autenticación 
                                institucional, gestión de reportes, asignación automática de departamentos, y un panel administrativo 
                                completo para el seguimiento y resolución de casos.
                            </p>
                        </div>
                        
                        <!-- Feature highlights -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-8">
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-blue-50/50 dark:bg-blue-500/10 border border-blue-100/50 dark:border-blue-800/50">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center flex-shrink-0">
                                    <i class="ph-fill ph-shield-check text-blue-600 dark:text-blue-400"></i>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Autenticación Institucional</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-purple-50/50 dark:bg-purple-500/10 border border-purple-100/50 dark:border-purple-800/50">
                                <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-500/20 flex items-center justify-center flex-shrink-0">
                                    <i class="ph-fill ph-robot text-purple-600 dark:text-purple-400"></i>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Análisis con IA (Gemini)</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-500/10 border border-emerald-100/50 dark:border-emerald-800/50">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                                    <i class="ph-fill ph-bell-ringing text-emerald-600 dark:text-emerald-400"></i>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Notificaciones por Email</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-amber-50/50 dark:bg-amber-500/10 border border-amber-100/50 dark:border-amber-800/50">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                                    <i class="ph-fill ph-chart-bar text-amber-600 dark:text-amber-400"></i>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Dashboard Administrativo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== TEAM SECTION ========== -->
    <section class="py-24 relative overflow-hidden bg-slate-50 dark:bg-slate-900/50">
        <!-- Background -->
        <div class="absolute inset-0 bg-gradient-to-b from-slate-50 via-white to-slate-50"></div>
        <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(#e2e8f0 0.5px, transparent 0.5px); background-size: 20px 20px; opacity: 0.5;"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-6xl mx-auto">
                <!-- Section Header -->
                <div class="text-center mb-20" data-aos="fade-up">
                    <div class="inline-flex items-center space-x-2 text-indigo-600 font-semibold mb-4">
                        <i class="ph-fill ph-users-three text-lg"></i>
                        <span class="uppercase tracking-wider text-sm">Nuestro Equipo</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-4 tracking-tight">
                        Equipo de <span class="gradient-text">Desarrollo</span>
                    </h2>
                    <p class="text-lg text-slate-500 max-w-2xl mx-auto">
                        Estudiantes comprometidos con la innovación tecnológica y la mejora institucional
                    </p>
                </div>
                
                <!-- Developer Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mb-20">
                    <!-- Developer 1 -->
                    <div class="team-card bg-white dark:bg-slate-800 rounded-3xl shadow-lg overflow-hidden border border-slate-100/80 dark:border-slate-700" data-aos="fade-up" data-aos-delay="0">
                        <!-- Header gradient -->
                        <div class="relative h-36 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 overflow-hidden">
                            <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.15) 1px, transparent 1px); background-size: 16px 16px;"></div>
                            <div class="absolute bottom-0 right-6 w-20 h-20 bg-white/10 rounded-full blur-xl"></div>
                        </div>
                        <div class="px-8 pb-8 -mt-14 relative z-10">
                            <div class="team-avatar w-28 h-28 rounded-full mb-5 shadow-xl shadow-indigo-500/30 border-4 border-white dark:border-slate-800 overflow-hidden bg-gradient-to-br from-blue-400 via-indigo-500 to-purple-500">
                                <img src="assets/team/samuel.jpg" alt="Samuel Elí León Mendoza" 
                                     class="w-full h-full object-cover"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-full h-full items-center justify-center text-white text-3xl font-black" style="display:none;">SE</div>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-1">
                                Samuel Elí León Mendoza
                            </h3>
                            <p class="text-indigo-600 dark:text-indigo-400 font-bold mb-4 flex items-center gap-2">
                                <i class="ph-fill ph-code text-lg"></i>
                                Desarrollador Full-Stack
                            </p>
                            <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                Estudiante de Ingeniería en Sistemas Computacionales, responsable del desarrollo 
                                del backend, integración de APIs y diseño de la arquitectura del sistema.
                            </p>
                            <div class="mt-6 flex flex-wrap items-center gap-2">
                                <div class="px-3 py-1.5 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-bold border border-blue-100/80 dark:border-blue-800/50">
                                    Backend
                                </div>
                                <div class="px-3 py-1.5 bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-lg text-xs font-bold border border-purple-100/80 dark:border-purple-800/50">
                                    Database
                                </div>
                                <div class="px-3 py-1.5 bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400 rounded-lg text-xs font-bold border border-green-100/80 dark:border-green-800/50">
                                    APIs
                                </div>
                                <div class="px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-bold border border-amber-100/80">
                                    Arquitectura
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Developer 2 -->
                    <div class="team-card bg-white dark:bg-slate-800 rounded-3xl shadow-lg overflow-hidden border border-slate-100/80 dark:border-slate-700" data-aos="fade-up" data-aos-delay="150">
                        <!-- Header gradient -->
                        <div class="relative h-36 bg-gradient-to-br from-purple-500 via-pink-500 to-rose-500 overflow-hidden">
                            <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.15) 1px, transparent 1px); background-size: 16px 16px;"></div>
                            <div class="absolute bottom-0 right-6 w-20 h-20 bg-white/10 rounded-full blur-xl"></div>
                        </div>
                        <div class="px-8 pb-8 -mt-14 relative z-10">
                            <div class="team-avatar w-28 h-28 rounded-full mb-5 shadow-xl shadow-pink-500/30 border-4 border-white dark:border-slate-800 overflow-hidden bg-gradient-to-br from-purple-400 via-pink-500 to-rose-500">
                                <img src="assets/team/marin.jpg" alt="Yahir Andrés Marin Obledo" 
                                     class="w-full h-full object-cover"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-full h-full items-center justify-center text-white text-3xl font-black" style="display:none;">YM</div>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-1">
                                Yahir Andrés Marin Obledo
                            </h3>
                            <p class="text-pink-600 dark:text-pink-400 font-bold mb-4 flex items-center gap-2">
                                <i class="ph-fill ph-paint-brush text-lg"></i>
                                Desarrollador Full-Stack
                            </p>
                            <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                Estudiante de Ingeniería en Sistemas Computacionales, enfocado en el desarrollo 
                                del frontend, experiencia de usuario y diseño de interfaces.
                            </p>
                            <div class="mt-6 flex flex-wrap items-center gap-2">
                                <div class="px-3 py-1.5 bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-lg text-xs font-bold border border-purple-100/80 dark:border-purple-800/50">
                                    Frontend
                                </div>
                                <div class="px-3 py-1.5 bg-pink-50 dark:bg-pink-500/10 text-pink-600 dark:text-pink-400 rounded-lg text-xs font-bold border border-pink-100/80 dark:border-pink-800/50">
                                    UI/UX
                                </div>
                                <div class="px-3 py-1.5 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-bold border border-blue-100/80 dark:border-blue-800/50">
                                    Design
                                </div>
                                <div class="px-3 py-1.5 bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 rounded-lg text-xs font-bold border border-rose-100/80 dark:border-rose-800/50">
                                    Testing
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advisors -->
                <div class="text-center mb-12" data-aos="fade-up">
                    <div class="inline-flex items-center space-x-2 text-emerald-600 dark:text-emerald-400 font-semibold mb-4">
                        <i class="ph-fill ph-chalkboard-teacher text-lg"></i>
                        <span class="uppercase tracking-wider text-sm">Supervisión Académica</span>
                    </div>
                    <h3 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-3 tracking-tight">
                        Asesores del Proyecto
                    </h3>
                    <p class="text-slate-500 dark:text-slate-400 text-lg">
                        Guía y acompañamiento en cada etapa del desarrollo
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- External Advisor -->
                    <div class="advisor-card bg-white dark:bg-slate-800 rounded-2xl shadow-md p-8 border border-slate-100 dark:border-slate-700 group" data-aos="fade-up" data-aos-delay="0">
                        <div class="flex items-start gap-5 text-left">
                            <div class="advisor-icon w-16 h-16 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center text-white flex-shrink-0 shadow-lg shadow-emerald-500/20">
                                <i class="ph-fill ph-briefcase text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="inline-flex items-center px-2.5 py-1 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-lg text-xs font-bold mb-2 border border-emerald-100/80 dark:border-emerald-800/50">
                                    Asesor Externo
                                </div>
                                <h4 class="text-xl font-black text-slate-900 dark:text-white mb-2">
                                    José de Jesús Álvarez Salazar
                                </h4>
                                <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Supervisor y guía del proyecto desde la perspectiva institucional, 
                                    asegurando la alineación con las necesidades del ITSCC.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Internal Advisor -->
                    <div class="advisor-card bg-white dark:bg-slate-800 rounded-2xl shadow-md p-8 border border-slate-100 dark:border-slate-700 group" data-aos="fade-up" data-aos-delay="150">
                        <div class="flex items-start gap-5 text-left">
                            <div class="advisor-icon w-16 h-16 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-2xl flex items-center justify-center text-white flex-shrink-0 shadow-lg shadow-blue-500/20">
                                <i class="ph-fill ph-chalkboard-teacher text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="inline-flex items-center px-2.5 py-1 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-bold mb-2 border border-blue-100/80 dark:border-blue-800/50">
                                    Asesor Interno
                                </div>
                                <h4 class="text-xl font-black text-slate-900 dark:text-white mb-2">
                                    Reyes Arévalos Rocha
                                </h4>
                                <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Asesor académico responsable del seguimiento técnico y metodológico 
                                    del proyecto de residencias profesionales.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== TECHNOLOGIES SECTION ========== -->
    <section class="py-24 bg-white relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute bottom-0 left-0 w-[600px] h-[600px] bg-gradient-to-tr from-indigo-50 to-transparent rounded-full translate-y-1/2 -translate-x-1/4"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-6xl mx-auto">
                <!-- Section Header -->
                <div class="text-center mb-16" data-aos="fade-up">
                    <div class="inline-flex items-center space-x-2 text-purple-600 dark:text-purple-400 font-semibold mb-4">
                        <i class="ph-fill ph-stack text-lg"></i>
                        <span class="uppercase tracking-wider text-sm">Stack Tecnológico</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
                        Tecnologías <span class="gradient-text">Utilizadas</span>
                    </h2>
                    <p class="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">
                        Herramientas modernas y robustas para una experiencia excepcional
                    </p>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-5 md:gap-6">
                    <!-- PHP -->
                    <div class="tech-card bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-blue-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="0">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 mb-4 shadow-lg shadow-blue-500/20">
                            <i class="ph-fill ph-code text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">PHP</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Backend</p>
                    </div>
                    
                    <!-- MySQL -->
                    <div class="tech-card bg-gradient-to-br from-orange-50 to-amber-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-orange-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="100">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 mb-4 shadow-lg shadow-orange-500/20">
                            <i class="ph-fill ph-database text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">MySQL</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Base de Datos</p>
                    </div>
                    
                    <!-- Tailwind CSS -->
                    <div class="tech-card bg-gradient-to-br from-cyan-50 to-sky-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-cyan-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="200">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-cyan-500 to-sky-600 mb-4 shadow-lg shadow-cyan-500/20">
                            <i class="ph-fill ph-palette text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">Tailwind</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Diseño CSS</p>
                    </div>
                    
                    <!-- Microsoft Graph -->
                    <div class="tech-card bg-gradient-to-br from-purple-50 to-fuchsia-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-purple-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="300">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 mb-4 shadow-lg shadow-slate-500/20">
                            <svg class="w-7 h-7 md:w-8 md:h-8" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                                <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                                <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                                <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                            </svg>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">Microsoft Graph</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Autenticación</p>
                    </div>
                </div>

                <!-- Additional tech row -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-5 md:gap-6 mt-5 md:mt-6">
                    <!-- Gemini AI -->
                    <div class="tech-card bg-gradient-to-br from-violet-50 to-purple-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-violet-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="400">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 mb-4 shadow-lg shadow-violet-500/20">
                            <i class="ph-fill ph-robot text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">Gemini</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Análisis IA</p>
                    </div>

                    <!-- JavaScript -->
                    <div class="tech-card bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-yellow-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="500">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-yellow-400 to-amber-500 mb-4 shadow-lg shadow-yellow-500/20">
                            <i class="ph-fill ph-file-js text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">JavaScript</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Frontend</p>
                    </div>

                    <!-- PHPMailer / SMTP -->
                    <div class="tech-card bg-gradient-to-br from-emerald-50 to-green-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-emerald-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="600">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 mb-4 shadow-lg shadow-emerald-500/20">
                            <i class="ph-fill ph-envelope-simple text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">PHPMailer</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Email SMTP</p>
                    </div>

                    <!-- Chart.js -->
                    <div class="tech-card bg-gradient-to-br from-rose-50 to-pink-50 dark:from-slate-800 dark:to-slate-900 rounded-2xl p-6 md:p-8 text-center border border-rose-100/60 dark:border-slate-700 shadow-sm" data-aos="fade-up" data-aos-delay="700">
                        <div class="tech-icon inline-flex items-center justify-center w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 mb-4 shadow-lg shadow-rose-500/20">
                            <i class="ph-fill ph-chart-line-up text-white text-2xl md:text-3xl"></i>
                        </div>
                        <h4 class="font-black text-slate-800 dark:text-white text-lg mb-1">Chart.js</h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Gráficas</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== CTA SECTION ========== -->
    <section class="relative py-24 overflow-hidden">
        <!-- Gradient background -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-800 to-slate-950 dark:from-slate-900 dark:via-blue-950 dark:to-slate-950"></div>
        <!-- Pattern overlay -->
        <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;"></div>
        <!-- Glow effects -->
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-80 h-80 bg-purple-400/20 rounded-full blur-3xl"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-4xl mx-auto text-center" data-aos="fade-up">
                <div class="inline-flex items-center space-x-2 py-2 px-4 rounded-full bg-white/10 backdrop-blur-sm text-white/90 text-sm font-semibold mb-8 border border-white/20">
                    <i class="ph-fill ph-megaphone-simple"></i>
                    <span>Tu opinión nos importa</span>
                </div>
                
                <h2 class="text-4xl md:text-5xl font-black text-white mb-6 tracking-tight leading-tight">
                    ¿Tienes alguna pregunta<br class="hidden md:block"> o sugerencia?
                </h2>
                <p class="text-xl text-blue-100/80 mb-10 max-w-2xl mx-auto leading-relaxed">
                    Estamos comprometidos con la mejora continua del sistema. Tu retroalimentación es fundamental para seguir creciendo.
                </p>
                <div class="flex flex-col sm:flex-row gap-5 justify-center mt-6">
                    <a href="submit_complaint.php" class="group relative inline-flex items-center justify-center px-10 py-4 font-black text-white transition-all duration-300 transform bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl hover:-translate-y-1 hover:scale-105 active:scale-95 overflow-hidden">
                        <!-- Shine effect -->
                        <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-white/0 via-white/20 to-white/0 -translate-x-full group-hover:animate-[shine-sweep_2s_infinite]"></div>
                        <i class="ph-bold ph-plus-circle text-xl mr-3 group-hover:rotate-90 transition-transform duration-500"></i>
                        <span class="relative">Enviar Reporte</span>
                    </a>
                    <a href="index.php" class="group inline-flex items-center justify-center px-10 py-4 font-bold text-white transition-all bg-white/10 backdrop-blur-md rounded-2xl border border-white/30 hover:bg-white/20 transform hover:-translate-y-1">
                        <i class="ph-bold ph-house text-xl mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>Volver al Inicio</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'components/footer.php'; ?>
