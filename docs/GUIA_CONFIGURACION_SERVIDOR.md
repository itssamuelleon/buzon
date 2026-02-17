# 🖥️ Guía de Configuración del Servidor Ubuntu
## Proyecto: Buzón de Quejas ITSCC
## Dominio: https://cdconstitucion.tecnm.mx/buzon/

> **Esta guía es para el administrador del servidor.**  
> Contiene todo lo que se necesita configurar para que la aplicación funcione correctamente,  
> incluyendo el login con cuentas institucionales de Microsoft.
>
> ⚠️ **Nota:** El servidor ya tiene HTTPS configurado y la página institucional en  
> `/somoshalcones/`. Esta guía **NO modifica** esa configuración existente.  
> Solo se agrega la carpeta `/buzon/` al mismo servidor.

---

## 📋 Resumen Rápido

| # | Tarea | Prioridad | Tiempo estimado |
|---|-------|-----------|-----------------|
| 1 | Instalar extensiones PHP | 🔴 Crítico | 5 min |
| 2 | Configurar PHP (php.ini) | 🔴 Crítico | 10 min |
| 3 | Configurar sesiones PHP | 🔴 Crítico | 5 min |
| 4 | Certificados SSL (cURL) | 🔴 Crítico | 5 min |
| 5 | Subir archivos del proyecto | 🔴 Crítico | 5 min |
| 6 | Permisos de directorios | 🔴 Crítico | 5 min |
| 7 | Proteger archivo .env | 🔴 Crítico | 5 min |
| 8 | Configurar base de datos | 🔴 Crítico | 10 min |
| 9 | Verificación final | 🟢 Final | 10 min |

---

## 1. 🔴 Instalar extensiones PHP requeridas

```bash
# Actualizar repositorios
sudo apt update

# Instalar PHP y extensiones necesarias (ajustar versión de PHP si es diferente)
sudo apt install -y php php-curl php-mbstring php-json php-mysqli php-xml php-gd php-zip

# Verificar que cURL está habilitado
php -m | grep curl
# Debe mostrar: curl

# Verificar que MySQLi está habilitado
php -m | grep mysqli
# Debe mostrar: mysqli

# Reiniciar Apache después de instalar
sudo systemctl restart apache2
```

### ¿Por qué es necesario?
- **php-curl**: La app hace llamadas HTTPS a Microsoft (login OAuth) y a Google (API de Gemini).  
  Sin esto, el login con cuenta institucional **NO funcionará**.
- **php-mysqli**: Conexión a la base de datos MySQL/MariaDB.
- **php-mbstring**: Manejo de caracteres especiales y codificación UTF-8.
- **php-gd**: Procesamiento de imágenes (fotos de perfil).

---

## 2. 🔴 Configurar PHP (php.ini)

Localizar el archivo php.ini que usa Apache:
```bash
php --ini | grep "Loaded Configuration File"
# Normalmente: /etc/php/8.x/apache2/php.ini
```

Editar con nano o vim:
```bash
sudo nano /etc/php/8.x/apache2/php.ini
```

### Valores a revisar/modificar:

```ini
; ===== UPLOADS =====
; La app permite que los usuarios suban archivos de hasta 5MB
file_uploads = On
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 10

; ===== SESIONES (CRÍTICO para login Microsoft) =====
session.cookie_secure = On
session.cookie_httponly = On
session.cookie_samesite = Lax
session.use_strict_mode = 1
session.gc_maxlifetime = 3600

; ===== SEGURIDAD =====
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; ===== TIEMPO DE EJECUCIÓN =====
max_execution_time = 60
memory_limit = 256M
```

**Reiniciar Apache después de los cambios:**
```bash
sudo systemctl restart apache2
```

---

## 3. 🔴 Configurar sesiones PHP

Las sesiones PHP son **CRÍTICAS** para el login con Microsoft. El flujo OAuth guarda un token
de seguridad (state) en la sesión durante `login_microsoft.php` y lo verifica en 
`callback_microsoft.php`. Si las sesiones no persisten, los usuarios **no podrán iniciar sesión**.

```bash
# Verificar que el directorio de sesiones existe
ls -la /var/lib/php/sessions/

# Si no existe, crearlo
sudo mkdir -p /var/lib/php/sessions

# Establecer permisos correctos
sudo chown www-data:www-data /var/lib/php/sessions
sudo chmod 700 /var/lib/php/sessions

# Verificar la configuración de sesiones
php -i | grep session.save_path
# Debe mostrar: /var/lib/php/sessions
```

### Prueba rápida de sesiones
Crear un archivo temporal para probar:
```bash
cat > /tmp/test_session.php << 'EOF'
<?php
session_start();
if (isset($_SESSION['test'])) {
    echo "Sesión funciona correctamente. Valor: " . $_SESSION['test'];
    echo "\nSession ID: " . session_id();
} else {
    $_SESSION['test'] = 'OK - ' . date('Y-m-d H:i:s');
    echo "Sesión iniciada. Recarga esta página para verificar.";
    echo "\nSession ID: " . session_id();
}
?>
EOF
```
Copiar a la carpeta web, visitar en el navegador, recargar, y verificar que muestra el valor guardado.
**Eliminar este archivo de prueba cuando termine.**

---

## 4. 🔴 Certificados SSL para cURL

La aplicación hace llamadas HTTPS **salientes** desde el servidor hacia:
- `login.microsoftonline.com` (autenticación Microsoft)
- `graph.microsoft.com` (perfil de usuario Microsoft)
- `generativelanguage.googleapis.com` (API de Google Gemini)

Aunque el servidor ya tiene HTTPS para el sitio web, PHP necesita certificados CA 
para poder verificar las conexiones salientes que hace con cURL.

```bash
# Instalar certificados CA
sudo apt install -y ca-certificates

# Verificar que PHP encuentra los certificados
php -r "print_r(openssl_get_cert_locations());"
# Debe mostrar default_cert_file apuntando a un archivo que existe

# Verificar que el bundle existe
ls -la /etc/ssl/certs/ca-certificates.crt
# Este archivo debe existir

# Prueba rápida: verificar conexión SSL a Microsoft
php -r "
\$ch = curl_init('https://login.microsoftonline.com/common/v2.0/.well-known/openid-configuration');
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\$ch, CURLOPT_SSL_VERIFYPEER, true);
\$result = curl_exec(\$ch);
if (curl_errno(\$ch)) {
    echo 'ERROR: ' . curl_error(\$ch) . PHP_EOL;
} else {
    echo 'CONEXIÓN SSL OK' . PHP_EOL;
}
curl_close(\$ch);
"
```

### Si la prueba falla con error SSL:
```bash
# Actualizar los certificados CA
sudo update-ca-certificates

# Si aún falla, verificar la ruta en php.ini
sudo nano /etc/php/8.x/apache2/php.ini
# Buscar y configurar:
# curl.cainfo = /etc/ssl/certs/ca-certificates.crt
# openssl.cafile = /etc/ssl/certs/ca-certificates.crt

sudo systemctl restart apache2
```

---

## 5. 🔴 Subir archivos del proyecto

La carpeta `/buzon/` va al mismo nivel que `/somoshalcones/` en el servidor.  
**No se modifica nada de la página institucional existente.**

```bash
# Primero, identificar dónde está el DocumentRoot actual
# Si /somoshalcones/ está en /var/www/html/somoshalcones/
# entonces /buzon/ irá en /var/www/html/buzon/

# Verificar la ruta actual
ls -la /var/www/html/
# Deberías ver la carpeta somoshalcones/ aquí

# Crear directorio para buzon
sudo mkdir -p /var/www/html/buzon

# Subir archivos vía SCP, SFTP, o Git
# Opción con SCP desde la máquina del desarrollador:
# scp -r /ruta/local/buzon/* usuario@servidor:/var/www/html/buzon/

# Verificar que los archivos se subieron correctamente
ls -la /var/www/html/buzon/
# Deben aparecer: login.php, callback_microsoft.php, config.php, .env, etc.
```

La estructura en el servidor debe quedar así:
```
/var/www/html/
├── somoshalcones/     ← Página institucional (NO TOCAR)
│   └── index.html
├── buzon/             ← Buzón de Quejas (NUEVO)
│   ├── .env
│   ├── login.php
│   ├── callback_microsoft.php
│   ├── config.php
│   ├── uploads/
│   └── ... (demás archivos)
└── index.html         ← Probable redirect a /somoshalcones/ (NO TOCAR)
```

> ℹ️ **Nota:** Si el DocumentRoot no es `/var/www/html/`, ajustar la ruta.  
> Se puede verificar con: `grep -r "DocumentRoot" /etc/apache2/sites-enabled/`

---

## 6. 🔴 Permisos de directorios

```bash
# Ir al directorio del proyecto
cd /var/www/html/buzon

# Crear directorios de uploads si no existen
sudo mkdir -p uploads
sudo mkdir -p uploads/response_evidence

# Permisos para el directorio de uploads (donde usuarios suben archivos)
sudo chown -R www-data:www-data uploads/
sudo chmod -R 755 uploads/

# Permisos para el archivo .env (solo lectura para el servidor web)
sudo chown www-data:www-data .env
sudo chmod 640 .env

# Permisos generales del proyecto
sudo chown -R www-data:www-data /var/www/html/buzon
sudo find /var/www/html/buzon -type f -exec chmod 644 {} \;
sudo find /var/www/html/buzon -type d -exec chmod 755 {} \;
```

---

## 7. 🔴 Proteger archivo .env

El archivo `.env` contiene credenciales sensibles (contraseña de BD, Client Secret de Microsoft).
**NUNCA debe ser accesible desde el navegador.**

### Opción A: Crear .htaccess dentro de /buzon/
```bash
cat > /var/www/html/buzon/.htaccess << 'EOF'
# Proteger archivos sensibles
<FilesMatch "^\.env$">
    Require all denied
</FilesMatch>
EOF

sudo chown www-data:www-data /var/www/html/buzon/.htaccess
```

> ⚠️ **Importante:** Para que `.htaccess` funcione, el VirtualHost de Apache debe tener  
> `AllowOverride All` para el directorio `/var/www/html/buzon`.  
> Si no funciona el `.htaccess`, verificar en la configuración de Apache:
> ```bash
> grep -A 5 "Directory" /etc/apache2/sites-enabled/*.conf
> ```
> Y asegurarse de que diga `AllowOverride All` y no `AllowOverride None`.

### Opción B: Agregar directamente en la configuración de Apache
Si prefieres no usar `.htaccess`, se puede agregar directamente en el archivo de 
configuración del sitio actual **sin modificar nada existente**:

```bash
sudo nano /etc/apache2/sites-enabled/000-default-le-ssl.conf
# (o como se llame el conf del sitio con SSL)
```

Agregar **antes del cierre `</VirtualHost>`**:
```apache
    # === Buzón de Quejas - Protección de archivos sensibles ===
    <Directory /var/www/html/buzon>
        AllowOverride All
        <FilesMatch "^\.env$">
            Require all denied
        </FilesMatch>
    </Directory>
```

```bash
sudo apache2ctl configtest
sudo systemctl restart apache2
```

### Verificar que .env está protegido:
```bash
curl -s -o /dev/null -w "%{http_code}" https://cdconstitucion.tecnm.mx/buzon/.env
# Debe mostrar 403 o 404, NUNCA 200
```

---

## 8. 🔴 Base de datos MySQL/MariaDB

```bash
# Verificar si MySQL/MariaDB ya está instalado
mysql --version
# Si no está instalado:
# sudo apt install -y mariadb-server
# sudo mysql_secure_installation

# Crear base de datos y usuario
sudo mysql -u root -p
```

```sql
-- Crear base de datos
CREATE DATABASE buzon_quejas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crear usuario para la aplicación (cambiar contraseña)
CREATE USER 'buzon_user'@'localhost' IDENTIFIED BY 'CONTRASEÑA_SEGURA_AQUI';
GRANT ALL PRIVILEGES ON buzon_quejas.* TO 'buzon_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Importar estructura de la base de datos
Si se proporciona un archivo SQL de respaldo:
```bash
mysql -u buzon_user -p buzon_quejas < /ruta/al/backup.sql
```

### Actualizar .env con credenciales de producción
Editar el archivo `.env` en el directorio del proyecto:
```bash
sudo nano /var/www/html/buzon/.env
```

Cambiar las líneas de base de datos:
```env
DB_HOST=localhost
DB_USER=buzon_user
DB_PASS=CONTRASEÑA_SEGURA_AQUI
DB_NAME=buzon_quejas
```

⚠️ **NO cambiar las líneas de MS_CLIENT_ID, MS_CLIENT_SECRET, MS_REDIRECT_URI ni MS_TENANT_ID**
a menos que el desarrollador lo indique.

---

## 9. 🟢 Verificación final

Ejecutar estas pruebas para confirmar que todo funciona:

```bash
echo "=== 1. Verificar PHP ==="
php -v

echo ""
echo "=== 2. Verificar extensiones PHP ==="
php -m | grep -E "(curl|mysqli|mbstring|json|gd)"

echo ""
echo "=== 3. Verificar sesiones ==="
php -i | grep session.save_path

echo ""
echo "=== 4. Verificar SSL (conexiones salientes desde PHP) ==="
php -r "
\$ch = curl_init('https://login.microsoftonline.com/common/v2.0/.well-known/openid-configuration');
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\$ch, CURLOPT_SSL_VERIFYPEER, true);
\$result = curl_exec(\$ch);
echo curl_errno(\$ch) ? 'FALLA: ' . curl_error(\$ch) : 'OK';
curl_close(\$ch);
echo PHP_EOL;
"

echo ""
echo "=== 5. Verificar archivos del proyecto ==="
ls -la /var/www/html/buzon/login.php
ls -la /var/www/html/buzon/.env

echo ""
echo "=== 6. Verificar permisos de uploads ==="
ls -la /var/www/html/buzon/uploads/

echo ""
echo "=== 7. Verificar .env protegido ==="
curl -s -o /dev/null -w "%{http_code}" https://cdconstitucion.tecnm.mx/buzon/.env
echo " (debe ser 403 o 404, NO 200)"

echo ""
echo "=== 8. Verificar que la app carga ==="
curl -s -o /dev/null -w "%{http_code}" https://cdconstitucion.tecnm.mx/buzon/login.php
echo " (debe ser 200)"

echo ""
echo "=== 9. Verificar base de datos ==="
php -r "
\$conn = new mysqli('localhost', 'buzon_user', 'CONTRASEÑA_AQUI', 'buzon_quejas');
echo \$conn->connect_error ? 'FALLA: ' . \$conn->connect_error : 'OK';
echo PHP_EOL;
"

echo ""
echo "=== 10. Verificar que la pagina institucional sigue OK ==="
curl -s -o /dev/null -w "%{http_code}" https://cdconstitucion.tecnm.mx/somoshalcones/index.html
echo " (debe seguir siendo 200)"
```

---

## 🔧 Troubleshooting

### El login de Microsoft da "Error de seguridad: Estado inválido"
- **Causa**: Las sesiones PHP no persisten entre `login_microsoft.php` y `callback_microsoft.php`
- **Solución**: Verificar paso 3 (sesiones) y que `session.cookie_secure = On` esté configurado en php.ini

### Error "SSL certificate problem" en cURL
- **Causa**: Certificados CA no instalados o no encontrados por PHP
- **Solución**: Verificar paso 4 (certificados SSL para cURL)

### Error "Error de conexión al obtener token"
- **Causa**: El servidor no puede comunicarse con `login.microsoftonline.com`
- **Solución**: 
  - Verificar que el firewall permite conexiones HTTPS **salientes** (puerto 443)
  - Probar con: `curl -v https://login.microsoftonline.com` desde la terminal del servidor

### Los archivos no se suben
- **Causa**: Permisos o configuración de PHP
- **Solución**: Verificar paso 6 (permisos) y `upload_max_filesize` en php.ini

### Página en blanco o error 500
- **Causa**: Error PHP no visible
- **Solución**: 
  ```bash
  sudo tail -50 /var/log/apache2/error.log
  sudo tail -50 /var/log/php_errors.log
  ```

### La página institucional dejó de funcionar
- **Causa**: Esto NO debería pasar ya que no se modifica nada de `/somoshalcones/`
- **Solución**: Verificar que no se haya modificado el VirtualHost principal.
  Revertir cualquier cambio al conf de Apache si es necesario.

---

## 📌 Puertos y conexiones que el servidor necesita

El servidor ya tiene HTTPS configurado correctamente. Solo verificar que permite  
conexiones **salientes** al puerto 443 (para que PHP pueda comunicarse con Microsoft y Google):

| Destino | Puerto | Dirección | Motivo |
|---------|--------|-----------|--------|
| `login.microsoftonline.com` | 443 | Saliente | Login Microsoft |
| `graph.microsoft.com` | 443 | Saliente | Perfil usuario Microsoft |
| `generativelanguage.googleapis.com` | 443 | Saliente | API Gemini |
| localhost | 3306 | Local | MySQL/MariaDB |

> ℹ️ El frontend también carga recursos desde CDNs (`cdn.tailwindcss.com`,  
> `cdn.jsdelivr.net`, `unpkg.com`, `fonts.googleapis.com`), pero estos los  
> carga el navegador del usuario, no el servidor. Solo importa que el servidor  
> permita las conexiones salientes listadas arriba.

### Verificar conexiones salientes:
```bash
curl -s -o /dev/null -w "%{http_code}" https://login.microsoftonline.com
echo " (debe ser 200 - Microsoft)"

curl -s -o /dev/null -w "%{http_code}" https://graph.microsoft.com
echo " (debe ser 401 - Graph, sin token pero conecta)"

curl -s -o /dev/null -w "%{http_code}" https://generativelanguage.googleapis.com
echo " (debe conectar - Gemini API)"
```

---

*Documento generado el 2026-02-16. Contactar al desarrollador si hay dudas.*
