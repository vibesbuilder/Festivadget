# Configurar el web push (fase 5)

**🇩🇪 [Deutsch](PUSH.md) · 🇬🇧 [English](PUSH.en.md) · 🇫🇷 [Français](PUSH.fr.md)**

El web push permite notificaciones en la pantalla de bloqueo, **incluso con la
app cerrada**: para avisos de seguridad, cambios de última hora y alertas de
«empieza en breve».

El backend es deliberadamente mínimo: unos pocos **archivos PHP** en la carpeta
[`push/`](../push/) del mismo **hosting** (no hace falta VPS). Requisitos:
**PHP 8.1+** con `openssl`, `mbstring` y `gmp` **o** `bcmath`, además de
**MySQL** y **cron**.

## Vista general

| Archivo | Función |
|---|---|
| `push/subscribe.php` | recibe suscripciones del navegador y las guarda en MySQL |
| `push/admin.php` | página protegida con contraseña para envío **inmediato** (+ botón de estadísticas) |
| `push/cron-send.php` | vía cron: resumen «actúa en la próxima hora» |
| `push/vapid-keys.php` | genera una vez el par de claves VAPID |
| `push/sender.php`, `db.php` | lógica común (envío, BD, esquema) |
| `push/weather.php`, `weather-providers.php` | endpoint del tiempo (GeoSphere/OpenWeather/WeatherAPI.com/MET Norway, caché en archivo; ajustes en la pestaña «Tiempo» del CMS) |
| `push/track.php`, `stats-db.php` | contador de uso anónimo (app → MySQL) |
| `push/stats.php` | página de estadísticas (misma contraseña que admin.php) |

> **Atajo de subida:** `deploy-data.bat push` sube todos los `push\*.php`,
> excepto `config.php`/`config.example.php`/`vapid-keys.php` (y sin `vendor\`).

La parte cliente (service worker + interruptor «Activar notificaciones» bajo
**Más**) ya está incluida en la app.

## Dos máquinas: ¿quién hace qué?

Intervienen **dos** lugares. Cada paso está marcado con su lugar:

- 💻 **PC local** (tu equipo Windows con Node/npm + el proyecto): construir la
  app, generar claves, preparar `config.php`, subirlo todo con
  `deploy-data.bat`/FTP.
- 🌐 **Hosting** (p. ej. World4You): aquí corren los **archivos PHP** +
  **MySQL** + **cron**. Los comandos allí se ejecutan **por SSH** (si tu plan
  lo incluye) **o** en el **panel del cliente** (cron, base de datos), o
  directamente nada: entonces lo haces todo en el PC y solo subes archivos.

> Regla básica: **todos los comandos `npm …` = 💻 PC.** **PHP/cron/BD = 🌐
> hosting.** Si tu hosting **no tiene SSH**, no necesitas allí **ninguna línea
> de comandos**: todo se hace en el PC y se sube por FTP (ver notas por paso).

## Paso a paso

### 1. 💻 Generar claves VAPID (en el PC, lo más fácil con Node)
En la carpeta del proyecto:
```bash
npx web-push generate-vapid-keys
```
Muestra la **clave pública** y la **clave privada**.
- **Clave pública** → en `config.php` (la app la obtiene en tiempo de ejecución vía `push/vapid.php`).
- **Clave privada** → solo en `config.php`. **Nunca commitear.**

*(Alternativa sin Node: `push/vapid-keys.php`, pero requiere PHP en el
servidor/SSH. Si se usa, borrar después el archivo del servidor.)*

### 2. 💻/🌐 Dependencia de Composer (`push/vendor/`)
El envío usa `minishlink/web-push` → la carpeta `push/vendor/` debe estar en el servidor.
- **Con SSH en el hosting:** allí `cd push && composer install`.
- **Sin SSH (caso normal):** ejecutar `composer install` en `push/` en el
  **PC** y subir la carpeta **`push/vendor/` por FTP**. (Composer en el PC:
  `getcomposer.org`.)

### 3. 🌐 Crear la base de datos MySQL
Crear una base de datos en el **panel del cliente** del hosting (anotar
nombre/usuario/contraseña). Las tablas se crean **automáticamente** en el
primer acceso (`push_subscriptions`, `push_log`).

### 4. 💻→🌐 Crear `push/config.php`
Copiar y rellenar en el **PC** y subir **por FTP** como `push/config.php`:
```bash
copy push\config.example.php push\config.php   :: Windows
```
Introducir: acceso a la BD (paso 3), `vapid.publicKey`/`privateKey` (paso 1),
`adminPasswordHash` (generar en el PC: `php -r "echo password_hash('TU_CONTRASENA', PASSWORD_DEFAULT);"`),
un `cronSecret` (cadena aleatoria). `config.php` está en `.gitignore`.

### 5. 💻 Desplegar la app
La clave pública ya no necesita entrar en el build: la app la obtiene en
tiempo de ejecución vía `push/vapid.php` (que lee `config.php`) y la recuerda
en `localStorage`. Basta con ejecutar **`deploy-data.bat full`**: el
interruptor «Notificaciones» bajo **Más** aparece en cuanto la clave es
accesible.
*(Alternativa opcional: definir `VITE_VAPID_PUBLIC_KEY` en el `.env` de la
app; entonces el interruptor está desde la primera carga, sin petición al
backend.)*

### 6. 🌐 Configurar el cron (solo para el resumen de inicio de conciertos)
En el **panel del cliente** del hosting → tareas cron, cada hora:
```
0 * * * *  php /ruta/a/push/cron-send.php
```
¿Solo es posible cron por HTTP? Apuntar un pinger externo (p. ej. cron-job.org)
a `https://app.rockimdorf.at/push/cron-send.php?key=<cronSecret>`.
*(Para las notificaciones `#push` de Telegram NO hace falta cron: salen al
instante.)*

**Frecuencia del cron y latencia de noticias:** los push automáticos de
noticias solo salen en la siguiente ejecución del cron; el intervalo determina
la **latencia**. Varias entradas cron (escalonadas), p. ej. cada 10–15 min, la
reducen en consecuencia. Pero entonces reduce también la **antelación del
resumen** (CMS → Ajustes → `upcomingWindowMin`) de forma acorde (p. ej.
15–20 min); si no, «En breve» anuncia artistas hasta 60 min antes de tiempo. No
pongas varios crons **en el mismo minuto** (en teoría, envío doble antes de que
actúe `push_log`); desplázalos unos minutos.

**Varias entradas cron en el mismo hosting:** si tu proveedor no permite la
misma ruta de archivo varias veces como cron, usa los wrappers incluidos
`push/cron-send-1.php` … `cron-send-5.php` (cada uno solo incluye
`cron-send.php`; la lógica queda en un único lugar). Así creas p. ej. 6
entradas cron escalonadas en `:00, :10, :20, :30, :40, :50` → push cada
~10 min.

> **No** retrasar con `sleep()`: los procesos PHP largos no son fiables en
> hosting compartido (timeouts de cron HTTP ~30 s, `max_execution_time`,
> workers bloqueados). Escalona con los **horarios del cron** (campo de
> minutos) o con un pinger externo (p. ej. cron-job.org) que llame a
> `cron-send.php?key=…` cada N minutos; entonces basta **un** archivo.

**Push inmediato (sin esperar al cron):** en la pestaña «Noticias» del CMS,
cada entrada tiene la casilla **«Enviar push de inmediato»**: al guardar, la
entrada (ya publicada) sale al instante (según categoría, una sola vez vía
`push_log`).

## Recordatorios de «Mi plan»

Los visitantes pueden activar **«Mi plan»** en el popover de notificaciones
(campana de la cabecera) y reciben entonces un recordatorio **antes del
comienzo** de sus artistas **favoritos**. Técnicamente:
- Los IDs de las actuaciones favoritas se guardan (solo IDs, anónimos) en la
  suscripción (`push_subscriptions.plan`) y se sincronizan automáticamente con
  el backend en cada cambio de favoritos.
- El cron (`cron-send.php`, bloque a2) envía **un push por artista favorito**
  dentro de la antelación (`upcomingWindowMin`), cada actuación por dispositivo
  **solo una vez** (dedup vía `push_log`, ref `plan:<hash>:<slotId>`).
- Los suscriptores de «Mi plan» **siguen** recibiendo el resumen general «En
  breve» (= cartel), pero **sin sus artistas favoritos**: esos llegan como
  recordatorio individual personal. Así ningún artista aparece dos veces.
  (Bloque a1 = resumen a suscripciones sin plan con cartel; bloque a2 =
  suscriptores con plan: push individuales para favoritos + resumen
  personalizado del resto de artistas, esto último solo si «Cartel» está
  suscrito. Dedup por dispositivo+actuación vía `push_log`.)

Para que «Mi plan» funcione, `autoPushUpcoming` debe estar activo y el cron en
marcha (ver arriba).

## Estadísticas de suscripciones (anónimas)

En cada ejecución (como mucho ~cada hora) el cron escribe una instantánea de
las **cifras de suscripciones** en la tabla `push_stats`: **solo contadores**,
sin datos personales: suscripciones totales y por categoría
(info/cartel/general; seguridad = todas las suscripciones). Visualización
(actual + historial) en la **pestaña «Push» del CMS**. Sin cron no se acumula
historial; las cifras actuales se ven igualmente en vivo.

## Pruebas

1. Abrir la app por **HTTPS** (el push requiere HTTPS; iOS solo como PWA instalada, iOS 16.4+).
2. En **Más → Notificaciones → Activar**, crear la suscripción (el navegador pide permiso).
3. Abrir `push/admin.php`, iniciar sesión, enviar un mensaje de prueba → aparece la notificación.
4. Probar el cron: `php push/cron-send.php` (CLI) o llamar a la URL con `?key=`; el informe JSON muestra `candidates`/`sent`.

## Categorías de push (quién recibe qué)

Los push automáticos de noticias (cron) se filtran **dos veces**:

1. El **admin** define qué categorías se envían automáticamente: en el CMS bajo
   **Ajustes → «Push automático: categorías»** (info / cartel / general).
   Guardado en `data/app-config.json` (`pushNewsCategories`), leído en vivo por
   el cron; el respaldo es `pushNewsCategories` de `config.php`. **Seguridad
   siempre está incluida.**
2. **Cada visitante** elige en la app, bajo **Notificaciones**, cuáles de esas
   categorías quiere recibir. La elección se guarda en la suscripción (columna
   `categories` de `push_subscriptions`; vacío = solo seguridad, NULL = todas
   para filas antiguas). **Seguridad llega siempre** y no se puede
   deseleccionar.

Una noticia solo se envía efectivamente si la categoría está **activa en el
admin** (o la entrada es `pinned`) **y** el visitante eligió esa categoría
(excepto seguridad: siempre). Los push manuales desde `push/admin.php` van
igualmente a **todas** las suscripciones.

> La fuente de noticias del push automático es `data/admin-news.json` (la
> pestaña «Noticias» del CMS); si no, el estado del build `news.json`.

## Seguridad

- `push/config.php` y `push/vendor/` están en `.gitignore` y además protegidos
  del acceso directo por `.htaccess`.
- La clave VAPID privada y la contraseña de administrador quedan solo en el servidor.
- `cron-send.php` está protegido de llamadas ajenas mediante `cronSecret`.

## Ampliaciones (opcional)

- **Enviar noticias programadas:** en `cron-send.php`, leer además `news.json`
  y enviar como push las entradas con `category="safety"`/`pinned` cuyo
  `publishAt` se haya alcanzado desde la última ejecución (idempotencia como en
  las actuaciones vía `push_log`, ref = `news:<id>`).
- **Recordatorios al minuto:** usar un cron externo de 1 minuto y estrechar la
  ventana en `cron-send.php` de 60 min a p. ej. 15 min.
