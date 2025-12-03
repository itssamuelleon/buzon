<?php 
$page_title = 'Acerca de - ITSCC Buzón'; 
include 'components/header.php'; 
?>

<style>
    .gradient-text {
        background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .team-card {
        transition: all 0.3s ease;
    }
    
    .team-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }
    
    .advisor-card {
        transition: all 0.3s ease;
    }
    
    .advisor-card:hover {
        transform: translateY(-5px);
    }
</style>

<div class="bg-gradient-to-b from-gray-50 to-white min-h-screen">
    <!-- Hero Section -->
    <section class="relative pt-20 pb-32 overflow-hidden">
        <!-- Decorative Background -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 right-0 w-96 h-96 bg-blue-200 rounded-full filter blur-3xl opacity-20 animate-pulse"></div>
            <div class="absolute bottom-0 left-0 w-80 h-80 bg-purple-200 rounded-full filter blur-3xl opacity-20" style="animation-delay: -2s;"></div>
        </div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center space-x-2 py-2 px-4 rounded-full bg-blue-50 text-blue-600 text-sm font-semibold mb-6 border border-blue-100">
                    <i class="ph-info"></i>
                    <span>Proyecto de Residencias Profesionales</span>
                </div>
                
                <h1 class="text-5xl md:text-6xl font-black text-gray-900 mb-6 leading-tight">
                    Acerca del <span class="gradient-text">Buzón Digital</span>
                </h1>
                
                <p class="text-xl text-gray-600 leading-relaxed max-w-3xl mx-auto">
                    Un sistema innovador desarrollado para fortalecer la comunicación institucional y promover 
                    la mejora continua en el Instituto Tecnológico Superior de Ciudad Constitución.
                </p>
            </div>
        </div>
    </section>

    <!-- Project Info Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <!-- Left: Image/Icon -->
                    <div class="relative">
                        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-12 shadow-2xl">
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-8 text-center">
                                <i class="ph-graduation-cap text-white text-8xl mb-6"></i>
                                <h3 class="text-white text-2xl font-bold mb-2">Residencias Profesionales</h3>
                                <p class="text-white/80 text-lg">Generación 2021-2026</p>
                            </div>
                        </div>
                        <!-- Decorative elements -->
                        <div class="absolute -top-4 -right-4 w-24 h-24 bg-yellow-400 rounded-full opacity-20 blur-xl"></div>
                        <div class="absolute -bottom-4 -left-4 w-32 h-32 bg-pink-400 rounded-full opacity-20 blur-xl"></div>
                    </div>
                    
                    <!-- Right: Description -->
                    <div>
                        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">
                            Sobre el Proyecto
                        </h2>
                        <div class="space-y-4 text-gray-600 leading-relaxed">
                            <p>
                                El <strong class="text-gray-900">Buzón Digital ITSCC</strong> es un sistema web desarrollado como parte del programa de 
                                Residencias Profesionales de la carrera de <strong class="text-gray-900">Ingeniería en Sistemas Computacionales</strong>.
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
                        
                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-6 mt-8">
                            <div class="bg-blue-50 rounded-xl p-6 border border-blue-100">
                                <div class="text-3xl font-bold text-blue-600 mb-1">2025</div>
                                <div class="text-sm text-gray-600">Año de Desarrollo</div>
                            </div>
                            <div class="bg-purple-50 rounded-xl p-6 border border-purple-100">
                                <div class="text-3xl font-bold text-purple-600 mb-1">ISC</div>
                                <div class="text-sm text-gray-600">Ingeniería en Sistemas</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-20 bg-gradient-to-b from-gray-50 to-white">
        <div class="container mx-auto px-4">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                        Equipo de Desarrollo
                    </h2>
                    <p class="text-xl text-gray-600">
                        Estudiantes comprometidos con la innovación tecnológica
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
                    <!-- Developer 1 -->
                    <div class="team-card bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 h-32"></div>
                        <div class="p-8 -mt-16">
                            <div class="w-24 h-24 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4 shadow-xl border-4 border-white">
                                SE
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">
                                Samuel Elí León Mendoza
                            </h3>
                            <p class="text-blue-600 font-semibold mb-4">Desarrollador Full-Stack</p>
                            <p class="text-gray-600 leading-relaxed">
                                Estudiante de Ingeniería en Sistemas Computacionales, responsable del desarrollo 
                                del backend, integración de APIs y diseño de la arquitectura del sistema.
                            </p>
                            <div class="mt-6 flex items-center gap-2">
                                <div class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-sm font-medium">
                                    Backend
                                </div>
                                <div class="px-3 py-1 bg-purple-50 text-purple-600 rounded-full text-sm font-medium">
                                    Database
                                </div>
                                <div class="px-3 py-1 bg-green-50 text-green-600 rounded-full text-sm font-medium">
                                    APIs
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Developer 2 -->
                    <div class="team-card bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                        <div class="bg-gradient-to-br from-purple-500 to-pink-600 h-32"></div>
                        <div class="p-8 -mt-16">
                            <div class="w-24 h-24 bg-gradient-to-br from-purple-400 to-pink-500 rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4 shadow-xl border-4 border-white">
                                YM
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">
                                Yahir Andrés Marin Obledo
                            </h3>
                            <p class="text-purple-600 font-semibold mb-4">Desarrollador Full-Stack</p>
                            <p class="text-gray-600 leading-relaxed">
                                Estudiante de Ingeniería en Sistemas Computacionales, enfocado en el desarrollo 
                                del frontend, experiencia de usuario y diseño de interfaces.
                            </p>
                            <div class="mt-6 flex items-center gap-2">
                                <div class="px-3 py-1 bg-purple-50 text-purple-600 rounded-full text-sm font-medium">
                                    Frontend
                                </div>
                                <div class="px-3 py-1 bg-pink-50 text-pink-600 rounded-full text-sm font-medium">
                                    UI/UX
                                </div>
                                <div class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-sm font-medium">
                                    Design
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advisors Section -->
                <div class="text-center mb-12">
                    <h3 class="text-3xl font-bold text-gray-900 mb-4">
                        Asesores del Proyecto
                    </h3>
                    <p class="text-lg text-gray-600">
                        Guía y supervisión académica
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- External Advisor -->
                    <div class="advisor-card bg-white rounded-xl shadow-md p-8 border border-gray-100">
                        <div class="flex items-start gap-4">
                            <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-emerald-500 rounded-lg flex items-center justify-center text-white text-xl font-bold flex-shrink-0">
                                <i class="ph-briefcase text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm text-green-600 font-semibold mb-1">Asesor Externo</div>
                                <h4 class="text-xl font-bold text-gray-900 mb-2">
                                    José de Jesús Álvarez Salazar
                                </h4>
                                <p class="text-gray-600">
                                    Supervisor y guía del proyecto desde la perspectiva institucional, 
                                    asegurando la alineación con las necesidades del ITSCC.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Internal Advisor -->
                    <div class="advisor-card bg-white rounded-xl shadow-md p-8 border border-gray-100">
                        <div class="flex items-start gap-4">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-lg flex items-center justify-center text-white text-xl font-bold flex-shrink-0">
                                <i class="ph-chalkboard-teacher text-2xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm text-blue-600 font-semibold mb-1">Asesor Interno</div>
                                <h4 class="text-xl font-bold text-gray-900 mb-2">
                                    Reyes Arévalos Rocha
                                </h4>
                                <p class="text-gray-600">
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

    <!-- Technologies Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">
                        Tecnologías Utilizadas
                    </h2>
                    <p class="text-xl text-gray-600">
                        Stack tecnológico moderno y robusto
                    </p>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 text-center border border-blue-100 hover:shadow-lg transition-shadow">
                        <i class="ph-code text-4xl text-blue-600 mb-3"></i>
                        <h4 class="font-bold text-gray-900 mb-1">PHP</h4>
                        <p class="text-sm text-gray-600">Backend</p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-6 text-center border border-orange-100 hover:shadow-lg transition-shadow">
                        <i class="ph-database text-4xl text-orange-600 mb-3"></i>
                        <h4 class="font-bold text-gray-900 mb-1">MySQL</h4>
                        <p class="text-sm text-gray-600">Base de Datos</p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-cyan-50 to-blue-50 rounded-xl p-6 text-center border border-cyan-100 hover:shadow-lg transition-shadow">
                        <i class="ph-palette text-4xl text-cyan-600 mb-3"></i>
                        <h4 class="font-bold text-gray-900 mb-1">Tailwind CSS</h4>
                        <p class="text-sm text-gray-600">Diseño</p>
                    </div>
                    
                    <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 text-center border border-purple-100 hover:shadow-lg transition-shadow">
                        <i class="ph-microsoft-outlook-logo text-4xl text-purple-600 mb-3"></i>
                        <h4 class="font-bold text-gray-900 mb-1">Microsoft Graph</h4>
                        <p class="text-sm text-gray-600">Autenticación</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-4xl font-bold mb-6">
                    ¿Tienes alguna pregunta o sugerencia?
                </h2>
                <p class="text-xl text-blue-100 mb-8">
                    Estamos comprometidos con la mejora continua del sistema
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="submit_complaint.php" class="inline-flex items-center justify-center px-8 py-4 bg-white text-blue-600 font-bold rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-1">
                        <i class="ph-plus-circle text-xl mr-2"></i>
                        Enviar Reporte
                    </a>
                    <a href="index.php" class="inline-flex items-center justify-center px-8 py-4 bg-blue-500 text-white font-bold rounded-xl shadow-lg hover:bg-blue-400 transition-all transform hover:-translate-y-1">
                        <i class="ph-house text-xl mr-2"></i>
                        Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'components/footer.php'; ?>
