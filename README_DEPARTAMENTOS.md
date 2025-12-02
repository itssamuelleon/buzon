# Instrucciones para Agregar la Columna is_hidden a Departamentos

## Descripción
Este script agrega la funcionalidad para ocultar departamentos en el sistema de buzón de quejas.

## Pasos para ejecutar el script SQL

### Opción 1: Usando phpMyAdmin
1. Abre phpMyAdmin en tu navegador (normalmente en `http://localhost/phpmyadmin`)
2. Selecciona la base de datos del buzón de quejas
3. Haz clic en la pestaña "SQL"
4. Copia y pega el contenido del archivo `add_department_hidden_column.sql`
5. Haz clic en "Continuar" o "Go"

### Opción 2: Usando la línea de comandos de MySQL
```bash
mysql -u root -p nombre_de_tu_base_de_datos < sql/add_department_hidden_column.sql
```

### Opción 3: Usando Laragon Terminal
1. Abre Laragon
2. Haz clic derecho en el ícono de Laragon en la bandeja del sistema
3. Selecciona "MySQL" > "MySQL Console"
4. Ingresa tu contraseña (por defecto suele estar vacía, solo presiona Enter)
5. Ejecuta:
```sql
USE nombre_de_tu_base_de_datos;
SOURCE C:/laragon/www/buzon/sql/add_department_hidden_column.sql;
```

## Verificación
Después de ejecutar el script, verifica que la columna se haya agregado correctamente:

```sql
DESCRIBE departments;
```

Deberías ver una columna llamada `is_hidden` de tipo `TINYINT(1)` con valor por defecto `0`.

## Funcionalidad Implementada

### En admin_settings.php:
- **Ícono de lápiz**: Aparece en la esquina superior derecha de cada tarjeta de departamento
- **Modal de edición**: Al hacer clic en el lápiz, se abre un popup para:
  - Editar el nombre del encargado
  - Ocultar/mostrar el departamento
- **Cambios pendientes**: Los cambios no se aplican inmediatamente, se marcan como pendientes
- **Indicador visual**: El botón "Guardar Cambios" muestra un anillo amarillo y un punto pulsante cuando hay cambios pendientes
- **Alerta de cambios**: Aparece un banner amarillo indicando que hay cambios pendientes
- **Badge "Oculto"**: Los departamentos ocultos muestran un badge gris con ícono de ojo tachado

### En view_complaint.php:
- Los departamentos ocultos NO aparecen en la lista al asignar departamentos a un reporte
- Solo se muestran los departamentos con `is_hidden = 0`

## Notas Importantes
- Los cambios solo se guardan cuando haces clic en "Guardar Cambios" e ingresas tu contraseña de administrador
- Los departamentos ocultos no se eliminan de la base de datos, solo se marcan como ocultos
- Puedes volver a mostrar un departamento oculto editándolo nuevamente y desmarcando la opción "Ocultar departamento"
