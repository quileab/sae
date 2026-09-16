# Estructura de la Base de Datos y Relaciones

Este archivo documenta las tablas principales y las relaciones del sistema **SAE**.

## Modelos y Tablas Principales

### 1. `users` (Modelo `User`)
Representa a todos los actores del sistema (estudiantes, profesores, administradores y personal de staff).
- **Campos clave**:
  - `id` (int, PK)
  - `role` (enum): `admin`, `student`, `teacher`, `director`, `administrative`, `treasurer`, `user`, `preceptor`
  - `email` (string)
  - `firstname` (string)
  - `lastname` (string)
  - `enabled` (boolean)
- **Helpers y Métodos**:
  - `fullname`: Atributo calculado que devuelve `lastname, firstname`.
  - `isStaff()`: Verifica si el usuario pertenece al personal (`admin`, `principal`, `director`, `administrative`, `preceptor`, `treasurer`).
  - `hasRole($role)` / `hasAnyRole($roles)`: Comprueba roles del usuario.

### 2. `careers` (Modelo `Career`)
Carreras académicas que ofrece la institución.
- **Relaciones**:
  - Muchos a Muchos con `users` (estudiantes y coordinadores asociados).

### 3. `subjects` (Modelo `Subject`)
Materias o cursos que pertenecen a una carrera.
- **Campos clave**:
  - `id` (int, PK)
  - `name` (string)
  - `career_id` (int, FK -> `careers`)
- **Relaciones**:
  - Muchos a Muchos con `users` a través de la tabla pivote `enrollments`.

### 4. `enrollments` (Modelo `Enrollment` / Pivote de `Subject` ↔ `User`)
Registra las inscripciones de los estudiantes (o asignaciones de docentes) a materias específicas.
- **Campos clave**:
  - `user_id` (int, FK -> `users`)
  - `subject_id` (int, FK -> `subjects`)
  - `status` (string): `active`, `completed`, `withdrawn`

### 5. `messages` (Modelo `Message`)
Mensajes del sistema de chat interno.
- **Campos clave**:
  - `id` (int, PK)
  - `sender_id` (int, FK -> `users`)
  - `subject_id` (int, FK -> `subjects`, opcional para chats grupales de cursos)
  - `content` (text)
- **Relaciones**:
  - Muchos a Muchos con `users` a través de la tabla pivote `message_user` (destinatarios) con columna `read_at` para control de lectura.

### 6. Planes y Pagos
- **`payment_plans` (Modelo `PaymentPlan`)**: Cabecera de plan de pagos institucional.
- **`payment_plan_details` (Modelo `PaymentPlanDetail`)**: Detalle de cuotas/vencimientos del plan.
- **`user_payments` (Modelo `UserPayment`)**: Registros de cuotas asignadas de manera individual a un estudiante.
- **`payment_records` (Modelo `PaymentRecord`)**: Historial de transacciones o cobros realizados contra las cuotas.

---

## Diagrama de Relaciones Académicas (Resumen)

```
[Career] 1 --- * [Subject]
   |                 |
   |                 | * (via enrollments)
   * (career_user)   |
   |                 |
[User] (Estudiante / Docente)
   |
   | 1 --- * [Message] (Remitente)
   * ------- * [Message] (Destinatarios, via message_user)
```
