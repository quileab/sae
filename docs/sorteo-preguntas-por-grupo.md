# Sorteo de preguntas por grupo

Dos alumnos que se sientan juntos no pueden recibir el mismo examen. Esto es un
sorteo por **grupo temático**, no un banco de preguntas guardadas aparte: el
grupo ya existe en la evaluación y lo que se le marca es cuántas de sus
preguntas se reparten.

## El modelo

Una evaluación es una lista de grupos (`content.sections[]`). Cada grupo tiene
título, introducción y sus preguntas (`blocks[]`). Un grupo con

```json
{ "draw": 1, "blocks": [ ...4 preguntas... ] }
```

entrega **una sola de esas cuatro**, sorteada al azar, a cada alumno. Un grupo
sin `draw` (o con `draw: 0`) entrega todas, como antes. El resultado es que
todos los alumnos cubren todos los grupos y casi ninguno recibe la misma
combinación.

`draw: N` entrega N, siempre que el grupo tenga más de N. Pedir más de lo que
hay no rompe nada: se entregan todas.

En el editor, el campo **Sorteo** de cada grupo es ese `draw` (0 = todas). La
tarjeta "Reglas de corrección" muestra el total que se va a corregir y avisa
cuando las alternativas de un grupo valen distinto.

## Por qué la nota se corrige sobre lo que le tocó

El puntaje se normaliza a escala 0-10 sobre el total de preguntas **recibidas**,
no sobre el total de la evaluación. Es lo único coherente cuando cada alumno
contesta una cantidad distinta: corregir un parcial de 4 preguntas sobre el total
de las 12 que había en la evaluación reprobaría a todos.

La contrapartida es que dos notas dejan de significar lo mismo si las
alternativas de un grupo valen distinto: un alumno puede haber sacado solo las
fáciles. El editor avisa cuando el rango de un grupo sorteado no es parejo. La
regla que queda: **dentro de un grupo sorteado, todas las alternativas valen lo
mismo**.

## Dónde queda registrado

`AttemptContent::draw()` arma la forma sorteada en el momento de crear el
intento, y esa forma es lo que se guarda en `evaluation_attempts.content_snapshot`.
Ahí está la garantía:

- El snapshot es lo único que define qué vio el alumno. Cambiar la evaluación
  después no le altera el examen.
- `AttemptGrader` normaliza contra el snapshot, así que el denominador es
  correcto sin tocarlo.
- El reintento sortea de nuevo: es otra combinación, no un repaso de la primera.
- El `rubric_snapshot` se arma desde la forma servida, así que el alumno queda
  medido contra la rúbrica de la pregunta que recibió y no contra las de las
  alternativas que nunca vio.

El **orden** de presentación es un asunto aparte y no se mezcla con esto:
`online_evaluations.shuffle_questions` sigue barajando el orden dentro de cada
grupo, y el resultado se guarda en `answers._metadata.shuffled_content` para que
no cambie al recargar.

## Vista previa

La vista previa del docente abre una **combinación real**, la misma que recibiría
un alumno, y sobre el paper ofrece un selector con todas las alternativas del
grupo para revisarlas una por una, más un botón para volver a sortear. Es la
forma de detectar que una de las variantes quedó mal escrita o demasiado fácil.

## Exportar a un asistente externo

`evaluations:export-grading` y el panel de corrección asistida ya no asumen una
única lista de preguntas para todo el curso. El archivo de Markdown:

- Lista en la sección 2 **todas** las preguntas que se repartieron, con su
  `[P#]` estable.
- En la sección 3, cada alumno lleva **solo las preguntas que recibió**.
- Dice explícitamente que hay una alternativa distinta por grupo, para que el
  modelo no intente calificar preguntas que ese alumno no vio.

El panel del docente arma la lista de preguntas desde la **unión** de los
intentos pendientes de corregir, así que al escribir la rúbrica aparecen todas
las variantes y no solo la del primer alumno.

El aviso `OJO` del encabezado cambió de significado: ya no avisa que el curso no
comparte contenido (eso ahora es normal), sino que una pregunta que alguien ya
respondió **cambió o se borró** desde entonces, y por eso se exporta la versión
que vio el alumno.

## Lo que no cambia

`answers`, `block_scores` y `grading_suggestions` siguen indexados por
`block['id']`. Ninguna parte de la corrección necesita conocer el orden ni saber
qué grupo vino de dónde, y por eso no hizo falta tocar la lógica de puntajes,
la sincronización al libro ni la calibración.