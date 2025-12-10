# Guía Anti-Spam - ITSCC Buzón Digital

Esta guía explica las configuraciones implementadas para evitar que los correos electrónicos del sistema **Buzón Digital ITSCC** lleguen a la carpeta de spam.

---

## 🎯 Configuración Actual: Gmail (@gmail.com)

El sistema está configurado para enviar correos desde **buzonitscc@gmail.com** usando el servidor SMTP de Gmail (`smtp.gmail.com`).

### ✅ Ventajas de usar Gmail:

| Aspecto | Estado |
|---------|--------|
| **SPF** | ✅ Gmail lo maneja automáticamente |
| **DKIM** | ✅ Gmail firma todos los correos automáticamente |
| **DMARC** | ✅ Gmail cumple con las políticas DMARC |
| **Reputación** | ✅ Heredas la excelente reputación de Gmail |
| **TLS/SSL** | ✅ Siempre cifrado |
| **Límites** | ⚠️ 500 emails/día (cuenta gratuita) |

### 🔑 Requisitos para Gmail:

1. **Contraseña de aplicación** - Ya configurada en `config/email_config.php`
2. **Verificación en 2 pasos** - Debe estar activada en la cuenta de Gmail
3. **Acceso SMTP** - Habilitado automáticamente con contraseña de app

---

## � Mejoras Implementadas en el Código

Se han añadido las siguientes configuraciones anti-spam en todos los correos:

### Headers Añadidos:

| Header | Valor | Propósito |
|--------|-------|-----------|
| `Message-ID` | Único por correo | Evita duplicados y mejora tracking |
| `X-Mailer` | ITSCC Buzon Digital Mailer v2.0 | Identifica el sistema como legítimo |
| `Reply-To` | buzonitscc@gmail.com | Respuestas van al buzón correcto |
| `X-Priority` | 3 (Normal) | Evita parecer spam urgente |
| `Organization` | Instituto Tecnológico Superior de Ciudad Constitución | Legitimidad institucional |
| `Precedence` | bulk | Indica correo transaccional legítimo |
| `Auto-Submitted` | auto-generated | Correo automático válido |
| `List-Unsubscribe` | mailto:buzonitscc@gmail.com | **Muy importante para Gmail** |
| `Feedback-ID` | buzon-itscc:notification:itscc | Para estadísticas |

### Otras Mejoras:

- ✅ **Codificación UTF-8** con base64 para caracteres especiales
- ✅ **Texto alternativo (AltBody)** en todos los correos - Los correos sin versión texto son sospechosos
- ✅ **Ratio equilibrado texto/HTML** - Mejora puntuación anti-spam
- ✅ **Verificación SSL/TLS** habilitada

---

## 📁 Archivos Involucrados

| Archivo | Función |
|---------|---------|
| `config/email_config.php` | Credenciales SMTP de Gmail |
| `config/email_antispam.php` | Configuraciones anti-spam centralizadas |
| `send_email.php` | Notificaciones a departamentos |
| `send_verification_email.php` | Códigos de verificación/registro |
| `daily_summary.php` | Resumen diario automático |

---

## 🧪 Cómo Verificar que los Correos NO van a Spam

### 1. Prueba con Mail-Tester (Recomendado)

1. Ve a https://www.mail-tester.com/
2. Copia la dirección de correo temporal que te dan
3. Envía un correo de prueba desde tu sistema a esa dirección
4. Revisa el puntaje (deberías obtener 8/10 o más)

### 2. Verifica Headers en Gmail

1. Abre un correo recibido en Gmail
2. Haz clic en los 3 puntos (⋮) → "Mostrar original"
3. Busca:
   - `SPF: PASS`
   - `DKIM: PASS`
   - `DMARC: PASS`

> **Nota:** Google Postmaster Tools no aplica para cuentas `@gmail.com`. Solo funciona si tienes un dominio propio.

---

## 🚨 Si los Correos Siguen Llegando a Spam

### Posibles Causas y Soluciones:

| Problema | Solución |
|----------|----------|
| Primera vez que se envía al destinatario | Pedir que marquen "No es spam" y agreguen como contacto |
| Filtros personales del destinatario | El destinatario debe revisar sus filtros |
| Contenido del correo parece spam | Evitar palabras como "urgente", "gratis", exceso de mayúsculas |
| Muchos correos en poco tiempo | Espaciar los envíos, no enviar en ráfagas |
| Correos rebotados | Verificar que las direcciones sean válidas |

### Acciones Recomendadas para Destinatarios:

1. **Marcar como "No es spam"** si llega a esa carpeta
2. **Agregar buzonitscc@gmail.com a contactos**
3. **Crear un filtro** para que siempre vaya a Inbox:
   - En Gmail: Configuración → Filtros → Crear filtro
   - De: buzonitscc@gmail.com → Nunca enviar a spam

---

## 📊 Límites de Gmail

| Tipo de Cuenta | Límite Diario |
|----------------|---------------|
| Gmail Gratuito | 500 emails/día |
| Google Workspace | 2,000 emails/día |

Si necesitas enviar más correos, considera:
- Google Workspace (de pago)
- Servicios como SendGrid, Mailgun, Amazon SES

---

## 📞 Soporte

Si tienes problemas con la entrega de correos:

1. Verifica que la contraseña de aplicación siga siendo válida
2. Revisa que la cuenta de Gmail no esté bloqueada
3. Verifica que no hayas excedido el límite diario

---

*Última actualización: Diciembre 2024*  
*Sistema Buzón Digital ITSCC v2.0*
