# Arquitectura Backend y Lógica del Sistema

Este archivo describe el funcionamiento del backend, helpers, rutas y reglas de negocio de todas las secciones del proyecto **SAE**.

## Stack Tecnológico y Configuración General
- **PHP 8.4**
- **Laravel 12**
- **Livewire v4** & **Volt v1** (SFC)
- **Pint**: Formateador de código PHP (`vendor/bin/pint --dirty --format agent`).

---

## 1. Módulos y Secciones del Proyecto

### A. Gestión de Usuarios y Roles (`routes/academic.php`)
- **Control de Roles (`middleware('roles:...')`)**: Limita el acceso a las rutas según el rol del usuario (por ejemplo, `admin`, `teacher`, `student`, `preceptor`).
- **Lista y Formulario de Usuarios (`users.user-list`, `users.user-form`)**: Permite administrar (crear, editar, deshabilitar) las cuentas. Incluye un módulo de importación masiva (`users.import`).
- **Configuraciones Generales (`config-manager`)**: Administra parámetros globales del sistema escolar (ciclo lectivo, feriados, etc.).

### B. Gestión Académica (Carreras, Materias e Inscripciones)
- **Carreras (`careers.career-list`, `careers.career-form`)**: Listados y edición de planes de estudio y coordinadores de carreras.
- **Materias (`subjects.subject-list`, `subjects.subject-table`, `subjects.subject-form`)**: Gestión de materias, asignación de docentes, horarios y cargas horarias.
- **Matriculaciones (`enrollments`)**: Permite inscribir estudiantes en materias y asignaturas de forma manual o masiva.
- **Inscripciones (`inscriptions.inscription-manager`, `inscriptions.inscription-list`)**: Administra el proceso de autogestión de inscripciones de alumnos en carreras y años lectivos, con descargas de formularios PDF (`inscriptionsPDF`).

### C. Clases, Asistencias y Calificaciones
- **Sesiones de Clase (`class_sessions.class-session-list`, `class-session-form`)**: Registro de los temas dictados por clase para el Libro de Temas (`class_sessions.students`).
- **Directorio de Estudiantes (`class_sessions.student-directory`)**: Componente Volt que permite a docentes e inspectores visualizar y calificar a los alumnos inscritos.
- **Gestión de Asistencia (`attendance.attendance-list`)**: Permite a los preceptores y docentes tomar asistencia diaria.
- **API y Sincronización PWA (`pwa-attendance`)**: Permite a los preceptores tomar asistencia offline desde una aplicación móvil (PWA) y sincronizarla con el servidor central mediante el controlador `AttendanceSyncController`.
- **Informes de Riesgo (`reports.risk`)**: Genera reportes automatizados de alumnos con riesgo de abandono escolar (basado en faltas consecutivas y bajas calificaciones).

### D. Chat y Mensajería Interna (`Chat.php`)
El chat permite comunicación interna segura entre roles escolares con reglas estrictas:
- **Personal (Staff)**: Puede comunicarse con cualquier usuario e iniciar difusiones grupales o masivas.
- **Docentes (`teacher`)**: Pueden comunicarse con el personal de Staff y con estudiantes que formen parte de sus cursos asignados.
- **Estudiantes (`student`)**: Pueden comunicarse con el personal de Staff y con docentes de sus cursos vigentes. Tienen prohibido el chat entre estudiantes.
- **Pestaña "Nuevo"**: Utiliza botones interactivos rápidos en lugar de un dropdown selector. Para evitar duplicidades, no permite redactar en la pestaña: al elegir el destinatario, muestra un botón **"Abrir conversación"** que redirige a la ventana principal de chat.

### E. Biblioteca y Gestión de Préstamos
- **Inventario (`books.index`, `books.create`)**: Permite administrar el catálogo físico de libros del establecimiento.
- **Préstamos (`books.loans`)**: Controla las fechas de retiro, devolución y estado de libros prestados a docentes y estudiantes.

### F. Gestión de Pagos y Finanzas (`routes/payments.php`)
- **Planes de Pagos (`pay-plans`)**: Permite estructurar e implementar planes de cuotas institucionales.
- **Cobros a Alumnos (`user-payment-component`)**: Muestra el estado de cuenta y cuotas del estudiante. Permite a los administradores registrar pagos parciales o totales y combinar cuotas duplicadas.
- **Reportes de Deudas e Ingresos (`report-payments`, `report-debts-sfc`)**: Alertas y listados para el seguimiento de la morosidad y auditoría de la caja diaria.
- **Pasarela Mercado Pago (`MercadoPagoController`)**: Permite pagos en línea autónomos mediante Webhooks para cobros automáticos e instantáneos de las cuotas desde la PWA/Portal.

---

## 2. Convenciones y Arquitectura de Código

- **Constructor Property Promotion** (PHP 8.4):
  ```php
  public function __construct(public InscriptionRepository $repository) {}
  ```
- **Type Hinting Estricto**: Obligatorio declarar tipos en los parámetros y retornos de cada método:
  ```php
  public function registerUser(User $user, float $amount): bool
  ```
- **Casts del Modelo**: Declarar las conversiones y casts a nivel de método:
  ```php
  protected function casts(): array {
      return [
          'date' => 'date',
          'enabled' => 'boolean',
      ];
  }
  ```
