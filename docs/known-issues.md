# Fallos conocidos

Registro de fallos detectados durante el desarrollo que **no** están relacionados con el cambio en curso.
Cada entrada debe incluir cómo reproducirlo, la causa raíz y la corrección propuesta, para que cualquier
agente o persona pueda retomarlo sin repetir el diagnóstico.

---

## KI-001 — `grading_suggestions.confidence` no admite los valores de la factory

**Estado:** abierto
**Detectado:** 2026-11-05
**Módulo:** Evaluaciones / Calificación asistida
**Prioridad:** media (bloquea 42 tests, la app funciona porque el valor solo se escribe vía factory/tests)

### Síntoma

Al ejecutar la suite de evaluaciones fallan 42 tests con:

```
SQLSTATE[22003]: Numeric value out of range: 1264
Out of range value for column 'confidence' at row 1
```

La sentencia inserta `confidence = 80`.

### Tests afectados

- `tests/Feature/Evaluations/GradingCalibrationTest.php` (9)
- `tests/Feature/Evaluations/GradingSuggestionImporterTest.php` (10)
- `tests/Feature/Evaluations/GradingSuggestionTest.php` (5)
- `tests/Feature/Evaluations/SuggestionReviewTest.php` (18)

### Reproducción

```bash
php artisan test --compact tests/Feature/Evaluations
```

Los tests de política y de "mis evaluaciones" pasan; solo fallan los que crean
`GradingSuggestion` a través de `database/factories/GradingSuggestionFactory.php`
(`'confidence' => 80`).

### Causa raíz

La migración consolidada `database/migrations/2024_01_01_000040_create_grading_suggestions_table.php`
define la columna como:

```php
$table->decimal('confidence', 3, 2)->nullable();
```

`decimal(3,2)` admite como máximo `9.99`, por lo que `80` queda fuera de rango.
La migración original archivada
`database/migrations_archive/2026_10_08_114753_create_grading_suggestions_table.php`
la definía como `decimal('confidence', 5, 2)` (máximo `999.99`), que es el esquema que la
factory y el modelo (`cast` a `decimal:2`) esperan. La consolidación de migraciones
introdujo la regresión.

### Corrección propuesta

Alinear la migración consolidada con la original:

```php
$table->decimal('confidence', 5, 2)->nullable();
```

Alternativa (solo si el dominio define `confidence` como fracción 0–1): cambiar la factory
a `'confidence' => 0.80`. Verificar antes qué consume el valor (importador, UI, cálculos)
para no romper el significado de `80` (porcentaje).

### Criterio de cierre

`php artisan test --compact tests/Feature/Evaluations` en verde, sin modificar tests
para ocultar el problema.
