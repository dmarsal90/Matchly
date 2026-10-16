# Arquitectura

Matchly es un monolito modular en Laravel 13 con Inertia 3 + Vue 3. Hay un solo repositorio, un solo despliegue y una sola base de datos, pero el código está partido por dominios con fronteras que se comprueban en los tests.

## Módulos

`app/Modules/<Dominio>/` agrupa todo lo de un dominio, con la misma estructura en todos:

| Capa | Contiene | Puede depender de |
| --- | --- | --- |
| `Domain` | Entidades, DTOs (`readonly`), enums, contratos y servicios puros (clasificador, puntuación) | Solo PHP y otros `Domain` |
| `Application` | Casos de uso (`Actions`), consultas, listeners y eventos | `Domain` e interfaces |
| `Infrastructure` | Modelos Eloquent, clientes HTTP, adaptadores, jobs de cola y comandos | Todo |
| `Http` | Controladores finos, Form Requests y Resources | `Application` |

| Módulo | Responsabilidad |
| --- | --- |
| `Providers` | Fuentes de ofertas (Remotive, RemoteOK, ATS…), cliente HTTP con throttling, caché y ETag, e ingesta |
| `Jobs` | Ofertas: normalización, clasificación, deduplicación, frescura, búsqueda, filtros y moderación |
| `Profiles` | Cuestionario, CV, importaciones de GitHub y LinkedIn, perfil de habilidades fusionado y perfil de búsqueda |
| `Matching` | Requisitos de cada oferta (reglas y, opcionalmente, IA) y puntuación de compatibilidad explicable (0-100) |
| `Applications` | Seguimiento de candidaturas con historial, solicitud rápida (CV adaptado, carta y PDF), recordatorios de seguimiento, preparación de entrevistas y panorama (embudo) |
| `Alerts` | Búsquedas guardadas, emparejamiento de ofertas nuevas y resúmenes |
| `Notifications` | Centro de notificaciones y emisión en tiempo real |
| `AI` | `AiGateway`: proveedores intercambiables, prompts versionados, esquemas, presupuesto y coste |
| `Identity` | Cuenta, Fortify, OAuth, consentimientos y RGPD (exportación y borrado) |
| `Shared` | Idioma, cabeceras de seguridad, ficheros cifrados, guardas de URL y middleware de Inertia |

`tests/Architecture/ArchTest.php` impone estas reglas:

- `Domain` no usa el framework.
- Los controladores no tocan la base de datos.
- Todo fichero declara `strict_types`.
- El nombre del producto solo sale de `APP_NAME`.

Los módulos se comunican con **eventos de aplicación**, no con llamadas cruzadas:

- `JobsIngested`: lo escuchan `Matching` (extrae requisitos y puntúa) y `Alerts` (empareja con las búsquedas guardadas).
- `ProfileChanged`: recalcula las puntuaciones y el perfil de búsqueda.
- `ConsentRevoked`: borra los CV o los datos importados de esa fuente.

## Flujos principales

**Ingesta.**

1. El scheduler lanza `jobs:ingest` cada 10 minutos, y cada fuente solo corre cuando le toca según su intervalo y su *back-off*.
2. Se encola un `IngestProviderJob` por fuente, con `ShouldBeUnique`, reintentos y *backoff*.
3. `IngestProvider` procesa cada oferta: proveedor → normalizador → clasificador → saneado con HTMLPurifier → descarte por antigüedad → deduplicación (título + empresa + URL canónica) → upsert idempotente.
4. Se cierran las ofertas que han desaparecido de la fuente, salvo que la ejecución haya tenido fallos parciales.
5. Se emite `JobsIngested`.

La interfaz **siempre** lee de la base de datos, nunca de las APIs externas en vivo.

**Compatibilidad.**

- `ResolveJobRequirements` saca los requisitos de cada oferta con reglas. La IA solo refina, en diferido (`AI_JOB_REQUIREMENTS=lazy`: solo cuando alguien abre la oferta).
- `ScoreJobsForUser` compara esos requisitos con el perfil fusionado y guarda en `job_matches` la puntuación, el nivel y el desglose por categoría.
- Las habilidades con evidencia (GitHub, CV, LinkedIn) pesan más que las solo declaradas.

**Solicitud rápida.** A partir del perfil confirmado se generan un CV maestro, un CV adaptado (reordenado con reglas o reescrito por la IA y verificado contra el original), una carta y los datos de contacto. La persona lo revisa y lo aprueba, y solo entonces puede descargar el PDF. **Nunca se envía nada automáticamente.**

**Seguimiento y copiloto de carrera.** Todo lo que sigue es para la persona: nada se envía a una empresa.

- *Historial.* Cada candidatura guarda sus estados (guardada, preparada, aplicada, entrevista, oferta, aceptada, declinada, rechazada, sin respuesta) y una tabla de eventos (`application_events`: cambios de estado, rondas de entrevista numeradas, seguimientos y notas cifradas). `RecordApplicationEvent` es el único punto que escribe en ella.
- *Recordatorios.* `applications:follow-up` (diario, 09:00) avisa una sola vez de las candidaturas aplicadas sin respuesta tras `FOLLOW_UP_DAYS` (10) días, salvo las que ya tuvieron seguimiento o cuya oferta se cerró. `FollowUpReminderNotification` sale por base de datos, tiempo real y correo (con consentimiento); el borrador del mensaje (`FollowUpDraftWriter`, en el idioma y tono de la carta enviada) solo va en el correo y en el tablero.
- *Preparación de entrevista.* `InterviewPrepBuilder` genera preguntas probables con esquemas STAR que citan las viñetas del propio CV (tarea y resultado quedan en blanco), respuestas honestas para las habilidades que faltan y preguntas para la empresa. Con IA (tarea `interview_prep`) se redacta mejor, pero se vacía todo esquema que nombre una habilidad que el CV no respalda y las respuestas de carencias se limitan a las carencias reales. Se genera en la cola `high` y el contenido se guarda cifrado.
- *Panorama* (`/insights`). `SkillGapReport` agrega las carencias de `job_matches` (ofertas seguidas y mejores afinidades) y las ordena por cuántas ofertas desbloquearían; `ApplicationFunnel` calcula la conversión entre etapas, los tiempos de respuesta y los resultados por fuente y área. Ambos leen datos ya guardados: ninguna llamada a IA.

**Alertas y tiempo real.**

- Las alertas inmediatas se notifican al ingerir. Las diarias y semanales acumulan entregas pendientes y las envía `alerts:digest`.
- `JobAlertNotification` sale por base de datos, por correo (con baja en un clic firmada y `List-Unsubscribe`) y por Reverb, al canal privado `users.{id}`.
- El navegador recibe la dirección de Reverb como prop de Inertia en tiempo de ejecución.

## Procesos y colas

Una sola imagen y varios roles (`docker/entrypoint.sh`): `web`, `horizon`, `scheduler` y `reverb`. Horizon tiene dos supervisores:

| Supervisor | Colas | Para qué |
| --- | --- | --- |
| interactivo | `high`, `notifications`, `default` | Lo que una persona está esperando |
| de fondo | `ingestion`, `ai`, `matching`, `maintenance` | Ingesta, IA, recálculo y limpieza |

Así una ingesta larga nunca retrasa un correo o una solicitud. Las tareas programadas usan `onOneServer` y `withoutOverlapping`.

## Datos y privacidad

- PostgreSQL 17 es la fuente de verdad. La búsqueda usa texto completo nativo (`tsvector` generado, con `pg_trgm` y `unaccent`) y JSONB para requisitos, filtros y desgloses.
- Redis guarda la caché, las colas, las sesiones y el *rate limiting*.
- Los ficheros de usuario (CV, ZIP) se cifran en la aplicación antes de escribirse (`EncryptedFileStore`), sea en disco local o en S3. Las claves de IA de los usuarios usan el cast `encrypted`.
- Cada fuente de datos personales exige un consentimiento explícito y revocable. Cada módulo aporta lo suyo a la exportación y al borrado (`personal-data`), y `privacy:prune` aplica la retención.

## Frontend

Las páginas Inertia están en `resources/js/pages` y los componentes por dominio en `resources/js/features/<dominio>`.

- **Rutas:** las genera Wayfinder, tipadas y sin URLs escritas a mano.
- **Textos:** salen de `lang/{es,en,pt}` mediante `laravel-vue-i18n`. Un test prohíbe el texto fijo en las plantillas y exige las mismas claves en los tres idiomas.
- **Estilos:** Tailwind 4 con tokens de color en `resources/css/app.css`, en modo claro y oscuro, comprobados con axe (WCAG 2.2 AA).

## Calidad

Pint y Larastan de nivel 8, ESLint, Prettier y vue-tsc. La suite de tests:

- Pest, unitarios y de feature, en SQLite y en PostgreSQL.
- Vitest para el frontend.
- Playwright para los flujos clave, en escritorio y en móvil.

Todo corre en cada PR (`.github/workflows/ci.yml`), y la imagen se publica y se despliega desde `main` ([despliegue](deployment.md)).

Más guías: [añadir una fuente de ofertas](adding-a-job-provider.md), [añadir un proveedor de IA](adding-an-ai-provider.md) y [entregabilidad del correo](email-deliverability.md).
