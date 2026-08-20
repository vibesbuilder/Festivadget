<?php
// Public VAPID key at runtime. The client (src/lib/push.ts) fetches the key
// from here instead of the build env so customer installations work without
// a build machine (VITE_VAPID_PUBLIC_KEY remains an optional fallback).
// The public key is no secret - it is in every subscription anyway.

declare(strict_types=1);

require_once __DIR__ . '/db.php'; // push_config()

$key = trim((string) (push_config()['vapid']['publicKey'] ?? ''));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache'); // Key-Wechsel (selten) soll zeitnah greifen
echo json_encode(['publicKey' => $key], JSON_UNESCAPED_SLASHES);
