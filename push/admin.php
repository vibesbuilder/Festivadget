<?php
// Geschützte Admin-Seite: Nachricht eintippen → sofort an alle Abos senden.
// Für Safety-Durchsagen und spontane Infos (unabhängig vom Cron-Intervall).

declare(strict_types=1);

require_once __DIR__ . '/db.php';

session_start();

$cfg    = push_config();
$report = null;
$error  = null;

// --- Login -------------------------------------------------------------
if (($_POST['do'] ?? '') === 'login') {
    require_once __DIR__ . '/log.php';
    $hash = (string) ($cfg['adminPasswordHash'] ?? '');
    if ($hash !== '' && password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        $_SESSION['push_admin'] = true;
        app_log('info', 'auth', 'Push-Admin-Login erfolgreich.');
    } else {
        app_log('warn', 'auth', 'Push-Admin-Login fehlgeschlagen (falsches Passwort).');
        $error = 'Falsches Passwort.';
    }
}

if (($_POST['do'] ?? '') === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$loggedIn = !empty($_SESSION['push_admin']);

// --- Senden ------------------------------------------------------------
if ($loggedIn && ($_POST['do'] ?? '') === 'send') {
    require_once __DIR__ . '/sender.php';
    $title = trim((string) ($_POST['title'] ?? ''));
    $bodyT = trim((string) ($_POST['body'] ?? ''));
    $url   = trim((string) ($_POST['url'] ?? '')) ?: '/';
    if ($title === '') {
        $error = 'Titel fehlt.';
    } else {
        try {
            $report = push_broadcast([
                'title' => $title,
                'body'  => $bodyT,
                'url'   => $url,
                'tag'   => 'admin',
            ]);
        } catch (Throwable $e) {
            $error = 'Versand fehlgeschlagen: ' . $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Festivadget · Push senden</title>
<style>
  body { font-family: system-ui, sans-serif; background:#121212; color:#fff; max-width:560px; margin:0 auto; padding:24px; }
  h1 { color:#f2c200; }
  label { display:block; margin:12px 0 4px; font-size:14px; color:#b3b3b3; }
  input, textarea { width:100%; box-sizing:border-box; padding:10px; border-radius:8px; border:1px solid #2e2e2e; background:#1c1c1c; color:#fff; }
  button { margin-top:16px; padding:10px 18px; border:0; border-radius:999px; background:#f2c200; color:#000; font-weight:700; cursor:pointer; }
  .muted { color:#b3b3b3; font-size:13px; }
  .ok { color:#4caf50; } .err { color:#e4572e; }
</style>
</head>
<body>
<h1>Festivadget · Push</h1>

<?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php if (!$loggedIn): ?>
  <form method="post">
    <input type="hidden" name="do" value="login">
    <label>Passwort</label>
    <input type="password" name="password" autofocus>
    <button type="submit">Anmelden</button>
  </form>
<?php else: ?>
  <?php if ($report): ?>
    <p class="ok">Gesendet: <?= (int) $report['sent'] ?> · entfernt (abgelaufen): <?= (int) $report['removed'] ?> · Abos gesamt: <?= (int) $report['total'] ?></p>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="do" value="send">
    <label>Titel</label>
    <input type="text" name="title" maxlength="120" required>
    <label>Text</label>
    <textarea name="body" rows="3" maxlength="300"></textarea>
    <label>Link (optional, Standard „/")</label>
    <input type="text" name="url" placeholder="/news">
    <button type="submit">An alle senden</button>
  </form>
  <p style="margin-top:24px">
    <a href="cms/?tab=stats" style="display:inline-block;padding:10px 18px;border-radius:999px;background:#1c1c1c;border:1px solid #2e2e2e;color:#f2c200;text-decoration:none;font-weight:700">📊 Statistik (CMS)</a>
  </p>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="do" value="logout">
    <button type="submit" style="background:#2e2e2e;color:#fff">Abmelden</button>
  </form>
<?php endif; ?>

<p class="muted">Hinweis: Versand erfolgt sofort an alle aktiven Abos.</p>
</body>
</html>
