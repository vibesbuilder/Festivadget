# Configurar noticias en vivo por Telegram

**🇩🇪 [Deutsch](TELEGRAM.de.md) · 🇬🇧 [English](TELEGRAM.md) · 🇫🇷 [Français](TELEGRAM.fr.md)**

Envía un mensaje a tu bot de Telegram → aparece **en ~2 minutos** en la app,
**sin desplegar**. Sin moderación, solo para **remitentes permitidos** (tú).

## Arquitectura (en breve)
- Las noticias en vivo acaban en **`data/live-news.json`** en el servidor,
  separadas del `news.json` editorial.
- La app lee **ambos** archivos y los mezcla en el feed de noticias.
- El build/deploy local (`deploy-data.bat`) **nunca** toca `live-news.json`
  (está en `.gitignore` y no se construye localmente). → sin sobrescrituras.

## Configuración

### 1. Crear el bot
En Telegram **@BotFather** → `/newbot` → anota el **token del bot**.

### 2. Averiguar tu ID de usuario
Escribe a **@userinfobot** → te dice tu `id` numérico (p. ej. `123456789`).

### 3. Rellenar `push/config.php`
```php
'telegram' => [
    'botToken'       => '123456:ABC...',          // de BotFather
    'webhookSecret'  => 'una-palabra-larga-aleatoria', // a tu elección
    'allowedUserIds' => [123456789, 987654321],   // varios, separados por comas
    'allowedChatIds' => [-1001234567890],         // opcional: grupo(s) permitido(s)
    'liveNewsFile'   => __DIR__ . '/../data/live-news.json',
    'tz'             => 'Europe/Vienna',
    'maxItems'       => 200,
],
```
- **Permitir a varias personas:** añade más IDs de usuario, separados por comas, en `allowedUserIds`.
- **Permitir un grupo** (cada mensaje del grupo se convierte en noticia): pon el **ID del grupo** en `allowedChatIds`.

### 4. Subir `push/telegram-hook.php`
Ya está en el repositorio bajo `push/`. Súbelo por FTP al servidor (junto con `push/`).

### 5. Registrar el webhook en Telegram (una sola vez)
Abrir en el navegador (insertar token + secreto):
```
https://api.telegram.org/bot<BOTTOKEN>/setWebhook?url=https://app.rockimdorf.at/push/telegram-hook.php&secret_token=<WEBHOOKSECRET>
```
Respuesta `{"ok":true,...}` = correcto. Desde ahora Telegram reenvía cada mensaje de inmediato al hook.

## Uso
Enviar un mensaje al bot:
- **Primera línea = título**, resto de líneas = texto.
- **Etiquetas** (se eliminan del texto) que controlan opciones:
  - `#safety` / `#info` / `#lineup` / `#general` → categoría (por defecto: general)
  - `#pin` → fijada (arriba del feed)
  - `#2h` / `#30m` → caduca automáticamente 2 horas / 30 minutos **después de la publicación**
  - `@HH:mm` → **publicación programada** hoy a las HH:mm (si la hora ya pasó → mañana). Sin `@` se publica de inmediato.
  - `#push` → enviar además como **web push** a la pantalla de bloqueo. **`#safety`** envía push **automáticamente** (incluso sin `#push`). Push solo con publicación **inmediata** (no con programadas `@HH:mm`) y solo si el web push está configurado (`docs/PUSH.es.md`). Controlable con `pushAutoCategories` en `config.php`.
- **Comandos:**
  - `/list` → muestra las noticias en vivo activas, numeradas (con hora de caducidad si existe).
  - `/del <n.º>` → **revoca** una noticia en vivo (número de `/list`).
  - `/clear` → borra **todas** las noticias en vivo.

**Ejemplo (inmediato)**
```
Atención: tormenta
Asegurad las tiendas y buscad refugio. #safety #pin #3h
```
→ noticia de seguridad fijada, desaparece a las 3 horas. El bot confirma «✅ Publicado: …».

**Ejemplo (programado)**
```
Pausa de soundcheck
Breve interrupción en el escenario principal. @18:00 #info #1h
```
→ aparece **a las 18:00** y desaparece **a las 19:00** (la caducidad cuenta
desde las 18:00). El bot confirma «⏰ Programado para 01.08. 18:00: …». El
mensaje llega de inmediato a `live-news.json`; la app lo muestra solo a partir
de las 18:00 gracias a `publishAt`.

## Averiguar IDs
- **Tu propio ID de usuario:** escribe a **@userinfobot** (o envía `/chatid` a tu propio bot; responde con ID de usuario y de chat).
- **ID de grupo:** mete el bot **en el grupo** y envía **`/chatid`** dentro del grupo; el bot responde con el `chat ID` (número negativo, p. ej. `-1001234567890`). Ponlo en `allowedChatIds`.
- **Importante para grupos:** para que el bot lea **todos** los mensajes del grupo (no solo comandos), en **@BotFather** → `/setprivacy` → elegir **Disable**. Si no, solo llegan los `/comandos`.
- ⚠️ Un grupo permitido significa: **cualquier miembro** puede enviar noticias a todos los visitantes.

## Permisos de escritura (no hace falta FTP)
Las noticias en vivo **no necesitan FTP**: el hook PHP escribe directamente en
`data/live-news.json` en el mismo servidor. Requisito: la **carpeta `data/` es
escribible para PHP** (lo habitual en hosting compartido). Si no: dar permisos
de escritura a `data/`.

## Notas
- `live-news.json` lo crea el servidor con la primera entrada; antes de eso la
  app simplemente no sirve noticias en vivo (sin error).
- Se entrega con caché corta (`max-age=120`) y la app consulta cada 2 minutos →
  actualizaciones visibles en ≤ 2 min.
- **Seguridad:** sin un `webhookSecret` válido (encabezado de Telegram) y sin
  `allowedUserIds` permitidos, **no se procesa nada**. Mantén en secreto
  `botToken`/`webhookSecret`.
- Ampliación posible: enviar además las noticias en vivo aprobadas como
  **push** (la infraestructura existe, ver `push/sender.php`).
