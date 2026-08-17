<?php
// Öffentlicher VAPID-Key zur Laufzeit. Der Client (src/lib/push.ts) holt den
// Key von hier statt aus der Build-Env, damit Kunden-Installationen ohne
// Build-Maschine auskommen (VITE_VAPID_PUBLIC_KEY bleibt optionaler Fallback).
// Der Public-Key ist kein Geheimnis – er steht ohnehin in jeder Subscription.

declare(strict_types=1);

require_once __DIR__ . '/db.php'; // push_config()

$key = trim((string) (push_config()['vapid']['publicKey'] ?? ''));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache'); // Key-Wechsel (selten) soll zeitnah greifen
echo json_encode(['publicKey' => $key], JSON_UNESCAPED_SLASHES);
