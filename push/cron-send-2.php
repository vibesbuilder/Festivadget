<?php
// Thin wrapper for an additional cron job entry. Many hosters do not allow the
// same file path as multiple cron entries - this file enables an additional
// entry executing EXACTLY the same logic (content only in cron-send.php).
// Multiple runs are harmless thanks to push_log (idempotency). Stagger the cron times!
require __DIR__ . '/cron-send.php';
