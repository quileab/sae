# SAE Workspace Rules & Guidelines

Este archivo contiene el contexto del proyecto **SAE**, consolidando las reglas del backend, la base de datos y el frontend para el agente.

## 1. Stack Tecnológico & Convenciones Generales
- **Backend**: PHP 8.4, Laravel 12.
- **Frontend**: Livewire v4, Livewire Volt v1 (SFC), MaryUI (DaisyUI), TailwindCSS v4.
- **Idiomas**: Código en **Inglés** (variables, métodos, DB), Interfaz de Usuario en **Español**.
- **Formateador**: PHP Pint (`vendor/bin/pint --dirty --format agent`).
- **Nuevos Componentes**: Siempre deben ser componentes **Volt SFC** (Single File Components).
- **Despliegue**: El comando para generar paquetes de despliegue ZIP es `php artisan make:deploy-zip` (excluye `vendor` y archivos ocultos).

---

## 2. Arquitectura de Backend & Módulos
- **Usuarios & Roles**: Control de acceso vía `middleware('roles:...')` (`admin`, `teacher`, `student`, `preceptor`, `director`, `administrative`, `treasurer`, `user`).
- **Gestión Académica**:
  - Carreras (`careers.career-list`, `careers.career-form`).
  - Materias (`subjects.subject-list`, `subjects.subject-table`, `subjects.subject-form`).
  - Inscripciones (`enrollments` pivote, `inscriptions.inscription-manager` autogestión con descargas PDF).
- **Asistencia & Clases**:
  - `class_sessions.class-session-list` (Libro de temas).
  - `class_sessions.student-directory` (Visualizar y calificar alumnos).
  - `attendance.attendance-list` (Asistencia diaria).
  - PWA sincronizable (`pwa-attendance`) offline/online con `AttendanceSyncController`.
- **Chat Interno (`Chat.php`)**:
  - **Staff** habla con todos.
  - **Teachers** hablan con Staff y estudiantes de sus cursos.
  - **Students** hablan con Staff y Teachers de sus materias. Chat entre estudiantes prohibido.
  - Pestaña "Nuevo": Botones de acción rápida. Al elegir destinatario, muestra "Abrir conversación" en lugar de redactar en la pestaña para evitar duplicados.
- **Biblioteca & Pagos**:
  - Libros y Préstamos (`books.index`, `books.loans`).
  - Planes de pago (`pay-plans`), cobro a alumnos (`user-payment-component`), pasarela Mercado Pago (`MercadoPagoController`) con Webhooks.

---

## 3. Modelo de Datos & Relaciones Clave
- **`User` (tabla `users`)**:
  - Atributo calculado `fullname` (`lastname, firstname`).
  - Helpers: `isStaff()`, `hasRole($role)`, `hasAnyRole($roles)`.
- **`Career` & `Subject`**:
  - Relación de muchos a muchos `Subject` ↔ `User` a través de pivote `enrollments` (estados: `active`, `completed`, `withdrawn`).
- **`Message` (tabla `messages`)**:
  - Relación `Message` ↔ `User` (destinatarios) mediante la tabla pivote `message_user` con `read_at` para control de lectura.
- **Finanzas**:
  - `PaymentPlan` -> `PaymentPlanDetail` (cuotas del plan).
  - `UserPayment` (cuota asignada al alumno).
  - `PaymentRecord` (transacciones contra las cuotas).

---

## 4. Frontend & Componentes MaryUI
- Evitar colores HTML puros. Usar la paleta de temas de DaisyUI/MaryUI (`bg-base-100`, `text-primary`, `btn-primary`, etc.).
- Preferir siempre componentes de MaryUI:
  - Botón: `<x-button label="..." icon="..." class="btn-primary" />`
  - Selectores: `<x-select label="..." wire:model.live="..." :options="..." option-value="id" option-label="name" />`
  - Megáfonos/Alertas: `<x-alert icon="o-megaphone" class="alert-warning">...</x-alert>`
  - Inputs: `<x-textarea placeholder="..." wire:model="..." />`
