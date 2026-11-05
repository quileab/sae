# Inscripciones (`/inscriptions`)

Documento de referencia para agentes/LLM. Define el contrato de datos y las reglas
obligatorias del módulo de inscripciones. Si el código contradice este documento,
el documento es la intención y el código debe corregirse (o este documento
actualizarse explícitamente).

## Contrato de datos

- **Períodos**: filas de `configs` con `group = 'inscriptions'`.
  - `value = 'true'`: período abierto para estudiantes.
  - `value = 'false'`: período cerrado.
- **Catálogo del ADMIN**: filas de `inscriptions` con `user_id = 1`.
- **Selección del ESTUDIANTE**: filas de `inscriptions` con `user_id = <id del estudiante>`.
- `configs_id` identifica el período (`mesasExamen`, `modality`, `promoDirecta` o el que se agregue).
- `value` es **texto libre**. Puede ser:
  - una fecha (`20/07`),
  - una letra (`A`),
  - una palabra (`Presencial`),
  - una lista de opciones separadas por coma (`Presencial,Semipresencial,Libre`).

  **Nunca asumir que `value` es una fecha ni validar un formato fijo.**

- `type` define la forma de selección:
  - `csv-1`: el estudiante elige **una sola** opción.
  - `csv-n`: el estudiante elige **una o varias** opciones.
- El `type` se define en el catálogo del ADMIN (`user_id = 1`), por período y
  materia. No debe depender del estado local del componente, de la sesión ni de
  valores enviados por el cliente.
- Un `value` compuesto solo por signos o espacios (por ejemplo `-----`, usado
  como marcador de posición) equivale a **sin opciones**: la materia no se
  muestra al estudiante.
- Las materias se listan **ordenadas por `id` ascendente**. El ID codifica
  carrera, año y ubicación (ej. `40201` = carrera `400`, año `02`, ubicación
  `01`), por lo que el orden numérico es el orden académico esperado.
- Para `csv-n`, la selección del estudiante se persiste como string separado por
  comas (`A,B,C`), sin espacios sobrantes, sin vacíos, sin duplicados y
  respetando el orden del catálogo. No guardar arrays en la columna `value`.
- Restricción existente: un único registro por `subject_id + user_id + configs_id`.

## Regla obligatoria: ADMIN = `users.id = 1`

- El usuario ADMIN del sistema es **siempre `users.id = 1`**.
- El catálogo de inscripciones se lee y escribe con `user_id = 1`.
- **No** resolver el admin por nombre, email ni rol.
- **No** usar `auth()->id()` ni otro fallback.
- Toda excepción a esta regla debe quedar documentada explícitamente en este
  archivo antes de implementarse.
- Los tests deben crear el admin con `id = 1`
  (`User::factory()->create(['id' => 1, ...])`).

## Flujo ADMIN

1. Habilita/deshabilita el período en `configs` (`group = 'inscriptions'`).
2. Carga el catálogo por materia en `/inscriptions`: define `value` y `type`
   (`csv-1` o `csv-n`).
3. Un valor vacío elimina la opción de esa materia.
4. Cambiar `type` debe afectar de inmediato la selección que verá el estudiante.
5. El catálogo vigente es el de las filas con `user_id = 1`; no filtrar por
   heurísticas como `user_id > 1000`.
6. Al guardar con `type = csv-n`, el `value` se normaliza (trim, sin vacíos, sin
   duplicados) y se persiste como `A,B,C`. Con `csv-1` se guarda tal cual
   (recortado).
7. La interfaz muestra una vista previa de las opciones del catálogo antes de
   guardar y un contador de materias con/sin opciones.

## Flujo ESTUDIANTE

1. Solo ve períodos con `value = 'true'`.
2. Solo ve materias que tengan catálogo cargado por el ADMIN.
3. Elige según `type`: una opción (`csv-1`) o varias (`csv-n`).
4. El servidor valida período abierto, carrera del estudiante, materia de la
   carrera y que la selección pertenezca al catálogo.
5. Al cargar la pantalla, las selecciones guardadas que ya no existen en el
   catálogo vigente se descartan (se avisa al estudiante) y se eliminan de la
   base al guardar. Nunca deben bloquear el guardado con un error de validación.
6. Al guardar se persiste la selección; si cambió respecto a lo guardado, se
   elimina el PDF anterior para no servir uno obsoleto.
7. Al confirmar se persiste la selección y se genera/actualiza el PDF.

## PDFs

- El nombre del PDF se deriva de `insc-{student_id}-{career_id}-{configs_id}-.pdf`.
- Nombre, ruta, existencia y borrado están centralizados en
  `App\Services\InscriptionPdfService`; cualquier cambio debe hacerse ahí y
  cubrirse con tests.
- Un PDF existente se invalida (se borra) cuando cambia la selección del
  estudiante y antes de regenerarlo al confirmar.
