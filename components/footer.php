<?php
// This line can be removed if config.php is already included in your main page structure.
// require_once __DIR__ . '/../config.php'; 
?>

    </main> <!-- This closes a potential <main> tag that should wrap your page content -->

    <!-- Modern Footer -->
    <!-- UPDATED: Replaced 'bg-gray-900' with the new blue gradient -->
    <footer class="relative bg-gradient-to-r from-blue-700 to-indigo-800 text-white pt-20 pb-8 overflow-hidden">
        <!-- Decorative Gradient Blobs -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 left-0 w-96 h-96 bg-gradient-to-br from-blue-600 to-purple-900 rounded-full filter blur-3xl opacity-30 animate-float"></div>
            <div class="absolute bottom-0 right-0 w-80 h-80 bg-gradient-to-tl from-indigo-800 to-blue-900 rounded-full filter blur-3xl opacity-20" style="animation-delay: -3s;"></div>
        </div>

        <!-- Decorative Top Border (optional, but looks good with the theme) -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-blue-400 to-transparent opacity-50"></div>

        <div class="container mx-auto px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
                
                <!-- Column 1: About & Logo -->
                <div class="col-span-1 md:col-span-2 lg:col-span-1">
                    <a href="index.php" class="flex items-center space-x-3 mb-6 group">
                        <img src="assets/logo.png" alt="ITSCC Logo" class="h-12 w-auto bg-white/10 p-2 rounded-lg group-hover:scale-105 transition-transform duration-300">
                        <div>
                            <span class="text-white text-xl font-bold tracking-tight">ITSCC</span>
                            <span class="block text-white/60 text-xs font-light">Buzón de Quejas</span>
                        </div>
                    </a>
                    <p class="text-white/60 text-sm leading-relaxed">
                        Comprometidos con la excelencia educativa y la mejora continua. Tu voz es importante para nosotros.
                    </p>
                    <div class="flex space-x-4 mt-6">
                        <a href="#" class="text-white/60 hover:text-white transition-colors duration-300 transform hover:-translate-y-1">
                            <i class="ph-facebook-logo text-2xl"></i>
                        </a>
                        <a href="#" class="text-white/60 hover:text-white transition-colors duration-300 transform hover:-translate-y-1">
                            <i class="ph-twitter-logo text-2xl"></i>
                        </a>
                        <a href="#" class="text-white/60 hover:text-white transition-colors duration-300 transform hover:-translate-y-1">
                            <i class="ph-instagram-logo text-2xl"></i>
                        </a>
                        <a href="#" class="text-white/60 hover:text-white transition-colors duration-300 transform hover:-translate-y-1">
                            <i class="ph-youtube-logo text-2xl"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Column 2: Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold text-white tracking-wider mb-4">Navegación</h3>
                    <ul class="space-y-3">
                        <li><a href="index.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Inicio</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="submit_complaint.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Nuevo Reporte</a></li>
                            <li><a href="my_complaints.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Mis Reportes</a></li>
                        <?php else: ?>
                            <li><a href="login.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Iniciar Sesión</a></li>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                             <li><a href="dashboard.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Column 3: Resources -->
                <div>
                    <h3 class="text-lg font-semibold text-white tracking-wider mb-4">Recursos</h3>
                    <ul class="space-y-3">
                        <li><a href="https://www.itscc.edu.mx" target="_blank" rel="noopener noreferrer" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Sitio Oficial</a></li>
                        <li><a href="privacy.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Política de Privacidad</a></li>
                        <li><a href="terms.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Términos de Uso</a></li>
                        <li><a href="faq.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Preguntas Frecuentes</a></li>
                    </ul>
                </div>
                
                <!-- Column 4: Contact -->
                <div>
                    <h3 class="text-lg font-semibold text-white tracking-wider mb-4">Contacto</h3>
                    <ul class="space-y-4 text-white/60">
                        <li class="flex items-start">
                            <i class="ph-map-pin text-xl text-blue-400 mr-3 mt-1"></i>
                            <div>
                                <span class="block">Carretera Transpeninsular al Norte Km. 212.5</span>
                                <span class="block">Ciudad Constitución, B.C.S.</span>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="ph-phone text-xl text-blue-400 mr-3 mt-1"></i>
                            <a href="tel:6131320238" class="hover:text-white transition-colors">(613) 132-0238</a>
                        </li>
                        <li class="flex items-start">
                            <i class="ph-envelope text-xl text-blue-400 mr-3 mt-1"></i>
                            <a href="mailto:contacto@itscc.edu.mx" class="hover:text-white transition-colors">contacto@itscc.edu.mx</a>
                        </li>
                    </ul>
                </div>
                
            </div>
            
            <!-- Bottom Bar -->
            <div class="mt-16 pt-8 border-t border-white/10 text-center text-white/40 text-sm">
                <p>&copy; <?php echo date('Y'); ?> Instituto Tecnológico Superior de Ciudad Constitución. Todos los derechos reservados.</p>
                <p class="mt-2">Diseñado con <i class="ph-heart-fill text-red-500/80"></i> para la comunidad tecnológica.</p>
            </div>
        </div>
    </footer>

</body>
</html>