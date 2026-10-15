# Staging gratis en Oracle Cloud

Guía para montar Matchly completo (web, Horizon, scheduler, Reverb, PostgreSQL, Redis y backups) en una máquina virtual del plan **Always Free** de Oracle Cloud, con HTTPS y despliegue automático desde GitHub.

Al final tendrás `https://tu-nombre.duckdns.org`, y cada merge a `main` actualizará el servidor.

> Los menús de Oracle cambian con frecuencia. Si algún nombre no coincide, busca el equivalente. Los límites del plan gratuito también han cambiado (ver [Riesgos](#riesgos)), así que comprueba siempre la página oficial de [Always Free Resources](https://www.oracle.com/cloud/free/).

## Antes de empezar

- Esta guía necesita que **ya esté publicada la imagen para ARM**. Las máquinas gratuitas de Oracle son ARM (Ampere), y el workflow `Deploy` publica `amd64` y `arm64` desde el PR que añadió esta guía. Comprueba que el último despliegue de `main` ha terminado en verde: *Actions → Deploy*.
- Necesitas una tarjeta para verificar la cuenta de Oracle (lo habitual es una retención temporal, no un cobro; comprueba sus condiciones al registrarte) y un cliente SSH.

## 1. Cuenta de Oracle Cloud

1. Regístrate en [oracle.com/cloud/free](https://www.oracle.com/cloud/free/).
2. Elige con cuidado la **región de origen** (*home region*): no se puede cambiar después y los recursos Always Free solo se crean ahí. Una cercana a tus usuarios, como Madrid o Frankfurt, va bien.
3. Después del registro, **no actives el paso a «Pay As You Go»** salvo que sepas lo que haces: con una cuenta de pago, lo que sobrepase los límites gratuitos sí se cobra.

## 2. Clave SSH

En tu ordenador, una para entrar tú:

```sh
ssh-keygen -t ed25519 -f ~/.ssh/matchly-oracle -C "matchly-oracle" -N ""
```

La pública es `~/.ssh/matchly-oracle.pub`.

## 3. Crear la máquina

*Menú ☰ → Compute → Instances → Create instance*:

| Campo | Valor |
| --- | --- |
| Name | `matchly-staging` |
| Image | **Canonical Ubuntu 24.04** (la versión `aarch64`) |
| Shape | *Ampere* → **VM.Standard.A1.Flex**, **2 OCPU y 12 GB** de memoria |
| Networking | VCN y subred públicas por defecto, con **IPv4 pública asignada** |
| SSH keys | *Paste public keys*: pega el contenido de `matchly-oracle.pub` |
| Boot volume | 50 GB bastan |

Quédate en 2 OCPU y 12 GB en total: es lo que informan varias fuentes como límite actual del plan gratuito. Aunque la consola te deje poner más, podrían apagarte la máquina.

**«Out of capacity».** Es habitual en la región más popular. Prueba otro *availability domain* en el mismo formulario, o repite el intento pasadas unas horas.

Anota la **IP pública** cuando la máquina esté en *Running*. No borres la instancia: perderías la IP.

## 4. Abrir los puertos en la red de Oracle

Hay dos cortafuegos y los dos tienen que dejar pasar el tráfico. El del servidor lo abre el script; el de la red se hace en la consola:

*Networking → Virtual cloud networks → tu VCN → Subnets → la subred pública → Security Lists → Default → Add Ingress Rules*:

| Source CIDR | Protocolo | Puerto destino |
| --- | --- | --- |
| `0.0.0.0/0` | TCP | `80` |
| `0.0.0.0/0` | TCP | `443` |
| `0.0.0.0/0` | UDP | `443` |

El 22 (SSH) ya está abierto. Si quieres, limítalo a tu IP.

## 5. Un dominio gratis

Let's Encrypt necesita un nombre, no una IP.

1. Entra en [duckdns.org](https://www.duckdns.org/) con tu cuenta de GitHub o Google.
2. Crea un subdominio, por ejemplo `matchly-staging`, y pon como *current ip* la IP pública de la máquina.
3. Tu dominio es `matchly-staging.duckdns.org`.

Comprueba que resuelve: `getent hosts matchly-staging.duckdns.org`.

## 6. Instalar

```sh
ssh -i ~/.ssh/matchly-oracle ubuntu@<IP pública>

curl -fsSL https://raw.githubusercontent.com/dmarsal90/matchly/main/deploy/oracle/bootstrap.sh -o bootstrap.sh
APP_DOMAIN=matchly-staging.duckdns.org ACME_EMAIL=tu@correo.com sh bootstrap.sh
```

Tarda unos minutos. El script:

1. Comprueba que el dominio apunta a esta máquina, para no agotar los límites de Let's Encrypt con intentos fallidos.
2. Crea 2 GB de swap.
3. Instala Docker.
4. Abre los puertos 80 y 443 en el cortafuegos del servidor, y los deja guardados.
5. Clona el repositorio en `/opt/matchly`.
6. Genera `/opt/matchly/.env` con claves aleatorias: `APP_KEY`, contraseñas de PostgreSQL y Redis, y claves de Reverb. Es un fichero solo para ti (permisos 600), y **nunca lo sobrescribe** si ya existe.
7. Descarga la imagen y arranca todo.

Variables opcionales, antes de `sh bootstrap.sh`:

| Variable | Para qué |
| --- | --- |
| `RESEND_API_KEY` y `MAIL_FROM_ADDRESS` | Enviar correos de verdad. Sin ella, los correos se escriben en el log (la verificación de correo y las alertas por correo no llegarán a nadie) |
| `ANTHROPIC_API_KEY` | Activar la IA. Sin ella queda desactivada y la app usa las reglas |
| `HORIZON_ADMIN_EMAILS` | Quién puede entrar en `/horizon` (por defecto, `ACME_EMAIL`) |

Al terminar, abre `https://matchly-staging.duckdns.org`, regístrate y hazte administrador. La opción `--verify` marca tu correo como verificado, para cuando todavía no hay proveedor de correo:

```sh
cd /opt/matchly
docker compose -f docker-compose.prod.yml exec web php artisan users:admin tu@correo.com --verify
```

El panel de administración está en `/admin`, y Horizon en `/horizon`.

**Si el certificado no se emite**, mira `docker compose -f docker-compose.prod.yml logs caddy`. Casi siempre es que falta abrir un puerto en el paso 4, o que el dominio todavía no apunta a la IP.

## 7. Despliegue automático desde GitHub

Cada merge a `main` publica la imagen y la despliega por SSH. Usa una **clave solo para esto**, distinta de la tuya.

En tu ordenador:

```sh
ssh-keygen -t ed25519 -f ~/.ssh/matchly-deploy -C "github-actions" -N ""
ssh-copy-id -i ~/.ssh/matchly-deploy.pub -o IdentityFile=~/.ssh/matchly-oracle ubuntu@<IP pública>
ssh-keyscan -t ed25519 <IP pública>      # lo usarás en DEPLOY_SSH_KNOWN_HOSTS (con el mismo host que DEPLOY_SSH_HOST)
```

En GitHub: *Settings → Environments → New environment → `staging`*:

| Tipo | Nombre | Valor |
| --- | --- | --- |
| Variable | `DEPLOY_SSH_HOST` | La IP pública (o el dominio) |
| Variable | `DEPLOY_SSH_USER` | `ubuntu` |
| Variable | `DEPLOY_PATH` | `/opt/matchly` |
| Variable | `DEPLOY_SSH_KNOWN_HOSTS` | La salida de `ssh-keyscan` |
| Variable | `APP_URL` | `https://matchly-staging.duckdns.org` |
| Secret | `DEPLOY_SSH_KEY` | El contenido de `~/.ssh/matchly-deploy` (la privada) |

Prueba: *Actions → Deploy → Run workflow* con el entorno `staging` y la etiqueta `latest`. Debe terminar con *Healthy*.

Para producción, cuando la quieras, crea otro entorno `production` igual (con aprobación obligatoria) y otro servidor.

## Día a día

```sh
cd /opt/matchly
docker compose -f docker-compose.prod.yml ps                  # estado
docker compose -f docker-compose.prod.yml logs -f web         # logs (también horizon, caddy, reverb…)
sh deploy/vps/deploy.sh --rollback                            # volver a la versión anterior
APP_TAG=sha-<commit> sh deploy/vps/deploy.sh                  # desplegar una versión concreta
```

**Backups.** Se hace uno cada día a las 03:30 UTC y otro antes de cada despliegue; quedan en `/opt/matchly/backups` (7 días). Contienen datos personales y solo los lee root, así que descárgalos con `sudo`. Hazlo de vez en cuando: si pierdes la máquina, pierdes los backups con ella.

```sh
ssh -i ~/.ssh/matchly-oracle ubuntu@<IP> 'sudo tar -C /opt/matchly -cz backups' > backups-matchly.tgz
```

Para restaurarlos, mira [docs/deployment.md](deployment.md#backups-logs-y-salud).

## Riesgos

- **El plan gratuito cambia.** Varias fuentes informan de que [Oracle recortó el plan Always Free de Ampere de 4 CPU y 24 GB a 2 CPU y 12 GB](https://infoq.com/news/2026/07/oracle-cloud-free-tier-limits/), con fechas y alcance que no coinciden entre sí, y de instancias apagadas sin aviso. Por eso esta guía usa 2 CPU y 12 GB.
- **Máquinas inactivas.** Por lo que sé de la política de Oracle, pueden recuperar instancias Always Free con muy poco uso durante varios días. Un staging casi parado podría entrar en esa categoría, así que no lo trates como el único sitio donde viven datos importantes. Compruébalo en la documentación de Oracle.
- **Un solo servidor.** Cada despliegue recrea `web` y hay un corte de unos segundos. Para staging es aceptable. Para producción real, mejor un servidor de pago o una PaaS.
- **Sin alertas.** Nadie te avisa si se cae. Un monitor gratuito (UptimeRobot, Better Stack) sobre `https://tu-dominio/up` lo resuelve.
