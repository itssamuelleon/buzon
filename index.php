<?php 
$page_title = 'Buzón de Quejas - TecNM';
include 'components/header.php'; 

$user_name = '';
$user_role = '';
if (isLoggedIn()) {
    $full_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Usuario';
    $user_role = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'student');

    $first = explode(' ', trim($full_name))[0];
    $user_name = mb_convert_case($first, MB_CASE_TITLE, "UTF-8");

    if (in_array($user_role, ['manager', 'admin']) && isset($_SESSION['email'])) {
        try {
            $stmt_mgr = $conn->prepare("SELECT manager FROM departments WHERE email = ? LIMIT 1");
            if ($stmt_mgr) {
                $stmt_mgr->bind_param("s", $_SESSION['email']);
                $stmt_mgr->execute();
                $res_mgr = $stmt_mgr->get_result();
                if ($row_mgr = $res_mgr->fetch_assoc()) {
                    if (!empty(trim($row_mgr['manager']))) {
                        $manager_first = explode(' ', trim($row_mgr['manager']))[0];
                        $user_name = mb_convert_case($manager_first, MB_CASE_TITLE, "UTF-8");
                    }
                }
                $stmt_mgr->close();
            }
        } catch (Exception $e) {}
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Outfit:wght@100..900&family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=Space+Grotesk:wght@300..700&family=Bebas+Neue&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Dancing+Script:wght@400..700&display=swap');

    :root {
        --morning-1: #ff9a5c; --morning-2: #ffcc70;
        --afternoon-1: #4facfe; --afternoon-2: #00f2fe;
        --evening-1: #6a3de8; --evening-2: #c850c0;
        --night-1: #0f0c29; --night-2: #302b63;
    }

    body { font-family: 'DM Sans', sans-serif; }

    /* ── Welcome Banner ── */
    .welcome-banner {
        position: relative;
        overflow: hidden;
        min-height: calc(100svh - 64px);
        display: flex;
        align-items: center;
    }
    @media (max-width: 767px) {
        .welcome-banner { min-height: calc(100svh - 56px); align-items: center; }
        .wb-content { text-align: center; }
        .wb-actions { justify-content: center; }
        .greeting-sub { margin-left: auto; margin-right: auto; }
        
        /* Ajustes para la imagen en móvil */
        #wbBgImage, .guest-hero img {
            width: 100% !important;
            object-position: center !important;
            opacity: 0.25 !important;
            mask-image: radial-gradient(circle at center, black 0%, transparent 100%) !important;
            -webkit-mask-image: radial-gradient(circle at center, black 0%, transparent 100%) !important;
        }
        
        /* Ocultar overlays de gradiente lateral que causan la "línea vertical" en móvil */
        #wbImgOverlayLight, #wbImgOverlayDark, 
        .guest-hero [style*="linear-gradient(to right"] {
            display: none !important;
        }
    }

    /* Aurora mesh background — z-index 0 */
    .aurora-bg {
        position: absolute;
        inset: 0;
        z-index: 0;
        transition: background 0.8s ease;
    }
    .aurora-bg.morning, .aurora-bg.afternoon {
        background: radial-gradient(circle at 60% 0%, rgba(59,130,246,0.1) 0%, rgba(255,255,255,0) 55%),
                    radial-gradient(circle at 0% 100%, rgba(99,102,241,0.07) 0%, rgba(255,255,255,0) 50%),
                    transparent;
    }
    html.dark .aurora-bg.morning, html.dark .aurora-bg.afternoon {
        background: radial-gradient(circle at 60% 0%, rgba(59,130,246,0.12) 0%, transparent 55%),
                    radial-gradient(circle at 0% 100%, rgba(99,102,241,0.08) 0%, transparent 50%), transparent;
    }
    .aurora-bg.night {
        background: radial-gradient(circle at 60% 0%, rgba(99,102,241,0.1) 0%, rgba(255,255,255,0) 55%),
                    radial-gradient(circle at 0% 100%, rgba(139,92,246,0.07) 0%, rgba(255,255,255,0) 50%), transparent;
    }
    html.dark .aurora-bg.night {
        background: linear-gradient(135deg, rgba(15,23,42,0.6) 0%, rgba(26,35,51,0.6) 50%, rgba(15,23,42,0.6) 100%);
    }

    /*
     * ══ Z-INDEX STACKING (welcome-banner) ══════════════════
     *  z-index 0  → aurora-bg, bg-mesh
     *  z-index 1  → blobs   (quedan visibles a la izquierda
     *                         donde la imagen es transparente)
     *  z-index 2  → imagen + sus overlays  (tapa los blobs
     *                         en la zona opaca, lado derecho)
     *  z-index 3  → partículas flotantes
     *  z-index 10 → contenido principal
     * ═══════════════════════════════════════════════════════
     */

    /* Blobs — z-index 1 */
    .wb-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.45;
        animation: wb-float 10s ease-in-out infinite alternate;
        z-index: 1;
        transition: background 0.6s ease;
    }
    @keyframes wb-float {
        0%   { transform: translate(0,0) scale(1) rotate(0deg); }
        33%  { transform: translate(12px,-18px) scale(1.06) rotate(2deg); }
        66%  { transform: translate(-8px,-10px) scale(0.96) rotate(-1deg); }
        100% { transform: translate(18px,-12px) scale(1.1) rotate(3deg); }
    }

    /* Partículas — z-index 3 (encima de la imagen) */
    .wb-particles {
        position: absolute;
        inset: 0;
        z-index: 3;
        pointer-events: none;
        overflow: hidden;
    }
    .wb-particle {
        position: absolute;
        border-radius: 50%;
        opacity: 0;
        animation: particle-float linear infinite;
    }
    @keyframes particle-float {
        0%   { opacity:0; transform:translateY(100%) scale(0); }
        10%  { opacity:1; }
        90%  { opacity:1; }
        100% { opacity:0; transform:translateY(-100vh) scale(1); }
    }

    /* Greeting */
    #greetingFull { display: block; white-space: normal; }
    .greeting-name {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2.4rem, 6.5vw, 4.6rem);
        line-height: 1.08;
        font-weight: 800;
        letter-spacing: -0.04em;
        position: relative;
    }
    .greeting-name em { font-style: italic; display: inline-block; }
    .greeting-name .name-highlight {
        position: relative; display: inline-block;
        padding-right: 0.15em; margin-right: -0.1em;
        background-image: linear-gradient(to right, #3b82f6, #6366f1, #8b5cf6, #6366f1, #3b82f6);
        background-size: 200% auto;
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        animation: name-shimmer 5s linear infinite;
    }
    html.dark .greeting-name .name-highlight {
        background-image: linear-gradient(to right, #60a5fa, #818cf8, #a78bfa, #818cf8, #60a5fa);
    }
    @keyframes name-shimmer { to { background-position: 200% center; } }
    .greeting-nowrap { display: block; white-space: normal; }
    @media (min-width: 768px) { #greetingFull, .greeting-nowrap { white-space: nowrap; } }

    .morning  .greeting-name, .afternoon .greeting-name { color: #1e3a8a; }
    .night    .greeting-name { color: #1e3a8a; }
    html.dark .night    .greeting-name { color: #f5f3ff; }
    html.dark .morning  .greeting-name, html.dark .afternoon .greeting-name { color: #e0e7ff; }

    .greeting-sub {
        font-size: clamp(0.9rem,2vw,1.05rem); font-weight: 400;
        max-width: 420px; line-height: 1.65; margin-top: 12px; opacity: 0.7;
    }
    .morning  .greeting-sub, .afternoon .greeting-sub { color: #1e3a8a; }
    .night    .greeting-sub { color: #1e3a8a; }
    html.dark .night    .greeting-sub { color: #c4b5fd; }
    html.dark .morning  .greeting-sub, html.dark .afternoon .greeting-sub { color: #a5b4fc; }

    .live-time {
        font-family: 'DM Sans', sans-serif;
        font-size: clamp(0.85rem,2vw,0.95rem); font-weight: 600;
        opacity: 0.5; letter-spacing: 0.08em; margin-top: 20px;
    }
    .morning  .live-time, .afternoon .live-time { color: #1e3a8a; }
    .night    .live-time { color: #1e3a8a; }
    html.dark .night    .live-time { color: #a5b4fc; }
    html.dark .morning  .live-time, html.dark .afternoon .live-time { color: #818cf8; }

    /* Botones */
    .wb-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
    .wb-btn-primary {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 14px 28px; border-radius: 16px; font-weight: 700; font-size: 0.9rem;
        border: none; cursor: pointer; text-decoration: none;
        transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s;
        position: relative; overflow: hidden;
    }
    .wb-btn-primary::after {
        content:''; position:absolute; inset:0;
        background: linear-gradient(105deg,transparent 40%,rgba(255,255,255,0.25) 45%,transparent 50%);
        transform: translateX(-100%); transition: transform 0.5s;
    }
    .wb-btn-primary:hover::after { transform: translateX(100%); }
    .wb-btn-primary:hover { transform: translateY(-3px) scale(1.02); }
    .wb-btn-primary:active { transform: translateY(0) scale(0.98); }
    .morning  .wb-btn-primary, .afternoon .wb-btn-primary { background:#2563eb; color:#fff; box-shadow:0 6px 20px rgba(37,99,235,0.3); }
    .morning  .wb-btn-primary:hover, .afternoon .wb-btn-primary:hover { box-shadow:0 10px 28px rgba(37,99,235,0.42); }
    html.dark .morning  .wb-btn-primary, html.dark .afternoon .wb-btn-primary { background:#4f46e5; box-shadow:0 6px 20px rgba(79,70,229,0.35); }
    .night    .wb-btn-primary { background:#2563eb; color:#fff; box-shadow:0 6px 20px rgba(37,99,235,0.3); }
    .night    .wb-btn-primary:hover { box-shadow:0 10px 28px rgba(37,99,235,0.42); }
    html.dark .night    .wb-btn-primary { background:#6d28d9; box-shadow:0 6px 20px rgba(109,40,217,0.35); }
    html.dark .night    .wb-btn-primary:hover { box-shadow:0 10px 28px rgba(109,40,217,0.45); }

    .wb-btn-secondary {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 22px; border-radius: 14px; font-weight: 600; font-size: 0.9rem;
        cursor: pointer; text-decoration: none;
        transition: transform 0.2s, background 0.2s;
    }
    .wb-btn-secondary:hover { transform: translateY(-2px); }
    .morning  .wb-btn-secondary, .afternoon .wb-btn-secondary { background:rgba(255,255,255,0.75); color:#1e3a8a; }
    html.dark .morning  .wb-btn-secondary, html.dark .afternoon .wb-btn-secondary { background:rgba(255,255,255,0.08); color:#c7d2fe; }
    .night    .wb-btn-secondary { background:rgba(255,255,255,0.75); color:#1e3a8a; }
    html.dark .night    .wb-btn-secondary { background:rgba(255,255,255,0.08); color:#e0d9ff; }

    /* Entry animation */
    @keyframes wb-slide-in {
        from { opacity:0; transform:translateY(28px) scale(0.97); filter:blur(4px); }
        to   { opacity:1; transform:translateY(0) scale(1); filter:blur(0); }
    }
    .wb-content > * { animation: wb-slide-in 0.7s cubic-bezier(0.34,1.56,0.64,1) forwards; opacity:0; }
    .wb-content > *:nth-child(1) { animation-delay:0.08s; }
    .wb-content > *:nth-child(2) { animation-delay:0.18s; }
    .wb-content > *:nth-child(3) { animation-delay:0.28s; }
    .wb-content > *:nth-child(4) { animation-delay:0.38s; }
    .wb-content > *:nth-child(5) { animation-delay:0.46s; }
    .wb-content > *:nth-child(6) { animation-delay:0.54s; }

    /* ── Guest hero ── */
    .guest-hero {
        position: relative; overflow: hidden;
        min-height: calc(100svh - 64px);
        display: flex; align-items: center;
        background: radial-gradient(circle at 50% 10%,rgba(59,130,246,0.08) 0%,transparent 60%),
                    radial-gradient(circle at 80% 80%,rgba(168,85,247,0.08) 0%,transparent 50%);
    }
    html.dark .guest-hero {
        background: radial-gradient(circle at 50% 10%,rgba(59,130,246,0.15) 0%,transparent 60%),
                    radial-gradient(circle at 80% 80%,rgba(168,85,247,0.15) 0%,transparent 50%);
    }
    .guest-shimmer-btn {
        position:relative; overflow:hidden;
        background: linear-gradient(135deg,#2563eb,#4f46e5);
        transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
        border: 1px solid rgba(255,255,255,0.1); z-index:1;
    }
    .guest-shimmer-btn::before {
        content:''; position:absolute; top:0; left:-100%;
        width:100%; height:100%;
        background: linear-gradient(90deg,transparent,rgba(255,255,255,0.3),transparent);
        transition: left 0.6s ease; z-index:-1;
    }
    .guest-shimmer-btn:hover::before { left:100%; }
    .guest-shimmer-btn:hover { transform:translateY(-4px) scale(1.02); box-shadow:0 20px 40px rgba(79,70,229,0.4); }
    .about-hover-btn {
        transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
    }
    .about-hover-btn:hover { transform:translateY(-4px) scale(1.02); box-shadow:0 20px 40px rgba(148,163,184,0.3); }
    .text-gradient-guest {
        background: linear-gradient(to right,#2563eb,#a855f7,#ec4899,#2563eb);
        background-size:200% auto; -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        animation: text-flow 5s linear infinite;
    }
    html.dark .text-gradient-guest {
        background: linear-gradient(to right,#60a5fa,#c084fc,#f472b6,#60a5fa);
        background-size:200% auto; -webkit-background-clip:text; -webkit-text-fill-color:transparent;
    }
    @keyframes text-flow { to { background-position:200% center; } }
    .guest-pill {
        backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
        background:rgba(255,255,255,0.6); border:1px solid rgba(255,255,255,0.8);
        box-shadow:0 8px 20px rgba(0,0,0,0.04);
    }
    html.dark .guest-pill {
        background:rgba(30,41,59,0.6); border:1px solid rgba(100,116,139,0.3);
        box-shadow:0 8px 20px rgba(0,0,0,0.2);
    }

    /* Liquid Glass */
    .liquid-glass {
        background: linear-gradient(135deg,rgba(255,255,255,0.65),rgba(255,255,255,0.35));
        backdrop-filter:blur(16px) saturate(190%); -webkit-backdrop-filter:blur(16px) saturate(190%);
        border:1px solid rgba(255,255,255,0.5);
        box-shadow:0 8px 32px 0 rgba(31,38,135,0.05),inset 0 1px 0 rgba(255,255,255,0.4);
        transition:all 0.4s cubic-bezier(0.23,1,0.32,1); position:relative; overflow:hidden;
    }
    html.dark .liquid-glass {
        background: linear-gradient(135deg,rgba(30,41,59,0.6),rgba(15,23,42,0.3));
        border:1px solid rgba(255,255,255,0.08);
        box-shadow:0 20px 50px -15px rgba(0,0,0,0.4),inset 0 1px 0 rgba(255,255,255,0.05);
    }
    .liquid-glass::before {
        content:''; position:absolute; top:-50%; left:-50%;
        width:200%; height:200%;
        background:radial-gradient(circle at center,rgba(255,255,255,0.1) 0%,transparent 70%);
        opacity:0.5; pointer-events:none; animation:liquid-drift 15s linear infinite;
    }
    @keyframes liquid-drift {
        0%   { transform:translate(-10%,-10%) rotate(0deg); }
        100% { transform:translate(10%,10%) rotate(360deg); }
    }
    .liquid-glass:hover {
        box-shadow:0 20px 40px -12px rgba(0,0,0,0.12),inset 0 1px 0 rgba(255,255,255,0.4);
        background:linear-gradient(135deg,rgba(255,255,255,0.7),rgba(255,255,255,0.4));
    }
    html.dark .liquid-glass:hover {
        background:linear-gradient(135deg,rgba(30,41,59,0.7),rgba(15,23,42,0.4));
        box-shadow:0 25px 60px -20px rgba(0,0,0,0.5);
    }

    .bg-mesh {
        position:absolute; inset:0; opacity:0.03; z-index:0;
        background-image:radial-gradient(#000 0.5px,transparent 0.5px);
        background-size:24px 24px; pointer-events:none;
    }
    html.dark .bg-mesh { opacity:0.02; }

    @keyframes float-y {
        0%,100% { transform:translateY(0); }
        50% { transform:translateY(-12px); }
    }
    .animate-float-y { animation:float-y 5s ease-in-out infinite; }
</style>

<?php if (isLoggedIn()): ?>
<!-- ══════════════════════════════════════════════════════
     WELCOME BANNER — Usuario con sesión activa
     ══════════════════════════════════════════════════════
     z-index 0  → aurora-bg + bg-mesh
     z-index 1  → blobs (visibles en zona transparente de la imagen)
     z-index 2  → imagen + overlays (tapa blobs en zona opaca)
     z-index 3  → partículas
     z-index 10 → contenido
     ══════════════════════════════════════════════════════ -->
<section class="welcome-banner font-option-a" id="welcomeBanner">

    <!-- z:0 fondos de ambiente -->
    <div class="aurora-bg" id="auroraBg"></div>
    <div class="bg-mesh"></div>

    <!-- z:1 blobs (ANTES de la imagen en el DOM, y z-index menor) -->
    <div class="wb-blob" id="blob1" style="width:500px;height:500px;top:-150px;left:-150px;opacity:0.6;animation-delay:0s;filter:blur(100px);"></div>
    <div class="wb-blob" id="blob2" style="width:400px;height:400px;bottom:-120px;left:40%;opacity:0.4;animation-delay:3s;filter:blur(90px);"></div>
    <div class="wb-blob" id="blob3" style="width:300px;height:300px;top:30%;right:5%;opacity:0.45;animation-delay:5s;filter:blur(80px);"></div>

    <!-- z:2 imagen + overlays (tapa blobs en el lado opaco/derecho) -->
    <div class="absolute inset-0" style="z-index:2; pointer-events:none;">
        <img src="assets/tecnm.jpg" alt="" id="wbBgImage"
             class="absolute right-0 top-0 h-full w-full md:w-[62%] object-cover object-center md:object-left"
             style="mask-image:linear-gradient(to left,black 30%,transparent 100%);
                    -webkit-mask-image:linear-gradient(to left,black 30%,transparent 100%);">
        <!-- opacidad dark via CSS para evitar clase Tailwind que no aplica bien en <img> sin Tailwind CDN compilado -->
<!-- Ponlo así (overlay oscuro separado, imagen siempre opaca): -->
<style>#wbBgImage { opacity: 1; }</style>
<!-- Overlay oscuro extra solo en dark mode, encima de la imagen pero debajo de los overlays de fusión -->
<div class="absolute inset-0 hidden dark:block" style="background:rgba(2,6,23,0.62);"></div>        <!-- overlay light: transparente a la izquierda para que se vean los blobs -->
        <div id="wbImgOverlayLight" class="absolute inset-0 dark:hidden"></div>
        <!-- overlay dark -->
        <div id="wbImgOverlayDark"  class="absolute inset-0 hidden dark:block"></div>
    </div>

    <!-- z:3 partículas flotantes -->
    <div class="wb-particles" id="wbParticles"></div>

    <!-- z:10 contenido -->
    <div class="container mx-auto px-5 sm:px-8 py-12 md:py-0 w-full" style="position:relative;z-index:10;">
        <div class="wb-content max-w-full md:max-w-2xl">

            <h1 class="greeting-name" id="greetingName">
                <span id="greetingFull" style="display:block;"></span>
                <span class="greeting-nowrap">
                    <em><span class="name-highlight"><?php echo htmlspecialchars($user_name); ?></span></em>&thinsp;!<span id="greetingEmoji"></span>
                </span>
            </h1>

            <p class="greeting-sub" id="greetingSub">Listo para hacer la diferencia hoy. Aquí tienes lo que necesitas.</p>

            <p class="live-time" id="liveTime">──</p>

            <div class="wb-actions">
                <?php if ($user_role === 'admin'): ?>
                    <a href="dashboard.php" class="wb-btn-primary">
                        <i class="ph-bold ph-chart-line-up"></i> Dashboard
                    </a>
                    <a href="admin_settings.php" class="wb-btn-secondary">
                        <i class="ph-bold ph-gear"></i> Configuración admin
                    </a>
                <?php elseif ($user_role === 'manager'): ?>
                    <a href="dashboard.php" class="wb-btn-primary">
                        <i class="ph-bold ph-chart-line-up"></i> Dashboard
                    </a>
                <?php else: ?>
                    <a href="submit_complaint.php" class="wb-btn-primary">
                        <i class="ph-bold ph-plus-circle"></i> Nuevo reporte
                    </a>
                    <a href="my_complaints.php" class="wb-btn-secondary">
                        <i class="ph-bold ph-folder-open"></i> Mis reportes
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<script>
(function () {
    const banner    = document.getElementById('welcomeBanner');
    const auroraBg  = document.getElementById('auroraBg');
    const blob1     = document.getElementById('blob1');
    const blob2     = document.getElementById('blob2');
    const blob3     = document.getElementById('blob3');
    const particles = document.getElementById('wbParticles');
    const greetingFull  = document.getElementById('greetingFull');
    const greetingEmoji = document.getElementById('greetingEmoji');
    const sub       = document.getElementById('greetingSub');
    const liveTime  = document.getElementById('liveTime');
    const overlayLight = document.getElementById('wbImgOverlayLight');
    const overlayDark  = document.getElementById('wbImgOverlayDark');

    /* Partículas */
    function spawnParticles() {
        if (!particles) return;
        particles.innerHTML = '';
        const count = window.innerWidth < 768 ? 12 : 24;
        for (let i = 0; i < count; i++) {
            const p = document.createElement('div');
            p.className = 'wb-particle';
            const size=Math.random()*4+2, left=Math.random()*100,
                  dur=Math.random()*12+8, delay=Math.random()*12;
            p.style.cssText=`width:${size}px;height:${size}px;left:${left}%;bottom:-10px;animation-duration:${dur}s;animation-delay:${delay}s;`;
            particles.appendChild(p);
        }
    }
    spawnParticles();

    /* Periodo */
    const TEST_PERIOD = null;
    function getPeriod(h) {
        if (TEST_PERIOD) return TEST_PERIOD;
        if (h>=6  && h<12) return 'morning';
        if (h>=12 && h<19) return 'afternoon';
        return 'night';
    }

    const userRole = <?php echo json_encode($user_role); ?>;
    const isStaff  = ['admin','manager'].includes(userRole);

    const subMessages = {
        morning:   { staff:'Tienes el control del día. Revisa los reportes pendientes y gestiona el seguimiento de tu equipo.', student:'Empieza el día con el pie derecho. Si tienes algo que reportar, este es tu espacio.' },
        afternoon: { staff:'La tarde es buen momento para revisar el estado de los casos abiertos y dar respuesta a tu comunidad.', student:'Si viviste algo hoy que merece atención, no lo dejes pasar. Tu reporte importa.' },
        night:     { staff:'Antes de cerrar, revisa si hay reportes recientes que requieran tu atención urgente.', student:'Si algo te preocupa, puedes registrarlo aquí cuando quieras.' },
    };

    const config = {
        morning:   { greeting:'¡Buenos días, ',   emoji:' ☀️',  blob1:'#bfdbfe',blob2:'#dbeafe',blob3:'#c7d2fe',particle:'rgba(59,130,246,0.35)'  },
        afternoon: { greeting:'¡Buenas tardes, ', emoji:' 🌤️', blob1:'#93c5fd',blob2:'#bfdbfe',blob3:'#a5b4fc',particle:'rgba(99,102,241,0.3)'   },
        night:     { greeting:'¡Buenas noches, ', emoji:' 🌙',  blob1:'#bfdbfe',blob2:'#c4b5fd',blob3:'#a5b4fc',particle:'rgba(99,102,241,0.25)'  },
    };
    const darkOverrides = {
        morning:   { blob1:'#3b82f6',blob2:'#6366f1',blob3:'#4f46e5',particle:'rgba(129,140,248,0.4)'  },
        afternoon: { blob1:'#4f46e5',blob2:'#6366f1',blob3:'#7c3aed',particle:'rgba(129,140,248,0.35)' },
        night:     { blob1:'#6D28D9',blob2:'#4338CA',blob3:'#5B21B6',particle:'rgba(167,139,250,0.4)'  },
    };

    /* Reloj */
    function tick() {
        const now=new Date(), h=now.getHours(),
              m=String(now.getMinutes()).padStart(2,'0'),
              s=String(now.getSeconds()).padStart(2,'0');
        const days=['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        const months=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        liveTime.textContent=`${days[now.getDay()]}, ${now.getDate()} de ${months[now.getMonth()]} · ${h}:${m}:${s}`;
    }

    /* Aplicar periodo */
    function apply() {
        const now=new Date(), period=getPeriod(now.getHours());
        const isDark=document.documentElement.classList.contains('dark');
        const cfg=config[period], dk=darkOverrides[period];

        banner.className='welcome-banner '+period;
        auroraBg.className='aurora-bg '+period;

        blob1.style.background=isDark?dk.blob1:cfg.blob1;
        blob2.style.background=isDark?dk.blob2:cfg.blob2;
        if(blob3) blob3.style.background=isDark?dk.blob3:cfg.blob3;

        document.querySelectorAll('.wb-particle').forEach(p=>p.style.background=isDark?dk.particle:cfg.particle);

        /*
         * Overlays de la imagen:
         * — izquierda (0%–38%): transparente → los blobs del lado izquierdo
         *   siguen visibles por detrás de la zona sin imagen.
         * — centro (38%–65%): suave fusión → mezcla imagen con el fondo.
         * — derecha (>65%): totalmente transparente → la imagen ya es opaca
         *   por su propio mask y no necesita overlay.
         */
        const lightGrad=`linear-gradient(to right,transparent 38%,rgba(248,250,252,0.55) 50%,rgba(248,250,252,0.2) 62%,transparent 70%)`;
        const darkGrad =`linear-gradient(to right,transparent 38%,rgba(2,6,23,0.55) 50%,rgba(2,6,23,0.2) 62%,transparent 70%)`;

        if(overlayLight) overlayLight.style.background=lightGrad;
        if(overlayDark)  overlayDark.style.background=darkGrad;

        greetingFull.textContent=cfg.greeting;
        greetingEmoji.textContent=cfg.emoji;
        sub.textContent=subMessages[period][isStaff?'staff':'student'];
    }

    apply(); tick();
    setInterval(tick,1000);
    setInterval(apply,60000);
    new MutationObserver(()=>apply()).observe(document.documentElement,{attributes:true,attributeFilter:['class']});
})();
</script>



<?php else: ?>
<!-- ══════════════════════════════════
     HERO SECTION — Usuario no logueado
     ══════════════════════════════════
     Dentro del div de fondo (-z-10):
       z:0  → blobs decorativos
       z:0  → bg-mesh
       z:10 → imagen + overlays  (tapa los blobs a la derecha)
     Fuera:
       z:10 → contenido
     ══════════════════════════════════ -->
<section class="guest-hero bg-transparent relative">

    <!-- Contenedor de capas de fondo -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none bg-slate-50 dark:bg-transparent" style="z-index:0;">

        <!-- z:0 blobs -->
        <div class="absolute -top-[10%] -left-[10%] w-[55%] h-[55%] rounded-full bg-blue-400/15 blur-[120px] animate-pulse" style="z-index:0;"></div>
        <div class="absolute bottom-[0%] left-[-5%] w-[45%] h-[45%] rounded-full bg-emerald-400/10 blur-[130px] animate-pulse" style="z-index:0;animation-delay:4s;"></div>
        <div class="bg-mesh"></div>

        <!-- z:10 imagen + overlays (encima de los blobs) -->
        <div class="absolute inset-0" style="z-index:10;">
            <img src="assets/tecnm.jpg" alt=""
                 class="absolute right-0 top-0 h-full w-full md:w-[62%] object-cover object-center md:object-left"
                 style="opacity:1;
                        mask-image:linear-gradient(to left,black 30%,transparent 100%);
                        -webkit-mask-image:linear-gradient(to left,black 30%,transparent 100%);">
            <!-- Ponlo así (agrega un div oscuro dentro del div de la imagen, antes de los overlays): -->
<style>.guest-hero img { opacity: 1; }</style>
<div class="absolute inset-0 hidden dark:block" style="background:rgba(2,6,23,0.62);"></div>
            <!-- Light overlay: sólido a la izquierda para ocultar el borde de inicio de imagen,
                 transparente a la derecha para dejar ver la foto -->
            <div class="absolute inset-0 dark:hidden"
                 style="background:linear-gradient(to right,
                     rgb(248 250 252) 0%,
                     rgb(248 250 252) 35%,
                     rgba(248,250,252,0.65) 48%,
                     rgba(248,250,252,0.2) 60%,
                     transparent 70%);"></div>
            <!-- Dark overlay -->
            <div class="absolute inset-0 hidden dark:block"
                 style="background:linear-gradient(to right,
                     rgb(2 6 23) 0%,
                     rgb(2 6 23) 35%,
                     rgba(2,6,23,0.65) 48%,
                     rgba(2,6,23,0.2) 60%,
                     transparent 70%);"></div>
        </div>
    </div>

    <!-- Contenido -->
    <div class="container mx-auto px-6 py-16 lg:py-0" style="position:relative;z-index:10;">
        <div class="max-w-4xl text-center lg:text-left" data-aos="fade-right" data-aos-duration="1000">
            <div class="liquid-glass inline-flex items-center space-x-2 py-2 px-5 rounded-full text-blue-800 dark:text-blue-400 text-sm font-extrabold mb-6 animate-float-y">
                <i class="ph-fill ph-megaphone-simple text-base"></i>
                <span class="tracking-widest uppercase text-[10px]">Sistema Oficial de Comunicación</span>
            </div>
            <h1 class="text-5xl md:text-6xl lg:text-7xl font-black text-slate-900 dark:text-white tracking-tight mb-6 leading-[1.05]" style="font-family:'Outfit',sans-serif;">
                Tu voz construye <br class="hidden lg:block">
                <span class="text-gradient-guest selection:bg-transparent">nuestro futuro</span>
            </h1>
            <p class="text-base md:text-lg text-slate-700 dark:text-slate-300 max-w-2xl lg:mx-0 mx-auto mb-10 leading-relaxed font-medium">
                Un espacio seguro, transparente y eficiente para compartir tus inquietudes, sugerencias y reconocimientos. Juntos hacemos del <strong class="text-slate-900 dark:text-white">TecNM</strong> una mejor institución.
            </p>
            <div class="flex flex-col sm:flex-row items-center lg:justify-start justify-center gap-5">
                <a href="login.php" class="guest-shimmer-btn w-full sm:w-auto px-10 py-4 text-white font-bold rounded-2xl flex items-center justify-center gap-3 shadow-2xl">
                    <span class="text-lg tracking-wide font-extrabold">Iniciar Sesión</span>
                    <i class="ph-bold ph-arrow-right text-xl"></i>
                </a>
                <a href="about.php" class="about-hover-btn liquid-glass w-full sm:w-auto px-10 py-4 font-bold rounded-2xl flex items-center justify-center gap-3 text-slate-800 dark:text-slate-200 border border-white/40 hover:bg-white/30">
                    <span class="text-lg tracking-wide font-extrabold">Acerca de</span>
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!isLoggedIn()): ?>
<!-- ══════════════════════════
     HOW IT WORKS SECTION
     ══════════════════════════ -->
<section class="py-32 relative z-10 overflow-hidden bg-transparent">
    <div class="container mx-auto px-4">
        <div class="text-center mb-20" data-aos="fade-up">
            <h2 class="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white mb-4" style="font-family:'Outfit',sans-serif;">¿Cómo funciona?</h2>
            <p class="text-slate-500 dark:text-slate-400 text-lg font-medium tracking-wide">Tu reporte en 3 simples pasos</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative max-w-6xl mx-auto">
            <div class="hidden md:block absolute top-[4.5rem] left-[20%] w-[60%] h-0 border-t-2 border-dashed border-slate-300 dark:border-slate-700 z-0" data-aos="fade-in" data-aos-duration="1000" data-aos-delay="200"></div>
            <div class="liquid-glass rounded-3xl p-8 text-center relative z-10" data-aos="fade-up" data-aos-delay="0">
                <div class="w-20 h-20 mx-auto bg-blue-500/20 rounded-2xl flex items-center justify-center mb-8 relative border border-blue-500/20 shadow-inner">
                    <i class="ph-duotone ph-sign-in text-4xl text-blue-600 dark:text-blue-400"></i>
                    <div class="absolute -top-3 -right-3 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold shadow-lg shadow-blue-500/30">1</div>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-3" style="font-family:'Outfit',sans-serif;">Inicia Sesión</h3>
                <p class="text-slate-700 dark:text-slate-300 leading-relaxed">Accede a tu cuenta institucional mediante Microsoft 365 para garantizar la seguridad y seguimiento.</p>
            </div>
            <div class="liquid-glass rounded-3xl p-8 text-center relative z-10" data-aos="fade-up" data-aos-delay="150">
                <div class="w-20 h-20 mx-auto bg-purple-500/20 rounded-2xl flex items-center justify-center mb-8 relative border border-purple-500/20 shadow-inner">
                    <i class="ph-duotone ph-pencil-simple text-4xl text-purple-600 dark:text-purple-400"></i>
                    <div class="absolute -top-3 -right-3 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold shadow-lg shadow-purple-500/30">2</div>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-3" style="font-family:'Outfit',sans-serif;">Crea tu Reporte</h3>
                <p class="text-slate-700 dark:text-slate-300 leading-relaxed">Describe la situación detalladamente, selecciona una categoría adecuada y adjunta evidencia gráfica si es necesario.</p>
            </div>
            <div class="liquid-glass rounded-3xl p-8 text-center relative z-10" data-aos="fade-up" data-aos-delay="300">
                <div class="w-20 h-20 mx-auto bg-pink-500/10 dark:bg-pink-500/20 rounded-2xl flex items-center justify-center mb-8 relative border border-pink-500/20 shadow-inner">
                    <i class="ph-duotone ph-check-circle text-4xl text-pink-600 dark:text-pink-400"></i>
                    <div class="absolute -top-3 -right-3 w-8 h-8 bg-pink-600 text-white rounded-full flex items-center justify-center font-bold shadow-lg shadow-pink-500/30">3</div>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-3" style="font-family:'Outfit',sans-serif;">Recibe Respuesta</h3>
                <p class="text-slate-600 dark:text-slate-400 leading-relaxed">Las autoridades evaluarán tu caso de forma imparcial y recibirás notificaciones en tiempo real sobre el progreso.</p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════
     STATS / CATEGORIES SECTION
     ══════════════════════════ -->
<section class="py-28 relative z-10 bg-slate-50/40 dark:bg-slate-900/40 overflow-hidden border-y border-slate-200/50 dark:border-slate-800/50 backdrop-blur-[2px]">
    <div class="absolute inset-0 opacity-[0.03] dark:opacity-10" style="background-image:radial-gradient(#cbd5e1 1.5px,transparent 1.5px);background-size:40px 40px;"></div>
    <div class="container mx-auto px-4 relative z-10">
        <div class="text-center mb-20" data-aos="fade-up">
            <h2 class="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white mb-4" style="font-family:'Outfit',sans-serif;">Transparencia en tiempo real</h2>
            <p class="text-slate-500 dark:text-slate-400 text-lg font-medium tracking-wide">Actividad registrada en los últimos 30 días</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php
        $stmt = $conn->prepare("SELECT COALESCE(c.category_id,0) as category_id,cat.name as category_name,COUNT(*) as count
                              FROM complaints c LEFT JOIN categories cat ON c.category_id=cat.id
                              WHERE c.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
                              GROUP BY c.category_id,cat.name ORDER BY c.category_id IS NULL,count DESC");
        $stmt->execute();
        $result=$stmt->get_result();
        $category_info=[
            0 =>['from'=>'from-gray-500',   'to'=>'to-slate-500',  'icon'=>'ph-file-text'],
            1 =>['from'=>'from-blue-500',   'to'=>'to-cyan-500',   'icon'=>'ph-wifi-high'],
            2 =>['from'=>'from-indigo-500', 'to'=>'to-purple-500', 'icon'=>'ph-chalkboard-teacher'],
            3 =>['from'=>'from-emerald-500','to'=>'to-teal-500',   'icon'=>'ph-books'],
            4 =>['from'=>'from-amber-500',  'to'=>'to-orange-500', 'icon'=>'ph-flask'],
            5 =>['from'=>'from-green-500',  'to'=>'to-emerald-600','icon'=>'ph-basketball'],
            6 =>['from'=>'from-amber-500',  'to'=>'to-orange-600', 'icon'=>'ph-fork-knife'],
            7 =>['from'=>'from-sky-500',    'to'=>'to-blue-500',   'icon'=>'ph-toilet'],
            8 =>['from'=>'from-zinc-500',   'to'=>'to-slate-600',  'icon'=>'ph-car'],
            9 =>['from'=>'from-fuchsia-500','to'=>'to-purple-600', 'icon'=>'ph-chalkboard-teacher'],
            10=>['from'=>'from-indigo-500', 'to'=>'to-blue-600',   'icon'=>'ph-book-open'],
            11=>['from'=>'from-yellow-500', 'to'=>'to-amber-600',  'icon'=>'ph-exam'],
            12=>['from'=>'from-blue-500',   'to'=>'to-indigo-600', 'icon'=>'ph-folders'],
            13=>['from'=>'from-emerald-500','to'=>'to-teal-600',   'icon'=>'ph-handshake'],
            14=>['from'=>'from-rose-500',   'to'=>'to-pink-600',   'icon'=>'ph-credit-card'],
            15=>['from'=>'from-sky-500',    'to'=>'to-cyan-600',   'icon'=>'ph-headphones'],
            16=>['from'=>'from-violet-500', 'to'=>'to-purple-600', 'icon'=>'ph-megaphone'],
            17=>['from'=>'from-red-600',    'to'=>'to-rose-700',   'icon'=>'ph-prohibit'],
            18=>['from'=>'from-red-500',    'to'=>'to-orange-600', 'icon'=>'ph-warning'],
            19=>['from'=>'from-green-600',  'to'=>'to-emerald-700','icon'=>'ph-shield-check'],
            20=>['from'=>'from-pink-500',   'to'=>'to-fuchsia-600','icon'=>'ph-target'],
        ];
        $default_info=['from'=>'from-gray-500','to'=>'to-slate-500','icon'=>'ph-file-text'];
        $delay=0;
        while($row=$result->fetch_assoc()){
            $category_name=$row['category_id']==0?"Sin Categoría":($row['category_name']??"Categoría Desconocida");
            $info=$category_info[$row['category_id']]??$default_info;
            $delay+=50;
        ?>
            <div class="liquid-glass rounded-2xl p-6 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="<?php echo $delay;?>">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?php echo $info['from'];?> <?php echo $info['to'];?> flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <i class="<?php echo $info['icon'];?> text-white ph-fill text-2xl"></i>
                    </div>
                    <span class="text-3xl font-black text-slate-800"><?php echo $row['count'];?></span>
                </div>
                <h3 class="font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($category_name);?>"><?php echo htmlspecialchars($category_name);?></h3>
                <div class="flex items-center mt-2 text-xs font-medium text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                    Activos recientemente
                </div>
            </div>
        <?php } ?>
        </div>
    </div>
</section>

<?php if (!isLoggedIn()): ?>
<!-- ══════════════════════════
     FEATURES SECTION
     ══════════════════════════ -->
<section class="pt-32 pb-40 relative overflow-hidden bg-transparent">
    <div class="absolute inset-0 bg-gradient-to-b from-transparent to-slate-50/50 dark:to-slate-900/80 -z-20"></div>
    <div class="container mx-auto px-4 relative z-10">
        <div class="text-center mb-20" data-aos="fade-up">
            <h2 class="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white" style="font-family:'Outfit',sans-serif;">Por qué usar el Buzón de Quejas</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            <div class="liquid-glass rounded-3xl p-10 group" data-aos="fade-up" data-aos-delay="0">
                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center mb-8 shadow-lg group-hover:scale-110 transition-transform duration-300">
                    <i class="ph-fill ph-leaf text-3xl text-white"></i>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4" style="font-family:'Outfit',sans-serif;">Sin papeletas</h3>
                <p class="text-slate-700 dark:text-slate-300 leading-relaxed text-lg">El buzón físico quedó en el pasado. Envía tu queja, sugerencia o felicitación desde tu celular o computadora en segundos. Sin imprimir nada, 100% eco-friendly y sin depender de que alguien vacíe el buzón.</p>
            </div>
            <div class="liquid-glass rounded-3xl p-10 group" data-aos="fade-up" data-aos-delay="100">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mb-8 shadow-lg group-hover:scale-110 transition-transform duration-300">
                    <i class="ph-fill ph-shield-check text-3xl text-white"></i>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4" style="font-family:'Outfit',sans-serif;">Seguro y confidencial</h3>
                <p class="text-slate-700 dark:text-slate-300 leading-relaxed text-lg">Tu identidad está protegida en todo momento. Puedes reportar irregularidades o situaciones incómodas sin temor a represalias, sabiendo que tu información es manejada con total discreción.</p>
            </div>
            <div class="liquid-glass rounded-3xl p-10 group" data-aos="fade-up" data-aos-delay="200">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center mb-8 shadow-lg group-hover:scale-110 transition-transform duration-300">
                    <i class="ph-fill ph-timer text-3xl text-white"></i>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-4" style="font-family:'Outfit',sans-serif;">Seguimiento en tiempo real</h3>
                <p class="text-slate-700 dark:text-slate-300 leading-relaxed text-lg">Olvídate de entregar algo y nunca saber qué pasó. Puedes consultar el estado de tu reporte en cualquier momento: si está siendo revisado, en proceso o ya atendido. Tienes visibilidad completa desde el momento en que lo envías.</p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include 'components/footer.php'; ?>