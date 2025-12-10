<?php
/**
 * Configuración Anti-Spam para PHPMailer
 * 
 * Este archivo contiene funciones y configuraciones para mejorar la entregabilidad
 * de los correos electrónicos y evitar que lleguen a la carpeta de spam.
 * 
 * IMPORTANTE: Para una protección completa anti-spam, también debes configurar:
 * 
 * 1. SPF (Sender Policy Framework) - Registro DNS TXT:
 *    Ejemplo para Gmail: v=spf1 include:_spf.google.com ~all
 * 
 * 2. DKIM (DomainKeys Identified Mail) - Gmail maneja esto automáticamente
 *    cuando usas smtp.gmail.com con autenticación.
 * 
 * 3. DMARC - Registro DNS TXT:
 *    Ejemplo: v=DMARC1; p=none; rua=mailto:postmaster@tudominio.com
 */

/**
 * Aplica configuraciones anti-spam al objeto PHPMailer
 * 
 * @param PHPMailer\PHPMailer\PHPMailer $mail Objeto PHPMailer
 * @param string $fromEmail Email del remitente
 * @param string $fromName Nombre del remitente
 * @return void
 */
function applyAntiSpamConfig($mail, $fromEmail = null, $fromName = null) {
    // Usar valores por defecto si no se proporcionan
    $fromEmail = $fromEmail ?? SMTP_FROM_EMAIL;
    $fromName = $fromName ?? SMTP_FROM_NAME;
    
    // ========================================
    // 1. HEADERS ESENCIALES ANTI-SPAM
    // ========================================
    
    // Message-ID único y bien formado (crítico para evitar spam)
    // Extraer dominio del email del remitente para consistencia
    $emailParts = explode('@', $fromEmail);
    $domain = isset($emailParts[1]) ? $emailParts[1] : 'itscc.edu.mx';
    $messageId = sprintf(
        '<%s.%s@%s>',
        bin2hex(random_bytes(8)),
        time(),
        $domain
    );
    $mail->MessageID = $messageId;
    
    // XMailer - Identificador profesional del sistema de correo
    $mail->XMailer = 'ITSCC Buzon Digital Mailer v2.0';
    
    // Reply-To - Dirección de respuesta (ayuda a la legitimidad)
    $mail->addReplyTo($fromEmail, $fromName);
    
    // ========================================
    // 2. PRIORIDAD Y TIPO DE CORREO
    // ========================================
    
    // Prioridad normal (3) - Evita parecer spam urgente
    // 1 = Alta, 3 = Normal, 5 = Baja
    $mail->Priority = 3;
    
    // Headers de prioridad adicionales para compatibilidad
    $mail->addCustomHeader('X-Priority', '3');
    $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
    $mail->addCustomHeader('Importance', 'Normal');
    
    // ========================================
    // 3. HEADERS DE ORGANIZACIÓN
    // ========================================
    
    // Organización (añade legitimidad)
    $mail->addCustomHeader('Organization', 'Instituto Tecnologico Superior de Ciudad Constitucion');
    
    // Precedencia: bulk para correos transaccionales/notificaciones
    // Esto ayuda a que los filtros no lo marquen como spam
    $mail->addCustomHeader('Precedence', 'bulk');
    
    // Auto-Submitted: Indica que es un correo automático legítimo
    $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
    
    // ========================================
    // 4. HEADERS DE FEEDBACK/UNSUBSCRIBE
    // ========================================
    
    // List-Unsubscribe (muy importante para Gmail y Outlook)
    // Aunque sea un sistema interno, tener este header mejora la reputación
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . $fromEmail . '?subject=Unsubscribe>');
    $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
    
    // Feedback-ID para tracking (útil para Gmail Postmaster Tools)
    $mail->addCustomHeader('Feedback-ID', 'buzon-itscc:notification:itscc');
    
    // ========================================
    // 5. CONFIGURACIÓN DE CODIFICACIÓN
    // ========================================
    
    // Asegurar UTF-8 en todo el correo
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64'; // base64 es más seguro para caracteres especiales
    
    // Content-Type con boundary limpio
    $mail->ContentType = 'text/html; charset=UTF-8';
    
    // ========================================
    // 6. CONFIGURACIÓN SMTP OPTIMIZADA
    // ========================================
    
    // Habilitar verificación de certificados SSL (seguridad)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ];
    
    // Timeout optimizado
    $mail->Timeout = 30;
    
    // Keep-alive para múltiples envíos
    $mail->SMTPKeepAlive = true;
}

/**
 * Genera un cuerpo de texto plano bien formateado desde HTML
 * Esto es CRÍTICO para evitar spam - los correos sin texto plano son sospechosos
 * 
 * @param string $html Contenido HTML
 * @return string Contenido en texto plano
 */
function generatePlainTextFromHtml($html) {
    // Remover scripts y estilos
    $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
    
    // Convertir enlaces a formato texto
    $text = preg_replace('/<a[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/i', '$2 ($1)', $text);
    
    // Convertir saltos de línea HTML
    $text = preg_replace('/<br[^>]*>/i', "\n", $text);
    $text = preg_replace('/<\/?(p|div|h[1-6]|tr|li)[^>]*>/i', "\n", $text);
    
    // Remover todas las etiquetas HTML restantes
    $text = strip_tags($text);
    
    // Decodificar entidades HTML
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Limpiar espacios en blanco excesivos
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n\s*\n/', "\n\n", $text);
    $text = trim($text);
    
    return $text;
}

/**
 * Valida que el correo cumpla con las mejores prácticas anti-spam
 * 
 * @param PHPMailer\PHPMailer\PHPMailer $mail Objeto PHPMailer
 * @return array ['valid' => bool, 'issues' => array]
 */
function validateEmailForDelivery($mail) {
    $issues = [];
    
    // Verificar que tenga cuerpo de texto plano
    if (empty($mail->AltBody)) {
        $issues[] = 'Falta el cuerpo de texto plano (AltBody) - crítico para evitar spam';
    }
    
    // Verificar longitud del asunto
    if (strlen($mail->Subject) > 78) {
        $issues[] = 'El asunto es demasiado largo (máximo recomendado: 78 caracteres)';
    }
    
    // Verificar palabras spam comunes en el asunto
    $spamWords = ['gratis', 'urgente', '!!!', 'dinero', 'ganador', 'premio', 'oferta', 'descuento'];
    foreach ($spamWords as $word) {
        if (stripos($mail->Subject, $word) !== false) {
            $issues[] = "El asunto contiene palabra potencialmente spam: '$word'";
        }
    }
    
    // Verificar que tenga destinatarios
    if (empty($mail->getAllRecipientAddresses())) {
        $issues[] = 'No hay destinatarios configurados';
    }
    
    // Verificar que tenga remitente
    if (empty($mail->From)) {
        $issues[] = 'No hay remitente configurado';
    }
    
    return [
        'valid' => empty($issues),
        'issues' => $issues
    ];
}

/**
 * Lista de verificación para configuración DNS (para referencia del administrador)
 */
function getDnsChecklistForAdmin() {
    return [
        'SPF' => [
            'descripcion' => 'Sender Policy Framework - Autoriza servidores que pueden enviar correo',
            'tipo' => 'TXT',
            'host' => '@',
            'valor' => 'v=spf1 include:_spf.google.com ~all',
            'nota' => 'Este registro permite que Gmail envíe correos en nombre de tu dominio'
        ],
        'DKIM' => [
            'descripcion' => 'DomainKeys Identified Mail - Firma digital para correos',
            'nota' => 'Gmail maneja DKIM automáticamente cuando usas smtp.gmail.com con autenticación OAuth o app password'
        ],
        'DMARC' => [
            'descripcion' => 'Domain-based Message Authentication - Política de autenticación',
            'tipo' => 'TXT',
            'host' => '_dmarc',
            'valor' => 'v=DMARC1; p=none; rua=mailto:postmaster@tudominio.com',
            'nota' => 'Comienza con p=none para monitoreo, luego cambia a p=quarantine o p=reject'
        ],
        'PTR' => [
            'descripcion' => 'Registro inverso - Asocia IP con nombre de dominio',
            'nota' => 'Gmail maneja esto automáticamente. Solo aplica si usas tu propio servidor SMTP'
        ]
    ];
}

/**
 * Obtiene consejos para mejorar la entregabilidad
 */
function getDeliverabilityTips() {
    return [
        'contenido' => [
            'Mantén un ratio equilibrado entre texto e imágenes',
            'Evita usar solo mayúsculas en el asunto',
            'No uses exceso de signos de puntuación (!!!, ???)',
            'Incluye siempre una versión de texto plano del correo',
            'Usa un diseño HTML limpio y bien estructurado'
        ],
        'tecnico' => [
            'Configura correctamente SPF, DKIM y DMARC en tu DNS',
            'Usa TLS/SSL para conexiones SMTP',
            'Mantén una tasa de rebote baja (menos del 2%)',
            'No envíes correos a direcciones inexistentes',
            'Implementa doble opt-in para listas de correo'
        ],
        'reputacion' => [
            'Envía correos consistentemente (no en ráfagas)',
            'Mantén buena proporción de apertura/clics',
            'Procesa las solicitudes de baja rápidamente',
            'Monitorea tu reputación en Google Postmaster Tools',
            'Evita usar acortadores de URLs públicos'
        ]
    ];
}
?>
