# Instalar Festivadget (sin máquina de build)

*Idiomas: [Deutsch](INSTALL.md) · [English](INSTALL.en.md) · [Français](INSTALL.fr.md)*

Festivadget se instala como Joomla/WordPress: **subir el paquete de release,
abrir el instalador en el navegador, listo.** No se necesita ninguna máquina
de build local (Node/pnpm), solo un hosting web.

## Requisitos

- Hosting con **PHP 8.1+** y acceso FTP (hosting compartido es suficiente).
- Opcional para **web push**: una base MySQL y una tarea cron.
- Opcional para el **branding del CMS** (iconos PWA): extensión PHP `gd`.

El instalador comprueba todos los requisitos y muestra lo que falta.

## Instalación

1. Descomprimir el paquete (`festivadget-vX.Y.Z.zip`) y subir su contenido
   por FTP a la **raíz web** del (sub)dominio. **Las subcarpetas (p. ej. `/testapp/`) no están soportadas** – el instalador lo comprueba (el build usa rutas absolutas); si hace falta, apunta un subdominio al directorio. Importante: **instalar de
   inmediato** – mientras no exista `push/config.php`, el instalador es
   accesible para cualquiera.
2. Abrir `https://tu-dominio/install/` en el navegador (DE/EN).
3. Rellenar el asistente:
   - **Contraseña de admin del CMS** (obligatoria) – para iniciar sesión en
     `/push/cms/`.
   - **Acceso MySQL** (opcional) – activa el web push; las claves VAPID se
     generan automáticamente. Dejar vacío para instalar sin push (se puede
     añadir después en `push/config.php`, ver [PUSH.es.md](PUSH.es.md)).
4. Tras el mensaje de éxito, **borrar la carpeta `install/`** (botón en la
   página final o por FTP).
5. Listo: app en `/`, CMS en `/push/cms/`. Contenidos, branding e imagen de
   fondo se gestionan por completo en el CMS (ver [ADMIN.es.md](ADMIN.es.md)).
   Con web push, añadir también la tarea cron ([PUSH.es.md](PUSH.es.md), paso 6).

## Actualizaciones

Las actualizaciones usan el **paquete de actualización** dedicado
`festivadget-update-vX.Y.Z.zip` (como la release, pero **sin `data/` y sin
`install/`**). El contenido del cliente queda intacto en ambas variantes:
`data/` (contenidos, subidas, branding), `push/config.php` y los ajustes de
CMS/meteo nunca se sobrescriben.

- **Cómodo (un clic):** en el CMS, abrir **Actualización** y subir el
  paquete; listo. El CMS valida el paquete (los paquetes de release
  completos se rechazan), aplica solo archivos no protegidos y muestra la
  versión instalada (archivo `VERSION`). Requiere la extensión PHP `zip`
  (o `phar`).
- **Mínimo (FTP):** descomprimir el paquete y copiarlo por FTP sobre la
  instalación (sobrescribir). Como `data/` e `install/` no están en el
  paquete, no hay nada más que vigilar.

## Construir el paquete uno mismo (mantenedor)

Ejecutar una vez `composer install` en `push/` (para `push/vendor/`), luego:

```bash
powershell -File tools/build-release.ps1
```

Construye la app de forma **neutral** (sin valores de instancia integrados) y
crea `release/festivadget-v<version>.zip` con el build de la app, `push/`
(sin secretos) e `install/`. Nota: `data/` del paquete corresponde al estado
de build de `public/data/`; usar datos de ejemplo para releases públicas.
