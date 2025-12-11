# Manual del Desarrollador - Buzón de Quejas ITSCC

## Tabla de Contenidos

1. [Introducción](#introducción)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Requisitos del Sistema](#requisitos-del-sistema)
4. [Instalación y Configuración](#instalación-y-configuración)
5. [Estructura del Proyecto](#estructura-del-proyecto)
6. [Base de Datos](#base-de-datos)
7. [Autenticación y Autorización](#autenticación-y-autorización)
8. [Sistema de Estados de Reportes](#sistema-de-estados-de-reportes)
9. [Gestión de Correos Electrónicos](#gestión-de-correos-electrónicos)
10. [Integración con Gemini AI](#integración-con-gemini-ai)
11. [API Endpoints AJAX](#api-endpoints-ajax)
12. [Componentes Reutilizables](#componentes-reutilizables)
13. [Sistema de Archivos y Uploads](#sistema-de-archivos-y-uploads)
14. [Configuración del Administrador](#configuración-del-administrador)
15. [Tareas Programadas (Cron Jobs)](#tareas-programadas-cron-jobs)
16. [Seguridad](#seguridad)
17. [Guía de Estilos y Frontend](#guía-de-estilos-y-frontend)
18. [Solución de Problemas](#solución-de-problemas)
19. [Extensibilidad](#extensibilidad)

---

## Introducción

El **Buzón de Quejas ITSCC** es una aplicación web desarrollada en PHP puro (sin frameworks) que permite la gestión de quejas, sugerencias y reconocimientos para el Instituto Tecnológico Superior de Ciudad Constitución.

### Stack Tecnológico

| Componente | Tecnología |
|------------|------------|
| **Backend** | PHP 7.4+ |
| **Base de Datos** | MySQL 5.7+ / MariaDB |
| **Frontend** | HTML5, CSS3 (TailwindCSS), JavaScript |
| **Librerías JS** | Alpine.js, Chart.js, AOS (Animate on Scroll) |
| **Email** | PHPMailer |
| **IA** | Google Gemini API |
| **Servidor** | Apache (Laragon recomendado para desarrollo) |

[Placeholder Imagen: Diagrama de arquitectura del sistema mostrando las capas Backend, Frontend, DB y APIs externas]

---

## Arquitectura del Sistema

### Patrón de Diseño

El sistema sigue un patrón **procedural con separación de responsabilidades**:

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND                                 │
│  (HTML + TailwindCSS + Alpine.js + JavaScript)                  │
├─────────────────────────────────────────────────────────────────┤
│                         PHP FILES                                │
│  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────────┐   │
│  │  Pages    │ │  Config   │ │ Services  │ │  Components   │   │
│  │ (*.php)   │ │ (config/) │ │(services/)│ │ (components/) │   │
│  └───────────┘ └───────────┘ └───────────┘ └───────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                         DATABASE                                 │
│                    (MySQL / MariaDB)                             │
└─────────────────────────────────────────────────────────────────┘
```

### Flujo de una Solicitud

1. El usuario accede a una página PHP.
2. El archivo PHP incluye `config.php` para inicializar la sesión y conexión a BD.
3. Se procesa la lógica de negocio (validaciones, consultas SQL).
4. Se incluye `components/header.php` para el layout.
5. Se renderiza el contenido HTML.
6. Se incluye `components/footer.php` para cerrar el layout.

---

## Requisitos del Sistema

### Software Necesario

- **PHP**: 7.4 o superior
- **MySQL**: 5.7+ o MariaDB 10.3+
- **Apache**: Con mod_rewrite habilitado
- **Extensiones PHP**:
  - `mysqli`
  - `curl` (para API de Gemini)
  - `openssl`
  - `mbstring`
  - `fileinfo`

### Configuración PHP Recomendada (php.ini)

```ini
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 10
max_execution_time = 120
memory_limit = 256M
```

---

## Instalación y Configuración

### Paso 1: Clonar el Repositorio

```bash
git clone https://github.com/tu-usuario/buzon.git
cd buzon
```

### Paso 2: Configurar el Archivo .env

Crea un archivo `.env` en la raíz del proyecto:

```env
# Base de Datos
DB_HOST=localhost
DB_USER=root
DB_PASS=tu_contraseña
DB_NAME=buzon_quejas

# API de Gemini (opcional)
GEMINI_API_KEY=tu_api_key_de_gemini
```

[Placeholder Imagen: Archivo .env de ejemplo con las variables configuradas]

### Paso 3: Crear la Base de Datos

Ejecuta el script SQL de creación (consulta la sección de Base de Datos).

### Paso 4: Configurar el Virtual Host (opcional)

Para Apache:

```apache
<VirtualHost *:80>
    ServerName buzon.local
    DocumentRoot "C:/laragon/www/buzon"
    <Directory "C:/laragon/www/buzon">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Paso 5: Verificar la Instalación

Accede a `http://localhost/buzon` o `http://buzon.local` y verifica que la página de inicio cargue correctamente.

---

## Estructura del Proyecto

```
buzon/
├── .env                          # Variables de entorno
├── config.php                    # Configuración principal y conexión DB
├── env_loader.php                # Cargador de variables de entorno
│
├── config/                       # Configuraciones específicas
│   ├── email_config.php          # Configuración SMTP
│   ├── email_antispam.php        # Reglas antispam
│   ├── gemini_config.php         # Configuración API Gemini
│   └── microsoft_auth.php        # OAuth Microsoft (opcional)
│
├── components/                   # Componentes reutilizables de UI
│   ├── header.php                # Header con navegación y meta tags
│   ├── footer.php                # Footer con scripts
│   └── navbar.php                # Barra de navegación
│
├── services/                     # Servicios/lógica de negocio
│   └── gemini_service.php        # Servicio para llamadas a Gemini API
│
├── PHPMailer/                    # Librería de envío de correos
│   └── ...
│
├── uploads/                      # Archivos subidos por usuarios
│   ├── complaints/               # Adjuntos de reportes (legacy)
│   └── comments/                 # Adjuntos de comentarios
│
├── assets/                       # Recursos estáticos
├── css/                          # Estilos CSS
├── js/                           # Scripts JavaScript
├── docs/                         # Documentación
│
├── index.php                     # Página de inicio
├── login.php                     # Inicio de sesión
├── register.php                  # Registro de usuarios
├── forgot_password.php           # Recuperación de contraseña
├── logout.php                    # Cierre de sesión
│
├── dashboard.php                 # Panel de control principal
├── submit_complaint.php          # Formulario de envío de reportes
├── view_complaint.php            # Vista detalle de un reporte
├── my_complaints.php             # Lista de reportes del usuario
├── profile.php                   # Perfil del usuario
│
├── statistics.php                # Panel de estadísticas (admin)
├── admin_settings.php            # Configuración del sistema (admin)
│
├── ajax_gemini_analyze.php       # Análisis individual con IA
├── ajax_gemini_bulk_analyze.php  # Análisis masivo con IA
├── ajax_apply_bulk_suggestions.php # Aplicar sugerencias masivas
│
├── send_email.php                # Funciones de envío de correo
├── send_verification_email.php   # Envío de códigos de verificación
├── process_email_queue.php       # Procesador de cola de correos
├── daily_summary.php             # Script de resumen diario (cron)
│
├── status_helper.php             # Funciones de gestión de estados
├── update_statuses.php           # Actualización automática de estados
│
├── about.php                     # Página "Acerca de"
├── callback_microsoft.php        # Callback OAuth Microsoft
└── login_microsoft.php           # Inicio OAuth Microsoft
```

[Placeholder Imagen: Diagrama de directorios del proyecto con descripción de cada carpeta]

---

## Base de Datos

### Esquema de Tablas

[Placeholder Imagen: Diagrama Entidad-Relación (ER) de la base de datos]

#### Tabla: `users`
Almacena la información de los usuarios del sistema.

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'manager', 'admin') DEFAULT 'student',
    profile_photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT | Identificador único |
| `name` | VARCHAR(255) | Nombre completo |
| `email` | VARCHAR(255) | Correo electrónico (único) |
| `password` | VARCHAR(255) | Contraseña hasheada (bcrypt) |
| `role` | ENUM | Rol: student, manager, admin |
| `profile_photo` | VARCHAR(255) | Ruta a foto de perfil |

---

#### Tabla: `categories`
Categorías de reportes.

```sql
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Categorías predeterminadas**:
- Queja
- Sugerencia
- Reconocimiento
- Denuncia
- Otro

---

#### Tabla: `departments`
Departamentos de la institución.

```sql
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    manager VARCHAR(255),
    is_hidden TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | VARCHAR(255) | Nombre del departamento |
| `email` | VARCHAR(255) | Correo del encargado |
| `manager` | VARCHAR(255) | Nombre del encargado |
| `is_hidden` | TINYINT | Si está oculto en asignaciones |

---

#### Tabla: `complaints`
Reportes enviados por los usuarios.

```sql
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    folio VARCHAR(10) UNIQUE,
    description TEXT NOT NULL,
    category_id INT,
    is_anonymous TINYINT(1) DEFAULT 0,
    status ENUM('unattended_ontime', 'unattended_late', 'attended_ontime', 'attended_late', 'invalid', 'duplicate') DEFAULT 'unattended_ontime',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attended_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `folio` | VARCHAR(10) | Número de folio único (ej: 000001) |
| `is_anonymous` | TINYINT | 1 si es anónimo |
| `status` | ENUM | Estado del reporte |
| `attended_at` | TIMESTAMP | Fecha de atención (cuando se cierra) |

---

#### Tabla: `complaint_departments`
Relación muchos a muchos entre reportes y departamentos.

```sql
CREATE TABLE complaint_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    department_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (complaint_id, department_id)
);
```

---

#### Tabla: `attachments`
Archivos adjuntos a reportes.

```sql
CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);
```

---

#### Tabla: `complaint_comments`
Comentarios/seguimiento de reportes.

```sql
CREATE TABLE complaint_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

#### Tabla: `comment_attachments`
Archivos adjuntos a comentarios.

```sql
CREATE TABLE comment_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES complaint_comments(id) ON DELETE CASCADE
);
```

---

#### Tabla: `email_verifications`
Códigos de verificación temporales.

```sql
CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    password_hash VARCHAR(255),
    verification_code VARCHAR(6) NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

#### Tabla: `email_queue`
Cola de correos pendientes de envío.

```sql
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    department_id INT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);
```

---

#### Tabla: `admin_settings`
Configuraciones del sistema.

```sql
CREATE TABLE admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);
```

**Claves de configuración**:

| setting_key | Descripción |
|-------------|-------------|
| `test_mode` | Modo de prueba de correos (1/0) |
| `test_email` | Correo de pruebas |
| `notify_buzon_on_new_report` | Notificar al buzón en nuevos reportes (1/0) |
| `restrict_dashboard_access` | Restringir acceso al dashboard (1/0) |
| `disable_institutional_email_check` | Deshabilitar verificación de correo institucional (1/0) |

---

## Autenticación y Autorización

### Archivo: `config.php`

Este archivo es el punto central de configuración:

```php
<?php
session_start();

// Cargar variables de entorno
require_once __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/.env');

// Constantes de base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'buzon_quejas');

// Conexión a base de datos
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Funciones de autenticación
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function canAccessDashboard() {
    // ... lógica de acceso al dashboard
}
?>
```

### Funciones de Autorización

| Función | Descripción | Uso |
|---------|-------------|-----|
| `isLoggedIn()` | Verifica si hay sesión activa | Proteger páginas |
| `isAdmin()` | Verifica si el usuario es admin | Acceso a configuración |
| `canAccessDashboard()` | Verifica acceso al dashboard | Según configuración de restricción |

### Variables de Sesión

```php
$_SESSION['user_id']   // ID del usuario
$_SESSION['name']      // Nombre del usuario
$_SESSION['email']     // Correo electrónico
$_SESSION['role']      // Rol: student, manager, admin
```

### Hash de Contraseñas

El sistema utiliza `password_hash()` con `PASSWORD_DEFAULT` (bcrypt):

```php
// Al registrar
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Al verificar
if (password_verify($password, $user['password'])) {
    // Contraseña correcta
}
```

---

## Sistema de Estados de Reportes

### Archivo: `status_helper.php`

Este archivo contiene toda la lógica de gestión de estados.

[Placeholder Imagen: Diagrama de flujo de estados de un reporte]

### Estados Disponibles

| Estado | Código | Descripción |
|--------|--------|-------------|
| Sin atender (a tiempo) | `unattended_ontime` | Dentro del plazo de 5 días hábiles |
| Sin atender (tarde) | `unattended_late` | Más de 5 días hábiles sin atención |
| Atendido (a tiempo) | `attended_ontime` | Cerrado dentro del plazo |
| Atendido (tarde) | `attended_late` | Cerrado después del plazo |
| Inválido | `invalid` | Reporte no válido |
| Duplicado | `duplicate` | Reporte ya existente |

### Funciones Principales

#### `calculateBusinessDays($start_date, $end_date)`

Calcula los días hábiles (lunes a viernes) entre dos fechas.

```php
$business_days = calculateBusinessDays(
    new DateTime('2024-01-01'),
    new DateTime('2024-01-08')
);
// Resultado: 5.0 días hábiles
```

#### `determineReportStatus($created_at, $attended_at)`

Determina el estado automáticamente:

```php
$status = determineReportStatus('2024-01-01 10:00:00', null);
// Si han pasado más de 5 días hábiles: 'unattended_late'
// Si no: 'unattended_ontime'

$status = determineReportStatus('2024-01-01 10:00:00', '2024-01-05 15:00:00');
// Si se atendió en ≤5 días: 'attended_ontime'
// Si no: 'attended_late'
```

#### `getStatusDisplayInfo($status)`

Obtiene información de visualización:

```php
$info = getStatusDisplayInfo('unattended_ontime');
// [
//     'text' => 'Sin atender',
//     'class' => 'bg-blue-100 text-blue-800 ring-blue-600/20',
//     'icon' => 'ph-clock-bold',
//     'color' => 'blue'
// ]
```

#### `updateAllPendingStatuses($conn)`

Actualiza todos los reportes pendientes (para cron):

```php
$updated_count = updateAllPendingStatuses($conn);
echo "Actualizados: $updated_count reportes";
```

---

## Gestión de Correos Electrónicos

### Configuración SMTP

Archivo: `config/email_config.php`

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'buzonitscc@gmail.com');
define('SMTP_PASSWORD', 'app_password_aqui');
define('SMTP_FROM_EMAIL', 'buzonitscc@gmail.com');
define('SMTP_FROM_NAME', 'Buzón de Quejas ITSCC');
```

[Placeholder Imagen: Configuración de Gmail para App Passwords]

### Modo de Prueba

```php
function isTestMode() {
    // Consulta admin_settings para 'test_mode'
    // Si está activo, todos los correos van al correo de pruebas
}

function getTestEmail() {
    // Retorna el correo de pruebas configurado
}

function getEmailRecipient($department_email) {
    if (isTestMode()) {
        return getTestEmail();
    }
    return $department_email;
}
```

### Cola de Correos

El sistema utiliza una cola asíncrona para enviar correos:

1. Cuando se asigna un departamento, se inserta en `email_queue`.
2. Se dispara `process_email_queue.php` de forma asíncrona.
3. El procesador envía los correos pendientes.

```php
// Insertar en cola
$stmt = $conn->prepare("INSERT INTO email_queue (complaint_id, department_id, status) VALUES (?, ?, 'pending')");

// Disparar procesamiento asíncrono
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/buzon/process_email_queue.php',
    CURLOPT_TIMEOUT_MS => 100,
]);
curl_exec($ch);
curl_close($ch);
```

### Envío de Notificaciones

Archivo: `send_email.php`

```php
function sendDepartmentNotification($department, $complaint_data) {
    $mail = new PHPMailer(true);
    
    // Configuración SMTP
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    
    // Configurar destinatario (con modo de prueba)
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress(getEmailRecipient($department['email']));
    
    // Contenido
    $mail->isHTML(true);
    $mail->Subject = 'Nuevo Reporte Asignado';
    $mail->Body = /* HTML del correo */;
    
    return $mail->send();
}
```

---

## Integración con Gemini AI

### Configuración

Archivo: `config/gemini_config.php`

```php
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-2.5-flash-lite');
}

if (!defined('GEMINI_API_BASE_URL')) {
    define('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta');
}

function isGeminiConfigured(): bool {
    return defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '';
}
```

### Servicio de Gemini

Archivo: `services/gemini_service.php`

[Placeholder Imagen: Diagrama de flujo de llamada a Gemini API]

```php
function callGeminiAPI($prompt, $system_instruction = '') {
    $url = GEMINI_API_BASE_URL . '/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
    
    $data = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]]
        ],
        'systemInstruction' => [
            'parts' => [['text' => $system_instruction]]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'topP' => 0.8,
            'topK' => 40,
            'maxOutputTokens' => 2048,
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
```

### Análisis Individual

Archivo: `ajax_gemini_analyze.php`

El sistema construye un prompt detallado con:
- Información del reporte (folio, fecha, descripción)
- Adjuntos y comentarios existentes
- Lista de categorías y departamentos disponibles
- Información detallada de cada departamento (funciones, tipos de quejas)
- Reportes existentes del año (para detectar duplicados)

**Respuesta esperada (JSON)**:

```json
{
  "categoria_id": 1,
  "categoria_nombre": "Queja",
  "es_invalido": false,
  "es_duplicado": false,
  "duplicado_de": null,
  "motivo_cierre": null,
  "departamentos": [
    {"id": 10, "nombre": "División de ISC", "confianza": "alta"}
  ],
  "resumen": "Resumen del análisis"
}
```

### Análisis Masivo

Archivo: `ajax_gemini_bulk_analyze.php`

Procesa múltiples reportes sin asignar en una sola llamada:

```php
// Obtener reportes sin asignar del período seleccionado
$query = "SELECT c.* FROM complaints c 
          LEFT JOIN complaint_departments cd ON c.id = cd.complaint_id 
          WHERE cd.department_id IS NULL 
          AND c.status IN ('unattended_ontime', 'unattended_late')
          AND c.created_at >= ?";

// Construir prompt con todos los reportes
$reports_context = "";
foreach ($complaints as $c) {
    $reports_context .= "ID: {$c['id']}\nFolio: {$c['folio']}\n...";
}

// Llamar a Gemini con instrucción para análisis masivo
```

---

## API Endpoints AJAX

### `ajax_gemini_analyze.php`

**Método**: POST  
**Parámetros**: `complaint_id`  
**Respuesta**: JSON con sugerencias de IA

### `ajax_gemini_bulk_analyze.php`

**Método**: POST  
**Parámetros**: `period` (today, this_week, this_month)  
**Respuesta**: JSON con array de análisis

### `ajax_apply_bulk_suggestions.php`

**Método**: POST  
**Parámetros**: `suggestions` (JSON array con datos a aplicar)  
**Respuesta**: JSON con resultados de aplicación

### `profile.php` (AJAX)

**Método**: POST (Content-Type: application/json)  
**Acciones**:
- `verify_password`: Verifica contraseña actual
- `change_password`: Cambia la contraseña

---

## Componentes Reutilizables

### `components/header.php`

Incluye:
- Meta tags y SEO
- TailwindCSS (CDN)
- Alpine.js
- Phosphor Icons
- Chart.js (para estadísticas)
- AOS (Animate on Scroll)
- Barra de navegación dinámica

```php
<?php
$page_title = 'Mi Página - ITSCC Buzón';
include 'components/header.php';
?>
```

[Placeholder Imagen: Estructura del header con todas las dependencias cargadas]

### `components/footer.php`

Incluye:
- Pie de página con información
- Scripts de inicialización
- AOS init
- Scroll to top button

### `components/navbar.php`

Menú de navegación adaptativo según:
- Estado de autenticación
- Rol del usuario
- Configuración del sistema

---

## Sistema de Archivos y Uploads

### Estructura de Directorios

```
uploads/
├── comments/          # Adjuntos de comentarios
│   └── [unique_id]_filename.ext
└── (complaints/)      # Legacy, nuevos van en attachments
```

### Tipos de Archivo Permitidos

```php
$allowed_types = [
    'image/jpeg', 
    'image/png', 
    'image/gif', 
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
```

### Proceso de Upload

```php
// Generar nombre único
$unique_name = uniqid() . '_' . basename($_FILES['file']['name']);
$target_path = 'uploads/comments/' . $unique_name;

// Mover archivo
if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
    // Guardar en base de datos
    $stmt = $conn->prepare("INSERT INTO attachments (...) VALUES (...)");
}
```

---

## Configuración del Administrador

### Archivo: `admin_settings.php`

Funcionalidades:
1. **Modo de prueba de correos**: Redirige todos los correos a una dirección de prueba.
2. **Notificación al buzón**: Notifica cuando se crea un nuevo reporte.
3. **Restricción del dashboard**: Controla quién puede ver el dashboard.
4. **Verificación de correo institucional**: Habilita/deshabilita la validación de dominio.
5. **Gestión de departamentos**: CRUD de departamentos.
6. **Visibilidad de departamentos**: Ocultar departamentos de las asignaciones.

[Placeholder Imagen: Panel de administración mostrando todas las opciones de configuración]

### Verificación de Cambios

Todos los cambios requieren la contraseña del administrador:

```php
if (password_verify($password, $admin['password'])) {
    // Aplicar cambios
} else {
    $_SESSION['error_message'] = 'Contraseña incorrecta.';
}
```

---

## Tareas Programadas (Cron Jobs)

### Resumen Diario

Archivo: `daily_summary.php`

Ejecutar diariamente a las 7:00 AM:

```bash
0 7 * * * php /ruta/al/proyecto/daily_summary.php
```

Este script:
1. Consulta reportes pendientes y sin asignar.
2. Genera un correo HTML con resumen.
3. Envía al encargado del buzón.

[Placeholder Imagen: Ejemplo de correo de resumen diario]

### Actualización de Estados

Archivo: `update_statuses.php`

Ejecutar cada hora:

```bash
0 * * * * php /ruta/al/proyecto/update_statuses.php
```

Actualiza automáticamente los estados de reportes:
- `unattended_ontime` → `unattended_late` (si pasan 5 días hábiles)

### Procesador de Cola de Correos

Archivo: `process_email_queue.php`

Ejecutar cada 5 minutos:

```bash
*/5 * * * * php /ruta/al/proyecto/process_email_queue.php
```

Procesa correos pendientes en la cola y actualiza su estado.

---

## Seguridad

### Protección contra SQL Injection

El sistema utiliza **prepared statements** en todas las consultas:

```php
// ✅ Correcto
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// ❌ Incorrecto (vulnerable)
$result = $conn->query("SELECT * FROM users WHERE email = '$email'");
```

### Protección XSS

Uso de `htmlspecialchars()` en toda salida:

```php
<?php echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8'); ?>
```

### Protección CSRF

Las acciones importantes requieren verificación de sesión activa:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    // Procesar acción
}
```

### Validación de Uploads

```php
// Verificar tipo MIME real
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['file']['tmp_name']);

if (!in_array($mime, $allowed_types)) {
    throw new Exception('Tipo de archivo no permitido');
}
```

### Roles y Permisos

| Acción | Student | Manager | Admin |
|--------|---------|---------|-------|
| Ver sus reportes | ✅ | ✅ | ✅ |
| Enviar reportes | ✅ | ✅ | ✅ |
| Ver dashboard | ❌* | ✅ | ✅ |
| Asignar departamentos | ❌ | ❌ | ✅ |
| Cerrar reportes | ❌ | ✅** | ✅ |
| Configuración | ❌ | ❌ | ✅ |
| Estadísticas | ❌ | ❌ | ✅ |

*Depende de configuración  
**Solo reportes asignados a su departamento

---

## Guía de Estilos y Frontend

### TailwindCSS

El sistema utiliza TailwindCSS vía CDN:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

### Alpine.js

Para interactividad sin escribir mucho JavaScript:

```html
<div x-data="{ isOpen: false }">
    <button @click="isOpen = !isOpen">Toggle</button>
    <div x-show="isOpen">Contenido</div>
</div>
```

### Iconos (Phosphor Icons)

```html
<i class="ph-check-circle"></i>
<i class="ph-warning text-red-500"></i>
```

### Paleta de Colores

| Color | Uso | Clase |
|-------|-----|-------|
| Azul | Primario, acciones | `bg-blue-600`, `text-blue-600` |
| Verde | Éxito, atendido | `bg-green-600`, `text-green-600` |
| Rojo | Error, tardío | `bg-red-600`, `text-red-600` |
| Naranja | Advertencia | `bg-orange-600`, `text-orange-600` |
| Púrpura | Duplicados | `bg-purple-600`, `text-purple-600` |

### Animaciones (AOS)

```html
<div data-aos="fade-up" data-aos-duration="1000">
    Contenido animado
</div>
```

---

## Solución de Problemas

### Error de Conexión a Base de Datos

**Síntoma**: "Connection failed: ..."

**Solución**:
1. Verificar credenciales en `.env`
2. Verificar que MySQL esté corriendo
3. Verificar que la base de datos exista

### Correos No Se Envían

**Síntoma**: Los correos quedan en estado "pending"

**Solución**:
1. Verificar configuración SMTP en `config/email_config.php`
2. Para Gmail, usar App Password (no contraseña normal)
3. Verificar que cURL esté habilitado
4. Revisar `email_queue.error_message` para detalles

[Placeholder Imagen: Configuración de App Password en Google]

### Gemini No Funciona

**Síntoma**: "Configura GEMINI_API_KEY..."

**Solución**:
1. Obtener API Key de Google AI Studio
2. Agregar a `.env`: `GEMINI_API_KEY=tu_api_key`
3. Reiniciar el servidor web

### Archivos No Se Suben

**Síntoma**: Error al subir archivos

**Solución**:
1. Verificar permisos de carpeta `uploads/` (755 o 777)
2. Verificar `upload_max_filesize` en php.ini
3. Verificar `post_max_size` en php.ini

### Sesión Se Pierde

**Síntoma**: Usuario se desloguea inesperadamente

**Solución**:
1. Verificar configuración de sesiones en php.ini
2. Verificar que `session_start()` está al inicio de `config.php`
3. Verificar que no hay output antes de `session_start()`

---

## Extensibilidad

### Agregar Nueva Categoría

```sql
INSERT INTO categories (name, description) 
VALUES ('Nueva Categoría', 'Descripción de la categoría');
```

### Agregar Nuevo Departamento

Desde el panel de administración o:

```sql
INSERT INTO departments (name, email, manager) 
VALUES ('Nuevo Departamento', 'correo@ejemplo.com', 'Nombre del Encargado');
```

### Agregar Nuevo Estado

1. Modificar ENUM en tabla `complaints`:
```sql
ALTER TABLE complaints MODIFY status ENUM('unattended_ontime', 'unattended_late', 'attended_ontime', 'attended_late', 'invalid', 'duplicate', 'nuevo_estado');
```

2. Agregar entrada en `getStatusDisplayInfo()` en `status_helper.php`:
```php
'nuevo_estado' => [
    'text' => 'Nuevo Estado',
    'class' => 'bg-cyan-100 text-cyan-800 ring-cyan-600/20',
    'icon' => 'ph-icon-name',
    'color' => 'cyan'
],
```

### Agregar Nuevo Rol

1. Modificar ENUM en tabla `users`:
```sql
ALTER TABLE users MODIFY role ENUM('student', 'manager', 'admin', 'nuevo_rol');
```

2. Agregar lógica de autorización correspondiente en los archivos PHP.

### Agregar Nueva Configuración

1. Insertar en `admin_settings`:
```sql
INSERT INTO admin_settings (setting_key, setting_value) 
VALUES ('nueva_config', 'valor_default');
```

2. Agregar UI en `admin_settings.php`
3. Agregar lógica en el archivo correspondiente

---

## Documentación Adicional

### Archivos README Existentes

- `EMAIL_QUEUE_SETUP.md`: Configuración de la cola de correos
- `README_DEPARTAMENTOS.md`: Información sobre departamentos
- `RESPONSE_EVIDENCE_README.md`: Sistema de evidencias de respuesta
- `STATUS_SYSTEM_README.md`: Documentación del sistema de estados

### Recursos Externos

- [TailwindCSS Documentation](https://tailwindcss.com/docs)
- [Alpine.js Documentation](https://alpinejs.dev/start-here)
- [PHPMailer Documentation](https://github.com/PHPMailer/PHPMailer)
- [Google Gemini API](https://ai.google.dev/tutorials/get_started_web)
- [Phosphor Icons](https://phosphoricons.com/)

---

**Versión del Manual**: 1.0  
**Última actualización**: Diciembre 2024  
**Sistema**: Buzón de Quejas ITSCC
