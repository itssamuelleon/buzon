# Sistema de Evidencia de Respuesta

## Descripción

Este sistema permite a los administradores adjuntar archivos de evidencia como respuesta a los reportes. Al subir evidencia, el reporte se marca automáticamente como atendido.

## Características

### Para Administradores

1. **Subir Evidencia de Respuesta**
   - Formulario de carga de archivos múltiples
   - Formatos soportados: imágenes, PDF, Word, Excel
   - Al subir archivos, el reporte se marca automáticamente como atendido
   - El sistema calcula si fue atendido a tiempo o a destiempo

2. **Visualización**
   - Vista previa de imágenes
   - Iconos para otros tipos de archivos
   - Información de quién subió cada archivo y cuándo

### Para Usuarios

1. **Ver Evidencia de Respuesta**
   - Los usuarios pueden ver toda la evidencia de respuesta adjuntada por los administradores
   - Pueden descargar los archivos
   - Si no hay evidencia, se muestra un mensaje informativo

## Instalación

### Paso 1: Ejecutar el script SQL

Ejecuta el archivo `add_response_evidence.sql` en tu base de datos:

```bash
mysql -u root -p buzon_quejas < add_response_evidence.sql
```

O desde phpMyAdmin:
1. Abre phpMyAdmin
2. Selecciona la base de datos `buzon_quejas`
3. Ve a la pestaña "SQL"
4. Copia y pega el contenido de `add_response_evidence.sql`
5. Haz clic en "Continuar"

### Paso 2: Crear directorio de uploads

El sistema creará automáticamente el directorio `uploads/response_evidence/` cuando se suba el primer archivo. Sin embargo, puedes crearlo manualmente:

```bash
mkdir -p uploads/response_evidence
chmod 777 uploads/response_evidence
```

### Paso 3: Verificar permisos

Asegúrate de que el servidor web tenga permisos de escritura en el directorio `uploads/`:

```bash
chmod -R 777 uploads/
```

## Estructura de la Base de Datos

### Tabla: `response_evidence`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT | ID único del archivo |
| `complaint_id` | INT | ID del reporte asociado |
| `file_name` | VARCHAR(255) | Nombre original del archivo |
| `file_path` | VARCHAR(500) | Ruta del archivo en el servidor |
| `file_type` | VARCHAR(100) | Tipo MIME del archivo |
| `file_size` | INT | Tamaño del archivo en bytes |
| `uploaded_by` | INT | ID del usuario que subió el archivo |
| `uploaded_at` | TIMESTAMP | Fecha y hora de subida |

## Funcionamiento

### Flujo de Trabajo

1. **Administrador sube evidencia**
   - El administrador accede a la página de detalle del reporte
   - En la sección "Evidencia de Respuesta" (solo visible para admins)
   - Selecciona uno o más archivos
   - Hace clic en "Subir Evidencia"

2. **Sistema procesa la subida**
   - Los archivos se guardan en `uploads/response_evidence/`
   - Se genera un nombre único para cada archivo
   - Se registran en la base de datos
   - Se marca el reporte como atendido automáticamente
   - Se calcula si fue atendido a tiempo o a destiempo

3. **Usuario ve la respuesta**
   - El usuario accede a su reporte
   - Ve la sección "Evidencia de Respuesta"
   - Puede ver y descargar todos los archivos adjuntados
   - Ve quién subió cada archivo y cuándo

## Archivos Modificados

### `view_complaint.php`

Se agregaron las siguientes funcionalidades:

1. **Manejo de subida de archivos** (líneas 17-61)
   - Procesa archivos múltiples
   - Genera nombres únicos
   - Guarda en base de datos
   - Marca reporte como atendido

2. **Consulta de evidencia de respuesta** (líneas 127-131)
   - Obtiene todos los archivos de evidencia
   - Incluye información del usuario que subió

3. **Interfaz de usuario** (líneas 480-563)
   - Formulario de subida (solo admins)
   - Visualización de archivos
   - Mensajes de éxito/error

## Formatos de Archivo Soportados

- **Imágenes**: JPG, PNG, GIF, BMP, WebP, SVG
- **Documentos**: PDF, DOC, DOCX
- **Hojas de cálculo**: XLS, XLSX

## Seguridad

1. **Validación de permisos**
   - Solo administradores pueden subir evidencia
   - Los usuarios solo pueden ver evidencia de sus propios reportes

2. **Nombres de archivo únicos**
   - Se generan nombres únicos usando `uniqid()` y timestamp
   - Previene colisiones y sobrescritura de archivos

3. **Validación de tipos de archivo**
   - El formulario acepta solo tipos específicos
   - Validación adicional en el servidor (recomendado agregar)

## Mejoras Futuras Sugeridas

1. **Validación adicional de archivos**
   - Verificar tamaño máximo de archivo
   - Validar tipo MIME en el servidor
   - Escanear archivos por virus

2. **Notificaciones**
   - Enviar email al usuario cuando se sube evidencia
   - Notificar en el dashboard

3. **Gestión de archivos**
   - Permitir eliminar archivos de evidencia
   - Agregar comentarios a cada archivo
   - Historial de cambios

4. **Optimización**
   - Comprimir imágenes automáticamente
   - Generar miniaturas para vista previa
   - Limitar tamaño total de archivos por reporte

## Soporte

Para cualquier problema o pregunta sobre el sistema, revisa el código en `view_complaint.php` o contacta al desarrollador del sistema.
