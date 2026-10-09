# Corrección asistida de evaluaciones online

Documento técnico del flujo de corrección con asistencia de un LLM y del
rediseño de la pantalla de resultados. Audiencia: quien continúe este trabajo.

## Principio rector

La aplicación **nunca llama a un modelo**. Todo el circuito es un round-trip de
archivos: la app exporta un `.md` con las respuestas y el contrato de corrección,
el docente lo pasa a su propio asistente (opencode, Claude, Gemini, el que sea),
y la app importa el `.md` ya completado. Cero claves de API, cero workers, cero
costo y cero lock-in de proveedor en la aplicación.

La única escritura del agente es la tabla `grading_suggestions`. `score` y
`block_scores` siguen siendo exclusivamente del docente y pasan por el camino
auditado que ya existe (`AttemptGrader::applyManualMarks()`).

## Flujo de uso (rol docente)

1. En `/evaluations/{id}/results`, botón **"Corrección asistida · N"**.
2. Panel 1: elegir pregunta (por defecto, la primera sin rúbrica), modo
   (`grading` o `rubric`), anonimizar si corresponde. **Generar**, **Copiar**
   (portapapeles, sin round-trip) o **Descargar .md**.
3. Pegar el archivo en el asistente y pedirle que complete las secciones 4
   (rúbrica) y 5 (notas), terminando con un bloque cercado ` ```markdown `.
4. Volver al panel 2, pegar la respuesta, **Revisar** (dry-run: no escribe nada).
5. **Guardar propuestas** y/o **Guardar rúbrica** (acción separada y explícita).
6. En la tabla de intentos, abrir **Corregir**: ver la propuesta por criterio y
   **Aceptar y finalizar** / **Cargar para ajustar** / **Rechazar** (con motivo
   opcional que alimenta la mejora de rúbricas).
7. **Sincronizar Notas** al libro como siempre (dry-run + confirmación).
8. **Medir**: `evaluations:calibrate` compara propuestas contra notas finales
   una vez que la cursada está corregida. Guardar desde el modal liquida las
   propuestas pendientes (igual = aceptada, distinto = con ajustes).

Por defecto el flujo es en dos fases (`--mode=rubric` → el docente aprueba →
`--mode=grading`), para que el agente no se auto-califique. Una sola pasada queda
como atajo, no como default.

## Formato del archivo

Un solo `.md` que es a la vez instrucción y plantilla. El LLM no inventa la
estructura: solo completa valores dentro de dos secciones marcadas.

- Secciones 1–3 (reglas, preguntas, respuestas): las escribe la app, no se parsean.
- Sección 4 (rúbrica) y 5 (notas): tablas Markdown entre marcadores
  `<!-- SAE:RUBRICA:INICIO/FIN -->` y `<!-- SAE:NOTAS:INICIO/FIN -->`.
- Columnas matcheadas **por nombre de encabezado**, no por posición.
- Celdas con `|` escapado como `\|`; decimales con coma aceptados.
- La tabla de notas es **por criterio** (`ref`, `bloque`, `clave`, `puntaje`,
  `flags`, `justificacion`), no por bloque.
- `ref` = id de intento con padding de ceros: estable entre re-exportaciones y
  válido con intentos repetidos del mismo alumno.
- El parser prefiere el bloque cercado final y acepta varios archivos pegados
  juntos (cada export de una pregunta compone por `(ref, bloque)`).
- Todo desvío es **error duro con número de tabla y fila**, nunca salto silencioso.

Defensa anti-prompt-injection: el texto del alumno viaja cercado como dato, los
`<`/`>` se neutralizan, y el flag `instruction_injection` fuerza revisión manual
con 0 puntos.

## Modelo de datos

- `OnlineEvaluation.content`: los bloques `open_text` llevan `rubric`
  (`criteria: [{key, label, points, levels}]`, `source: agent|teacher`,
  `version`). `max_points` se **deriva** de los criterios, nunca se guarda.
- `evaluation_attempts.rubric_snapshot`: rúbrica congelada al crear el intento
  (igual que `content_snapshot`): editar la rúbrica después no reinterpreta
  marcas propuestas contra la anterior.
- `grading_suggestions`: una fila viva por `(attempt, block)`; `state`
  (`proposed|accepted|edited|rejected`), `criteria` con puntos y justificación
  por criterio, `flags` (enum), `agent` (texto libre: qué modelo lo produjo),
  `rubric_version`, `rubric_hash`, `raw_response`, `revision` + `history`
  (incluye el motivo de desacuerdo), `final_points`, `accepted_at/by`.
- Import idempotente: re-pegar lo mismo no hace nada; un archivo distinto
  sobrescribe en el lugar, sube `revision` y **reabre** la propuesta a revisión.
- Una rúbrica que no suma los puntos de la pregunta se **rechaza**, no se guarda.
- Policy separada `exportGrading` (no reusar `grade`): mandar respuestas a un
  modelo externo es otra cosa que corregir.

## Pantalla de resultados (`/evaluations/{id}/results`)

Orden docente: cabecera → **KPIs** → panel de corrección asistida (al abrirse) →
diálogo de corrección (overlay) → tabla de intentos.

- KPIs: pendientes (cola de trabajo), promedio, aprobación. Sin "Rango".
- Tabla como superficie de triage: columnas Alumno / Estado / Nota / Propuesta /
  Acción, badge con pendientes por intento, filtros por estado de revisión.
- Corrección en **modal `<dialog>` nativo a pantalla completa**, con
  anterior/siguiente (`N de M`) sin perder el lugar en la lista. Una sola tabla
  fusionada rúbrica+propuesta por pregunta; la nota se edita **por criterio**
  con suma automática al total del bloque.
- Avisos como `x-notice`: icono que abre modal (teleportado al `<body>`), no
  tooltips largos ni alertas inline.

## Comandos

- `evaluations:export-grading {evaluation} [--out=] [--mode=grading|rubric]
  [--block=] [--anonymize] [--status=submitted] [--preview]`
- `evaluations:seed-examples` (eval3gestion / evalIA / eval-rec-3SIPGO, Parte 1
  teórica + Parte 2 relacional; `--markdown-dir`, `--replace`).
- `evaluations:calibrate {evaluation} [--agent=] [--block=] [--min=10] [--json]`:
  mide el acuerdo entre propuestas y notas finales del docente (tasa de
  aceptación, error medio absoluto en puntos y en % de la pregunta, sesgo,
  aperturas por pregunta y por fuente, banderas con su tasa de rechazo y
  motivos de desacuerdo). Solo entran sugerencias liquidadas con nota final;
  las pendientes se listan para terminar de revisar primero. Debajo de `--min`
  liquidadas imprime una advertencia de muestra chica en vez de concluir nada.

El import vive solo en la UI (botones Guardar del panel 2).

## Tests

`tests/Feature/Evaluations/` (`AssistGradingTest`, `SuggestionReviewTest`,
`GradingSuggestionImporterTest`, `GradingFileBuilderTest`,
`GradingFileRoundTripTest`, `ExportGradingFileTest`, `GradingSuggestionTest`,
`GradingOverlayTest`, `AssistClipboardContractTest`, `EvaluationSeedFidelityTest`,
…) y `tests/Unit/Services/Evaluations/` (`RubricTest`,
`GradingFileParserTest`, …). Regla del módulo: ningún import escribe
`block_scores`; toda fila del parser trae tabla y número de fila en el error.

Suite verde salvo 3 fallos preexistentes y ajenos (`DeployZipTest` ×2,
`InscriptionManagerTest`), que también fallan sin estos cambios.

## Pendiente / trabajo futuro

- Atajos de teclado en el modal (anterior/siguiente, aceptar, rechazar).
- Solo `g1_1` trae rúbrica de ejemplo; el resto se redacta con `--mode=rubric`.
- `ref` expone ids internos de intento; si preocupa, pasar a token opaco con
  `mapa.csv` local.
