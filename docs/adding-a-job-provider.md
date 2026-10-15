# Añadir una fuente de ofertas

Cada fuente son dos clases y una entrada de configuración. El pipeline de ingesta (`IngestProvider`) no se toca.

## 1. Proveedor (`JobProvider`)

`app/Modules/Providers/Infrastructure/Sources/<Nombre>/<Nombre>Provider.php`

- `definition()` devuelve un `ProviderDefinition`: clave, nombre, web, URL de los términos de uso, si el tablero es solo remoto (`remoteOnly`) y si exige atribución.
- `fetch()` es un generador que hace `yield new RawJob($clave, $idExterno, $payload)` por cada oferta publicada. Usa siempre `ProviderHttpClient`, que ya aplica throttling, reintentos, caché y ETag; nunca llames a `Http` directamente.
- Pagina hasta `max_pages` y corta en cuanto llegues a ofertas más antiguas que `jobs.max_age_days`.
- Si una parte puede fallar sin invalidar el resto (varias empresas, varios feeds), implementa `ReportsPartialFailures`. Una ejecución con fallos parciales **no cierra** ofertas desaparecidas.
- Para un ATS nuevo, extiende `AbstractAtsProvider`: solo tienes que implementar `fetchCompany()` y `externalId()`. Las empresas se leen de la tabla `ats_companies`.

## 2. Normalizador (`JobNormalizer`)

`normalize(RawJob): ?JobDraft` traduce el payload al modelo común. Devuelve `null` para descartar.

- Lee el payload con `Payload::of($raw->payload)`: accesos tipados, notación con puntos, fechas en epoch, milisegundos o ISO.
- Rellena solo lo que la fuente dice explícitamente (`workMode`, `remoteScope`, `allowedRegions`, `seniority`…). Lo demás lo decide el clasificador a partir de `locationText` y del título.
- `applyUrl` es el enlace oficial de la oferta. Si los términos exigen enlazar a la propia fuente (Remotive, Remote OK), usa la URL de su ficha.
- La descripción puede llegar con HTML sin limpiar: el pipeline la sanea con HTMLPurifier.

## 3. Configuración

Añade la entrada en `config/jobs.php` → `providers`:

```php
'mi-fuente' => [
    'enabled' => (bool) env('JOBS_MI_FUENTE_ENABLED', true),
    'provider' => MiFuenteProvider::class,
    'normalizer' => MiFuenteNormalizer::class,
    'interval' => 120,    // minutos mínimos entre ejecuciones (respeta los términos)
    'rate_limit' => 10,   // peticiones por minuto
    'cache_ttl' => 1800,  // segundos que se reutiliza una respuesta
    'options' => ['url' => 'https://api.example.com/jobs'],
],
```

## 4. Tests

1. Guarda una respuesta real recortada en `tests/Fixtures/providers/`.
2. Añade un caso en `tests/Unit/Providers/NormalizersTest.php` para el mapeo de campos.
3. Añade una entrada al dataset de `tests/Feature/Providers/SourcesIngestionTest.php` para el flujo completo con `Http::fake`.

## Probarla

```sh
make shell
php artisan jobs:ingest mi-fuente --sync -v   # -v muestra los motivos de descarte
php artisan jobs:sources
```
