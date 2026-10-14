# Matchly

Plataforma que agrega ofertas de trabajo remoto de fuentes gratuitas, calcula la compatibilidad con el perfil de cada usuario, genera cartas y CV adaptados con IA y envía alertas.

> El nombre del producto se define **solo** en `APP_NAME` (`.env`) y como valor por defecto en `config/app.php`. Todo lo demás (UI, correos, títulos, metadatos, PDF) lo lee de la configuración o de las traducciones con el placeholder `:app`. `tests/Architecture/BrandNameTest.php` falla si aparece escrito a mano.

## Stack

Laravel 13 · PHP 8.4 · Inertia 3 + Vue 3 (TypeScript) · Tailwind CSS 4 · PostgreSQL 17 · Redis 7 · Horizon · Reverb · Fortify · Pest · Larastan · Vitest · Docker.

## Puesta en marcha (Docker)

Requisitos: Docker 24+ con Compose v2 y `make`.

```sh
make setup   # crea .env, construye la imagen, instala dependencias y levanta todo
```

| Servicio  | URL                              |
|-----------|----------------------------------|
| App       | http://localhost:8000            |
| Horizon   | http://localhost:8000/horizon    |
| Mailpit   | http://localhost:8025            |
| Vite HMR  | http://localhost:5173            |
| Reverb    | ws://localhost:8080              |

Los puertos del host se cambian en `.env` (`APP_PORT`, `FORWARD_DB_PORT`, `VITE_PORT`, …) si chocan con otros proyectos.

Comandos habituales (`make help` los lista todos):

```sh
make up / make down      # arrancar / parar
make shell               # shell en el contenedor app
make migrate             # migraciones
make test                # Pest + Vitest
make check               # lo mismo que la CI: Pint, ESLint, Prettier, Larastan, vue-tsc y tests
make logs s=horizon      # logs de un servicio
make horizon-restart     # recargar workers tras cambiar código de jobs
```

Xdebug viene instalado e inactivo: pon `XDEBUG_MODE=debug` en `.env` y ejecuta `docker compose up -d`.

## Sin Docker

PHP 8.3+ (pdo_pgsql, redis, intl, zip, pcntl), Composer, Node 22, PostgreSQL y Redis locales:

```sh
composer setup           # dependencias, .env, APP_KEY, migraciones y build
composer dev             # servidor, cola, logs y Vite
```

Los tests usan SQLite en memoria (`phpunit.xml`) y no necesitan servicios.

## Imagen Docker

Un único `Dockerfile` multi-stage sirve para todos los procesos:

| Target     | Uso                                                                 |
|------------|---------------------------------------------------------------------|
| `dev`      | desarrollo: Composer, Node y Xdebug; el código se monta como volumen |
| `prod`     | producción (target por defecto): nginx + php-fpm, OPcache, usuario no root, `HEALTHCHECK` |

El rol del contenedor lo elige el primer argumento del entrypoint (`docker/entrypoint.sh`):

| Rol         | Proceso                                   | Migraciones |
|-------------|-------------------------------------------|-------------|
| `web`       | nginx + php-fpm (supervisord)             | sí          |
| `fpm`       | php-fpm (desarrollo)                      | sí          |
| `horizon`   | workers de cola gestionados por Horizon   | no          |
| `worker`    | `queue:work` simple                       | no          |
| `scheduler` | `schedule:work`                           | no          |
| `reverb`    | servidor WebSocket                        | no          |

```sh
make prod-image          # construir la imagen de producción en local
```

## Estructura

El código de negocio vive en `app/Modules/<Dominio>/{Domain,Application,Infrastructure,Http}`. Los tests de `tests/Architecture` hacen cumplir las fronteras: `Domain` no depende del framework, los controladores no tocan la base de datos y todo archivo declara `strict_types`.

La documentación completa de arquitectura, cómo añadir proveedores de empleo o de IA y el despliegue (Laravel Cloud, Railway/Render/Fly.io, VPS) se completa en la fase de CI/CD.
