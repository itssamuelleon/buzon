# 📬 Buzón de Quejas Institucional - ITSCC

Este proyecto es una aplicación web Full-Stack desarrollada en PHP para modernizar y digitalizar la recolección de quejas, sugerencias y reconocimientos en el Instituto Tecnológico Superior de Ciudad Constitución (ITSCC). El sistema permite a estudiantes, docentes y personal administrativo enviar reportes de manera segura y transparente.

---

## ✨ Características Principales

* **Gestión de Reportes:** Permite el envío de reportes de forma anónima o identificada. Los usuarios pueden clasificar sus envíos y adjuntar múltiples evidencias, como imágenes y documentos en formatos PDF o Word.
* **Sistema de Roles y Accesos:** Cuenta con acceso segmentado para Estudiantes (creación y seguimiento de reportes propios), Encargados de Departamento (gestión de reportes asignados) y Administradores (control total de configuración y acceso a estadísticas globales).
* **Integración de Inteligencia Artificial:** Utiliza la API de Google Gemini (modelo gemini-2.5-flash-lite) para asistir a los administradores. La IA analiza el contenido para clasificar reportes automáticamente, sugerir el departamento correspondiente, y detectar tanto envíos duplicados como inválidos.
* **Sistema de Estados Automatizado:** Evalúa y clasifica los reportes como atendidos o sin atender (a tiempo o con retraso) basándose en un plazo de respuesta establecido de 5 días hábiles. Esta lógica se mantiene actualizada mediante tareas programadas (Cron Jobs) que corren en el servidor.
* **Notificaciones Asíncronas:** Implementa una tabla en la base de datos que funciona como cola de correos electrónicos. Un proceso asíncrono utiliza PHPMailer para enviar alertas de seguimiento a los usuarios y notificaciones a los departamentos sin interrumpir el flujo de navegación web.
* **Seguridad Robusta:** La arquitectura incluye protección estricta contra inyección SQL mediante el uso de sentencias preparadas en todas las consultas a la base de datos. Adicionalmente, previene ataques XSS sanitizando todas las salidas de texto y utiliza el algoritmo bcrypt para el hash seguro de las contraseñas de los usuarios.

---

## 🛠️ Stack Tecnológico y Arquitectura

El sistema está construido siguiendo un patrón de diseño procedural enfocado en la separación lógica de responsabilidades (Frontend, Backend, Configuración y Base de Datos).

### Backend & Base de Datos
* **Lenguaje Principal:** PHP 7.4 o superior.
* **Gestor de Base de Datos:** MySQL 5.7+ o MariaDB 10.3+.

### Frontend y UI
* **Estructura y Estilos:** HTML5 y CSS3 impulsados por el framework de utilidades TailwindCSS (integrado vía CDN).
* **Interactividad Dinámica:** JavaScript nativo en conjunto con Alpine.js para la reactividad ligera del DOM, y la librería AOS para animaciones fluidas al hacer scroll.
* **Visualización de Datos:** Chart.js, utilizado para la renderización de métricas y gráficos de rendimiento en el panel de los administradores.

---

## ⚙️ Instalación y Configuración Local

Para desplegar este proyecto en un entorno de desarrollo (se recomienda el uso de Apache mediante Laragon), debes seguir estos pasos:

1. **Clonar el repositorio:**
   `git clone https://github.com/itssamuelleon/buzon.git`
2. **Configuración de Variables de Entorno:**
   Crea un archivo llamado `.env` en la raíz del proyecto para alojar de forma segura las credenciales de conexión a la base de datos local y tu llave privada `GEMINI_API_KEY`.
3. **Migración de Base de Datos:**
   Ejecuta el script SQL estructurado en la documentación para generar el esquema completo de las tablas relacionales requeridas por el sistema.
4. **Configuración del Servidor (Opcional pero recomendado):**
   Configura un Virtual Host en tu instalación de Apache que apunte directamente a la carpeta del proyecto, asegurándote de tener el módulo `mod_rewrite` habilitado.

---

## 🚀 Estado del Proyecto

**En Producción:** Puedes visitar el entorno en vivo que da servicio a toda la comunidad estudiantil a través del dominio oficial de la institución:
🔗 [cdconstitucion.tecnm.mx/buzon](https://cdconstitucion.tecnm.mx/buzon)
