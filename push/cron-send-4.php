<?php
// Dünner Wrapper für einen weiteren Cron-Job-Eintrag. Viele Hoster lassen denselben
// Dateipfad nicht mehrfach als Cron eintragen – diese Datei ermöglicht einen
// zusätzlichen Eintrag, der EXAKT dieselbe Logik ausführt (Inhalt nur in cron-send.php).
// Mehrfachläufe sind dank push_log (Idempotenz) ungefährlich. Cron-Zeiten versetzen!
require __DIR__ . '/cron-send.php';
