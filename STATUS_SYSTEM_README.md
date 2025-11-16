# Sistema Automático de Actualización de Estados de Reportes

## Descripción

Este sistema actualiza automáticamente el estado de los reportes basándose en días hábiles (lunes a viernes). Los reportes tienen un periodo de 5 días hábiles para ser atendidos.

## Estados del Sistema

El sistema maneja 4 estados diferentes:

1. **Sin atender** (azul) - `unattended_ontime`
   - El reporte aún no ha sido atendido
   - Está dentro del periodo de 5 días hábiles
   
2. **Sin atender** (rojo) - `unattended_late`
   - El reporte aún no ha sido atendido
   - Ya pasaron los 5 días hábiles
   
3. **Atendido** (verde) - `attended_ontime`
   - El reporte fue atendido dentro de los 5 días hábiles
   
4. **Atendido a destiempo** (naranja) - `attended_late`
   - El reporte fue atendido después de los 5 días hábiles

## Instalación

### Paso 1: Ejecutar el script SQL

Ejecuta el archivo `update_status_system.sql` en tu base de datos MySQL:

```bash
mysql -u root -p buzon_quejas < update_status_system.sql
```

O desde phpMyAdmin:
1. Abre phpMyAdmin
2. Selecciona la base de datos `buzon_quejas`
3. Ve a la pestaña "SQL"
4. Copia y pega el contenido de `update_status_system.sql`
5. Haz clic en "Continuar"

### Paso 2: Verificar los archivos

Asegúrate de que los siguientes archivos estén en su lugar:

- `status_helper.php` - Funciones auxiliares para cálculo de días hábiles
- `update_statuses.php` - Script de actualización automática
- `update_status_system.sql` - Script SQL para modificar la base de datos

### Paso 3: Listo!

El sistema está configurado para actualizarse automáticamente en cada carga de página. No se requiere configuración adicional.

## Funcionamiento

### Actualización Automática

El sistema se actualiza automáticamente cada vez que se carga una página del sitio. El script `update_statuses.php` es llamado desde `components/header.php`.

**Eficiencia**: Solo se revisan y actualizan los reportes que están "sin atender". Los reportes que ya fueron atendidos no se vuelven a revisar, haciendo el sistema muy eficiente.

### Asignación de Departamentos

Cuando un administrador asigna uno o más departamentos a un reporte, el sistema automáticamente:

1. Marca el reporte como "atendido"
2. Registra la fecha y hora de atención
3. Calcula si fue atendido a tiempo o a destiempo
4. Actualiza el estado correspondiente

## Archivos Modificados

Los siguientes archivos fueron modificados para integrar el sistema:

1. **components/header.php**
   - Incluye el script de actualización automática

2. **view_complaint.php**
   - Marca reportes como atendidos al asignar departamentos
   - Usa las nuevas funciones de visualización de estados

3. **dashboard.php**
   - Muestra los nuevos estados con colores apropiados
   - Filtros actualizados para los 4 estados

4. **my_complaints.php**
   - Muestra los nuevos estados con colores apropiados

## Funciones Principales

### `calculateBusinessDays($start_date, $end_date)`
Calcula el número de días hábiles entre dos fechas (solo lunes a viernes).

### `determineReportStatus($created_at, $attended_at)`
Determina el estado correcto de un reporte basado en sus fechas.

### `getStatusDisplayInfo($status)`
Obtiene información de visualización (texto, clase CSS, icono) para un estado.

### `updateAllPendingStatuses($conn)`
Actualiza todos los reportes pendientes que necesitan cambio de estado.

### `markComplaintAsAttended($conn, $complaint_id)`
Marca un reporte como atendido y calcula su estado final.

## Días Hábiles

El sistema considera días hábiles únicamente de **lunes a viernes**. Los fines de semana (sábado y domingo) no se cuentan en el cálculo de los 5 días.

### Ejemplo:
- Reporte creado: Lunes 10:00 AM
- Día 1: Lunes
- Día 2: Martes
- Día 3: Miércoles
- Día 4: Jueves
- Día 5: Viernes
- Límite: Viernes 11:59 PM

Si se atiende el sábado o después, se considera "a destiempo".

## Colores del Sistema

- 🔵 **Azul**: Sin atender (a tiempo)
- 🔴 **Rojo**: Sin atender (tarde)
- 🟢 **Verde**: Atendido (a tiempo)
- 🟠 **Naranja**: Atendido a destiempo

## Mantenimiento

El sistema no requiere mantenimiento especial. La actualización es automática y eficiente.

### Optimización

Si tienes una base de datos muy grande (miles de reportes), considera:

1. Agregar un índice compuesto en la tabla `complaints`:
   ```sql
   CREATE INDEX idx_status_created_attended ON complaints(status, created_at, attended_at);
   ```

2. Ejecutar el script de actualización mediante un cron job en lugar de en cada carga de página:
   ```bash
   # Ejecutar cada hora
   0 * * * * php /ruta/a/update_statuses.php
   ```

## Soporte

Para cualquier problema o pregunta sobre el sistema, revisa el código en los archivos mencionados o contacta al desarrollador del sistema.
