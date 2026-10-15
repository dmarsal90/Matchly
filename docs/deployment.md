# Despliegue

Matchly es un monolito Laravel con procesos de larga duración: servidor web, workers de cola (Horizon), scheduler y Reverb (WebSockets). Por eso no vale un hosting estático ni uno solo de PHP por petición: hace falta una plataforma que ejecute contenedores o procesos persistentes.

Todos los procesos salen de **la misma imagen** (`Dockerfile`, target `prod`). Solo cambia el primer argumento, que es el rol que interpreta `docker/entrypoint.sh`:

| Rol | Proceso | Notas |
| --- | --- | --- |
| `web` | Nginx + PHP-FPM en el puerto 8080 | Espera a la BD, ejecuta `migrate --force` (salvo `RUN_MIGRATIONS=false`), cachea config, rutas, vistas y eventos, y enlaza `storage`. |
| `horizon` | Workers de cola supervisados | Nunca migra. |
| `worker` | `queue:work` sin Horizon | Alternativa a `horizon` donde no haya Redis o se prefiera. |
| `scheduler` | `schedule:work` | Una sola instancia (las tareas usan `onOneServer`). |
| `reverb` | Servidor de WebSockets | |
| cualquier otro | Se ejecuta tal cual | `php artisan schedule:run`, `php artisan tinker`… |

El healthcheck de la imagen (`docker/healthcheck.sh`) conoce el rol y comprueba lo que toca: `/up` en web, `horizon:status` en Horizon y el puerto en Reverb.

## Entornos y secretos

| Entorno | Dónde | Despliegue |
| --- | --- | --- |
| local | `docker-compose.yml` (README) | `make up` |
| staging | La plataforma elegida | Automático en cada push a `main` |
| production | La plataforma elegida | Manual desde GitHub Actions, con aprobación |

- **Nunca hay secretos en el repositorio.** En una PaaS se guardan en el panel del proveedor y en un VPS en el `.env` del servidor, que es solo legible por su dueño (`chmod 600 .env`). Los de la CD van en los secretos de GitHub.
- `deploy/env.production.example` lista lo que cambia respecto a `.env.example` en staging y producción. Cada entorno tiene su propio `APP_KEY`, sus claves de Reverb y su base de datos.
- Al rotar `APP_KEY`, mueve la anterior a `APP_PREVIOUS_KEYS`: los CV y las claves de IA de los usuarios están cifrados con ella.
- **Tiempo real:** el navegador recibe la dirección de Reverb en tiempo de ejecución (`REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT`, `REVERB_PUBLIC_SCHEME`), no al compilar. Por eso la misma imagen sirve para staging y producción. `REVERB_HOST` y `REVERB_PORT` son la dirección interna con la que PHP publica los eventos.

## CI/CD (GitHub Actions)

**`.github/workflows/ci.yml`** se ejecuta en cada pull request y en cada push a `main`:

- Pint y Larastan.
- Pest dos veces: en SQLite y en PostgreSQL 17 con Redis. La pasada de PostgreSQL mide la cobertura y falla por debajo del 90 %.
- Frontend: build, ESLint, Prettier, vue-tsc y Vitest.
- Playwright contra PostgreSQL, Redis y Mailpit como servicios.
- En los PR, además, el build de la imagen de producción con una prueba de humo: usuario no root y extensiones cargadas.

Composer, npm, los navegadores de Playwright y las capas de Docker van en caché.

**`.github/workflows/deploy.yml`** en cada push a `main`:

1. Construye la imagen para `amd64` y `arm64`, cada una en su propio runner (sin emulación), y la publica en GHCR como `ghcr.io/dmarsal90/matchly:sha-<commit>` y `:latest`. Así vale para servidores x86 y para ARM, como los gratuitos de Oracle. No lleva SBOM ni atestaciones de procedencia, porque complican la unión de las dos arquitecturas.
2. La escanea con Trivy (CRITICAL y HIGH con parche disponible). El resultado va a la pestaña *Security* y no bloquea el despliegue.
3. Despliega en el entorno `staging`.

Si lo lanzas a mano (*Actions → Deploy → Run workflow*), despliega cualquier etiqueta ya publicada en `staging` o en `production`. Volver a lanzarlo con una etiqueta anterior es el **rollback**.

Configura en *Settings → Environments* un entorno `staging` y otro `production`. Al de producción ponle *Required reviewers*. Cada entorno necesita una de estas dos cosas, según el destino:

| Destino | Configuración del entorno |
| --- | --- |
| PaaS (Render, Railway, Laravel Cloud, Forge) | Secreto `DEPLOY_HOOK_URL` (el *deploy hook* del servicio) |
| VPS | Variables `DEPLOY_SSH_HOST`, `DEPLOY_SSH_USER`, `DEPLOY_SSH_KNOWN_HOSTS` (`ssh-keyscan <host>`) y `DEPLOY_PATH`, y el secreto `DEPLOY_SSH_KEY` |

Además, la variable `APP_URL`: tras desplegar, el workflow espera a que `APP_URL/up` responda 200 y falla si no lo hace en 5 minutos.

Dependabot (`.github/dependabot.yml`) abre cada lunes los PR de Composer, npm, GitHub Actions y las imágenes de Docker, agrupando las versiones menores.

## VPS con Docker Compose

Para probarlo sin gastar nada, la guía [Staging gratis en Oracle Cloud](oracle-free-staging.md) lo monta de principio a fin en una máquina ARM del plan Always Free, con un script de arranque (`deploy/oracle/bootstrap.sh`) y el despliegue automático desde GitHub.

`docker-compose.prod.yml` levanta toda la plataforma en un solo servidor:

- Caddy, con HTTPS automático de Let's Encrypt y HTTP/3.
- `web`, `horizon`, `scheduler` y `reverb`, todos con la misma imagen.
- PostgreSQL 17, Redis con contraseña y un servicio de backups.

Todos los contenedores llevan reinicio automático, límites de CPU y memoria, logs a stdout con rotación (10 MB × 5 por contenedor) y un volumen persistente para los ficheros de usuario. Los workers solo arrancan cuando `web` está sano, es decir, con las migraciones ya aplicadas.

Requisitos: un servidor con Docker 24 o superior, los puertos 80 y 443 abiertos, y el dominio apuntando a su IP.

```sh
# En el servidor (una vez)
git clone https://github.com/dmarsal90/matchly.git /opt/matchly && cd /opt/matchly
cp deploy/env.production.example .env && chmod 600 .env
# Rellena APP_KEY, APP_DOMAIN, ACME_EMAIL, contraseñas, claves de Reverb y correo
echo "$GHCR_TOKEN" | docker login ghcr.io -u <usuario> --password-stdin   # si el paquete es privado
APP_TAG=latest sh deploy/vps/deploy.sh
docker compose -f docker-compose.prod.yml exec web php artisan users:admin tu@correo.com --verify   # tras registrarte; --verify si aún no hay correo
```

`deploy/vps/deploy.sh` hace estos pasos:

1. Descarga la etiqueta. Si no existe, no toca nada.
2. Saca un backup.
3. Recrea `web`, que migra, espera a que esté sano y luego recrea el resto.
4. Guarda en `.previous-tag` la etiqueta anterior.

Para el rollback, usa `sh deploy/vps/deploy.sh --rollback` o lánzalo desde GitHub Actions con la etiqueta anterior. El corte dura lo que tarda en arrancar `web` (unos segundos). Si necesitas cero cortes, pon dos réplicas de `web` detrás de Caddy o usa una PaaS con despliegue *rolling*.

**Escalar.** Horizon reparte los procesos entre las colas interactivas y las de fondo según `HORIZON_INTERACTIVE_PROCESSES` y `HORIZON_BACKGROUND_PROCESSES`. Para más capacidad, sube esos valores y los límites del servicio `horizon`, o arranca otro servidor con solo `docker compose -f docker-compose.prod.yml up -d horizon` apuntando a la misma BD y al mismo Redis. El scheduler debe tener **una sola** instancia.

**BD y Redis gestionados.** Apunta `DB_HOST` y `REDIS_HOST` (o `DB_URL` y `REDIS_URL`) a ellos y levanta solo `caddy web horizon scheduler reverb`. Las dependencias de `postgres` y `redis` son opcionales (`required: false`).

### Laravel Forge (sin Docker)

1. Crea un servidor de aplicaciones con PHP 8.4, PostgreSQL y Redis, y un sitio con el repositorio.
2. *Deploy script*: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan optimize`, `php artisan horizon:terminate` y `php artisan reload`.
3. *Daemons*: `php artisan horizon` y `php artisan reverb:start --host=0.0.0.0 --port=8080`. Activa el *Scheduler* del servidor.
4. En Nginx, enruta `/app/` a `127.0.0.1:8080` con las cabeceras `Upgrade`. Pon `REVERB_PUBLIC_HOST` igual al dominio y el puerto 443.
5. Copia el *Deploy hook URL* en `DEPLOY_HOOK_URL`. Para el rollback, usa *Deployments → Redeploy* de un commit anterior.

## Laravel Cloud

Laravel Cloud construye desde el repositorio (no usa la imagen) y gestiona PostgreSQL, Redis (KV) y WebSockets.

1. Crea la aplicación desde GitHub y un entorno `staging` sobre `main`. Para producción, crea un entorno con la rama o la etiqueta que despliegues.
2. *Build commands*: `composer install --no-dev --optimize-autoloader`, `npm ci` y `npm run build`. *Deploy commands*: `php artisan migrate --force`.
3. Añade PostgreSQL y KV Store al entorno: inyectan `DB_*` y `REDIS_*`.
4. Activa el *Scheduler* y crea un *Queue cluster* (o *worker*) con `php artisan horizon`, o con `queue:work --queue=high,notifications,default,ingestion,ai,matching,maintenance`. Escálalo aparte desde su panel.
5. Activa *WebSockets* (Reverb gestionado) y copia sus valores en `REVERB_*` y `REVERB_PUBLIC_*`.
6. El resto de variables las tomas de `deploy/env.production.example`. Para usar el *deploy hook* desde la CD, copia su URL en `DEPLOY_HOOK_URL`. Para el rollback, usa *Deployments → Redeploy*.

## Railway

Crea un proyecto con PostgreSQL y Redis y añade cuatro servicios desde la imagen `ghcr.io/dmarsal90/matchly:latest`. Solo cambia el *Custom Start Command*: `web`, `horizon`, `scheduler` y `reverb`.

- **Variables:** en las variables compartidas del proyecto pon las de `deploy/env.production.example`, con `DB_URL=${{Postgres.DATABASE_URL}}` y `REDIS_URL=${{Redis.REDIS_URL}}`.
- **Web:** dale un dominio público, puerto 8080 y *Healthcheck path* `/up`.
- **Reverb:** dale un dominio público aparte (`REVERB_PUBLIC_HOST`, puerto 443, y añade `wss://<dominio>` a `SECURITY_CSP_CONNECT_SRC`). Para el acceso interno, `REVERB_HOST=reverb.railway.internal` y `REVERB_PORT=8080`.
- **Ficheros de usuario:** los volúmenes de Railway se montan en un solo servicio, así que usa `USER_FILES_DISK=s3`.
- **Despliegue y rollback:** la CD puede usar el *deploy hook* de cada servicio, o redeploy desde el panel. Para escalar, sube las réplicas del servicio `horizon`. Para el rollback, usa *Deployments → Redeploy* de una versión anterior.

## Render

`deploy/render.yaml` es un Blueprint con `web`, `horizon`, `scheduler` y `reverb` (todos desde la imagen de GHCR), PostgreSQL 17 y Key Value con `noeviction`, para que no se pierdan trabajos de la cola.

1. *New → Blueprint*, elige el repositorio y pon como ruta `deploy/render.yaml`.
2. Rellena las variables marcadas `sync: false`.
3. Copia el *Deploy Hook* de cada servicio a la CD. Para escalar, cambia el número de instancias de `matchly-horizon`. Para el rollback, haz *Rollback* a un despliegue anterior en cada servicio.

## Fly.io

`deploy/fly.toml` declara los grupos de procesos `app` (web), `worker` (Horizon), `scheduler` y `reverb`. Las migraciones van en el `release_command`. Reverb se publica en el puerto 6001.

```sh
fly launch --no-deploy --copy-config --config deploy/fly.toml
fly postgres create && fly postgres attach <cluster>     # o Supabase/Neon: DB_URL
fly redis create                                          # Upstash: REDIS_URL
fly secrets set APP_KEY=… REVERB_APP_ID=… REVERB_APP_KEY=… REVERB_APP_SECRET=… RESEND_API_KEY=…
fly deploy --config deploy/fly.toml --image ghcr.io/dmarsal90/matchly:sha-<commit>
fly scale count worker=2 --config deploy/fly.toml        # escalar solo los workers
```

Para la CD, usa `flyctl deploy --image …` en vez del *deploy hook*: añade un paso con `superfly/flyctl-actions` y el secreto `FLY_API_TOKEN`. Para el rollback, `fly deploy --image` con la etiqueta anterior.

## Demo mínima (un solo servicio)

Para enseñar la aplicación sin coste puede bastar un único servicio web:

```env
QUEUE_CONNECTION=sync        # los trabajos se ejecutan en la propia petición
BROADCAST_CONNECTION=log     # sin Reverb: la campana se actualiza al navegar
CACHE_STORE=database
SESSION_DRIVER=database
```

El scheduler lo dispara un cron externo cada minuto, ejecutando la imagen con `php artisan schedule:run`. Sirven un *Cron Job* de Render (`* * * * *`, mismo Blueprint), un *Cron schedule* de Railway o `fly machine run --schedule`.

**Limitaciones:** con `sync`, la ingesta y las llamadas a la IA alargan la petición o el cron que las lanza. Cuando haya usuarios reales, cambia a `QUEUE_CONNECTION=redis` y añade el servicio `horizon` (y `reverb` si quieres tiempo real).

## Backups, logs y salud

- **Backups:**
  - En el VPS, el servicio `backup` hace un `pg_dump` comprimido cada día a la hora `BACKUP_AT` (UTC). Guarda `BACKUP_KEEP_DAYS` días en `BACKUP_PATH` (`./backups`), con permisos solo para root. `deploy.sh` saca uno antes de cada despliegue, y `docker compose -f docker-compose.prod.yml exec backup sh /usr/local/bin/backup.sh now` saca uno al momento.
  - Copia esa carpeta fuera del servidor (restic, rclone o snapshots del proveedor): un backup en el mismo disco no protege de perderlo.
  - Para restaurar: `docker compose -f docker-compose.prod.yml exec -T backup gunzip -c /backups/<fichero>.sql.gz | docker compose -f docker-compose.prod.yml exec -T postgres psql -U "$DB_USERNAME" -d "$DB_DATABASE"`. Hazlo sobre una base vacía.
  - En una PaaS, activa los backups o el *point-in-time recovery* de la base gestionada.
- **Logs:** todo va a stdout/stderr (`LOG_CHANNEL=stderr`). Docker rota los ficheros en el VPS y las PaaS los recogen en su panel. Sin Docker, `LOG_CHANNEL=daily` con `LOG_DAILY_DAYS` rota los ficheros de `storage/logs`.
- **Salud:** `GET /up` responde 200 cuando la aplicación arranca. Lo usan el `HEALTHCHECK` de la imagen, Caddy, los balanceadores de las PaaS y el paso final de la CD.
- **Migraciones y rollback:** las migraciones solo añaden cosas. Volver a una imagen anterior deja el esquema nuevo, que esa versión sigue pudiendo usar. Si una versión necesita deshacer un cambio de esquema, ejecuta antes `php artisan migrate:rollback --step=N` con la imagen nueva.
