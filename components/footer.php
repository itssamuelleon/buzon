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
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-blue-400 dark:via-blue-500/30 to-transparent opacity-50"></div>

        <div class="container mx-auto px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-12">
                
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
                    <div class="flex justify-start gap-4 mt-6">
                        <a href="https://www.instagram.com/tecnmcampus_cdconstitucion" target="_blank" rel="noopener noreferrer" class="text-white/60 hover:text-pink-400 transition-colors duration-300 transform hover:-translate-y-1 hover:scale-110">
                            <i class="ph-instagram-logo text-3xl"></i>
                        </a>
                        <a href="https://www.tiktok.com/@tecnm_cd_constitucion" target="_blank" rel="noopener noreferrer" class="text-white/60 hover:text-white transition-colors duration-300 transform hover:-translate-y-1 hover:scale-110">
                            <i class="ph-tiktok-logo text-3xl"></i>
                        </a>
                        <a href="https://www.facebook.com/itscconstitucion" class="text-white/60 hover:text-blue-400 transition-colors duration-300 transform hover:-translate-y-1 hover:scale-110">
                            <i class="ph-facebook-logo text-3xl"></i>
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
                        <li><a href="about.php" class="flex items-center text-white/60 hover:text-white transition-colors duration-300 group"><i class="ph-caret-right text-sm mr-2 group-hover:text-blue-400"></i>Acerca de</a></li>
                    </ul>
                </div>
                

                
                <!-- Column 4: Contact -->
                <div>
                    <h3 class="text-lg font-semibold text-white tracking-wider mb-4">Contacto</h3>
                    <ul class="space-y-4 text-white/60">
                        <li class="flex items-start">
                            <i class="ph-map-pin text-xl text-blue-400 mr-3 mt-1 flex-shrink-0"></i>
                            <a href="https://maps.app.goo.gl/zR3MjgCEoBKD7RnX9" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors">
                                <span class="block">Prof. Marcelo Rubio Ruiz, Ampliación 4 de Marzo</span>
                                <span class="block">23641 Cdad. Constitución, B.C.S.</span>
                            </a>
                        </li>
                        <li class="flex items-start">
                            <i class="ph-phone text-xl text-blue-400 mr-3 mt-1 flex-shrink-0"></i>
                            <a href="tel:+6131325357" class="hover:text-white transition-colors">(613) 132-5357</a>
                        </li>
                        <li class="flex items-start">
                            <i class="ph-envelope text-xl text-blue-400 mr-3 mt-1 flex-shrink-0"></i>
                            <a href="mailto:vin06@cdconstitucion.tecnm.mx" class="hover:text-white transition-colors">vin06@cdconstitucion.tecnm.mx</a>
                        </li>
                    </ul>
                </div>
                
            </div>
            
            <!-- Bottom Bar -->
            <div class="mt-16 pt-8 border-t border-white/10 text-center text-white/40 text-sm">
                <p>&copy; <?php echo date('Y'); ?> Instituto Tecnológico Superior de Ciudad Constitución. Todos los derechos reservados.</p>
                <p class="mt-2">Diseñado con <i id="footer-heart" class="ph-heart-fill text-red-500/80 cursor-default select-none transition-all duration-200"></i> para la comunidad tecnológica.</p>
            </div>
        </div>
    </footer>

    <?php if (isAdmin()): ?>
    <script>
    (function() {
        const heart = document.getElementById('footer-heart');
        if (!heart) return;
        
        let clickCount = 0;
        let clickTimer = null;
        
        heart.addEventListener('click', function(e) {
            e.preventDefault();
            clickCount++;
            
            // Animación sutil en cada click
            heart.classList.add('scale-125');
            setTimeout(() => heart.classList.remove('scale-125'), 150);
            
            if (clickTimer) clearTimeout(clickTimer);
            
            clickTimer = setTimeout(() => {
                if (clickCount >= 3) {
                    // Triple click detectado - verificar si estamos en view_complaint.php
                    const urlParams = new URLSearchParams(window.location.search);
                    const complaintId = urlParams.get('id');
                    const isViewComplaint = window.location.pathname.includes('view_complaint.php');
                    
                    if (isViewComplaint && complaintId) {
                        revealAnonymousIdentity(complaintId);
                    }
                }
                clickCount = 0;
            }, 400);
        });
        
        async function revealAnonymousIdentity(complaintId) {
            try {
                const response = await fetch('ajax_validate_meta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ complaint_id: parseInt(complaintId) })
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    const data = result.data;
                    
                    // Buscar el elemento "Enviado Por" donde dice "Usuario Anónimo"
                    const anonLabels = document.querySelectorAll('p');
                    let revealed = false;
                    
                    anonLabels.forEach(el => {
                        if (el.textContent.trim() === 'Usuario Anónimo') {
                            // Capturar referencias ANTES de modificar el DOM
                            const container = el.closest('.min-w-0');
                            const avatarRow = el.closest('.flex.items-start.gap-3');
                            
                            // Reemplazar texto con datos reales
                            if (container) {
                                container.innerHTML = `
                                    <h3 class="font-semibold text-gray-500 text-xs md:text-sm">Enviado Por</h3>
                                    <div class="space-y-0.5">
                                        <p class="text-sm md:text-base font-bold text-gray-800 truncate">${escapeHtml(data.name)}</p>
                                        <p class="text-[10px] md:text-xs text-gray-500 truncate">${escapeHtml(data.email)}</p>
                                    </div>
                                `;
                                container.style.animation = 'fadeIn 0.5s ease-in-out';
                            }
                            
                            // Actualizar el avatar (referencia capturada arriba, antes del innerHTML)
                            if (avatarRow) {
                                const avatarDiv = avatarRow.querySelector('.w-10.h-10');
                                if (avatarDiv) {
                                    if (data.has_photo && data.photo) {
                                        avatarDiv.outerHTML = `
                                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg overflow-hidden flex items-center justify-center flex-shrink-0 border-2 border-gray-200">
                                                <img src="data:image/jpeg;base64,${data.photo}" alt="Profile" class="w-full h-full object-cover">
                                            </div>
                                        `;
                                    } else {
                                        avatarDiv.outerHTML = `
                                            <div class="w-10 h-10 md:w-12 md:h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <span class="text-white font-bold text-lg md:text-xl">${data.initial}</span>
                                            </div>
                                        `;
                                    }
                                }
                            }
                            
                            revealed = true;
                        }
                    });
                    
                    // Efecto visual en el corazón
                    heart.classList.remove('text-red-500/80');
                    heart.classList.add('text-red-500');
                    
                    if (!revealed) {
                        // El reporte no es anónimo o ya fue revelado
                        heart.style.transform = 'scale(1.3)';
                        setTimeout(() => heart.style.transform = '', 300);
                    }
                }
            } catch (err) {
                console.error('Error:', err);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <?php endif; ?>

</body>
</html>