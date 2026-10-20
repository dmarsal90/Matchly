# Cómo contribuir

Gracias por querer mejorar Matchly. Al participar aceptas el [código de conducta](CODE_OF_CONDUCT.md).

## Puesta en marcha

```sh
make setup   # .env, imágenes, dependencias y stack en Docker
make check   # todo lo que ejecuta la CI: lint, Larastan, tipos y tests
```

Otros comandos útiles: `make test-php a="--filter=Nombre"`, `make test-pgsql` (PostgreSQL, como en la CI) y `make format` (Pint, ESLint y Prettier). `make help` lista el resto.

## Flujo de trabajo

1. Abre primero un issue si el cambio es grande, para ponernos de acuerdo.
2. Crea una rama desde `main` actualizado (`feat/…`, `fix/…`, `docs/…`, `chore/…`).
3. Haz commits pequeños con mensajes en el estilo del historial (`feat(scope): …`, `fix(scope): …`).
4. Abre el PR contra `main` con la plantilla rellena. `main` está protegido: solo se integra por PR y con la CI en verde.

## Qué espera la revisión

- Tests para lo que cambias. Un bug se arregla con un test que falle antes del arreglo.
- `make check` en verde; la CI exige Pint, Larastan, Pest (SQLite y PostgreSQL), frontend y Playwright.
- Textos de interfaz en los tres idiomas (`lang/es`, `lang/en`, `lang/pt`).
- Nada de secretos, claves ni datos personales en el código, los fixtures o las capturas.

## Añadir una fuente de ofertas

Cada fuente son dos clases y una entrada de configuración: sigue [docs/adding-a-job-provider.md](docs/adding-a-job-provider.md). Antes de proponerla, comprueba sus términos de uso y que su API devuelva ofertas reales.

## Seguridad

No abras issues públicos para vulnerabilidades: lee [SECURITY.md](SECURITY.md).
