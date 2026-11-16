# Despliegue de DANJER FITNESS en AWS (EC2 + Docker Compose)

Esta guía cubre la **capa 2 (aplicación)** de la arquitectura: cómo levantar el
proyecto en la EC2 `danjer-app`. Se asume que ya existen la VPC, los security
groups (`sg-alb`, `sg-ec2`, `sg-rds`), el rol `danjer-ec2-role`, el bucket S3 y
la base de datos RDS `danjer-db`.

## Archivos de Docker del proyecto

| Archivo | Qué hace |
|---|---|
| `Dockerfile` | Construye dos imágenes: `app` (PHP 8.2-FPM + Laravel 9) y `web` (nginx con `public/`). |
| `docker-compose.prod.yml` | Levanta los 5 servicios: `nginx`, `app`, `worker`, `scheduler` y `redis`. Envía los logs a CloudWatch. |
| `docker/nginx.conf` | nginx en el puerto 80, pasa las peticiones PHP a `app:9000`. |
| `docker/php.ini` | Límites de subida (10 MB), zona horaria de Lima y OPcache. |
| `.env.production.example` | Plantilla del `.env` para AWS (RDS, Redis, S3 con rol IAM). |

Además, para funcionar detrás del Application Load Balancer:

- `app/Http/Middleware/TrustProxies.php` confía en el ALB (`$proxies = '*'`), así
  Laravel sabe que el usuario entró por HTTPS y genera enlaces `https://`.
- `routes/api.php` tiene la ruta **`/api/health`**, que es la que usa el Target group.

## Requisitos de la EC2

Al lanzar la instancia (Ubuntu 24.04, t3.medium):

- **IAM instance profile:** `danjer-ec2-role` (con `AmazonSSMManagedInstanceCore`,
  `CloudWatchAgentServerPolicy` y la política inline del bucket S3).
- **Metadata response hop limit: 2.** Sin esto, los contenedores no pueden usar el rol.
- **User data:** el script que instala Docker y el plugin `docker compose`.

## 1. Entrar a la EC2

EC2 → `danjer-app` → **Connect** → pestaña **Session Manager** → **Connect**.

```bash
sudo su - ubuntu
```

## 2. Clonar el proyecto y crear el `.env`

```bash
git clone -b feat/docker-aws https://github.com/elpierZON/DanjerFitnes.git danjer
cd danjer
cp .env.production.example .env
nano .env
```

Completa como mínimo: `APP_URL`, `DB_HOST` (endpoint de RDS), `DB_PASSWORD` y los
datos de `MAIL_*`. La línea `COMPOSE_FILE=docker-compose.prod.yml` hace que todos
los comandos `docker compose` usen el archivo de producción.

## 3. Construir e iniciar

```bash
docker compose build
docker compose run --rm app php artisan key:generate --show
```

Copia la clave que aparece (`base64:...`) en `APP_KEY=` del `.env` y luego:

```bash
docker compose run --rm app php artisan migrate --force
docker compose up -d
docker compose ps
```

Los 5 servicios deben aparecer en estado `running`.

> Si tienen seeders con datos iniciales (planes, admin, etc.):
> `docker compose run --rm app php artisan db:seed --force`

## 4. Probar dentro de la EC2

```bash
curl -s localhost/api/health          # {"status":"ok"}
curl -sI localhost/ | head -n 1        # HTTP/1.1 200 OK
docker compose exec app php artisan migrate:status   # confirma la conexión con RDS
```

Si algo falla, los logs están en **CloudWatch → Log groups → `/danjer/laravel`** y
**`/danjer/nginx`**, o en la misma EC2 con `docker compose logs app`.

## 5. Target group y ALB

- Target group `danjer-tg`: HTTP 80, **health check path: `/api/health`**.
- Registrar la instancia `danjer-app` y esperar a que salga **healthy**.
- Crear el ALB con los listeners HTTPS 443 (certificado ACM) y HTTP 80 → redirect a 443.

## 5.1 HTTPS sin dominio: CloudFront delante del ALB

Como no hay dominio propio (requisito de ACM), el HTTPS lo da **Amazon CloudFront**
con su certificado gratuito `*.cloudfront.net`:

```
Usuario --HTTPS--> CloudFront (dxxxx.cloudfront.net) --HTTP--> ALB --> EC2 --> RDS
```

- Distribución con origen `danjer-alb-...elb.amazonaws.com` (HTTP 80),
  *Viewer protocol policy*: **Redirect HTTP to HTTPS**.
- *Cache policy*: **CachingDisabled** (la app es dinámica: sesiones, CSRF, Livewire).
- *Origin request policy*: **AllViewerAndCloudFrontHeaders-2022-06**, para enviar
  cookies, el `Host` y la cabecera `CloudFront-Forwarded-Proto`.
- `app/Http/Middleware/TrustProxies.php` lee `CloudFront-Forwarded-Proto` para que
  Laravel sepa que el visitante entró por HTTPS (el ALB reescribe
  `X-Forwarded-Proto` a `http`).
- El security group **`alb`** solo acepta el puerto 80 desde la *prefix list*
  administrada `com.amazonaws.global.cloudfront.origin-facing`: nadie puede saltarse
  CloudFront entrando directo al ALB.
- En el `.env`: `APP_URL=https://dxxxx.cloudfront.net` y `SESSION_SECURE_COOKIE=true`.

Cuando tengan dominio, se puede asociar a la misma distribución con un certificado
de ACM (en us-east-1) o volver al diseño original (certificado ACM en el ALB).

## 6. Publicar una nueva versión

```bash
cd ~/danjer
git pull
docker compose build
docker compose run --rm app php artisan migrate --force
docker compose up -d
```

## Amazon S3 (fotos y comprobantes)

El proyecto ya incluye el paquete `league/flysystem-aws-s3-v3` y el `.env` de
producción trae `FILESYSTEM_DISK=s3` con `AWS_BUCKET=danjer-prod-media`.
**No pongas `AWS_ACCESS_KEY_ID` ni `AWS_SECRET_ACCESS_KEY`**: Laravel usa
automáticamente el rol IAM de la EC2 (por eso el *hop limit* debe ser 2).

Cuando agreguen la subida de archivos, en el código:

```php
// Guardar un comprobante en el bucket privado
$ruta = $request->file("comprobante")->store("comprobantes");

// Enlace temporal de 10 minutos
$url = Storage::temporaryUrl($ruta, now()->addMinutes(10));
```

## Notas

- `composer.json` fija `config.platform.php = 8.2.0` para que las dependencias
  siempre sean compatibles con la imagen Docker (PHP 8.2), aunque en tu PC tengas
  otra versión de PHP.
- Probado localmente con Docker: build, migraciones contra MySQL 8, `/api/health`,
  páginas públicas, registro de usuario (Bcrypt), sesión en Redis, worker y
  scheduler en ejecución.
