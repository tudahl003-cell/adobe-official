<?php
// /decide.php — one Jev behavioral verdict per visit.
//
// The landing page posts the raw telemetry blob (mouse/keyboard/scroll
// events collected by include/telemetry.php). We attach the server-side
// view of the visitor (IP, UA, hints, page age since the token was minted)
// and make ONE Jev call:
//   human  : noul   -> probability this is a real human
//   profile: choice -> 'fast' (aggressive engine) | 'robust' (conservative)
// The verdict is folded into a fresh verdict-token and returned to the page,
// which continues the flow with it.
//
// Fallback: ANY failure (no token, oversized body, Jev down, timeout,
// malformed response, rate limit) returns profile '' + the ORIGINAL token —
// the legacy fixed-time gate then applies exactly as before.
require __DIR__ . '/../lib.php';

$tk  = (string)($_GET['tk'] ?? '');
$tok = check_token($tk);

$out = [
    'tk'      => $tk,          // verdict token, or original token on fallback
    'profile' => '',           // ''  -> legacy pacing (Jev unavailable)
    'flag'    => 0,            // 0 none | 1 gray | 2 low/bot
    'n'       => null,         // noul (0..1) or null
];
header('Content-Type: application/json');

if ($tok) {
    $raw   = (string)file_get_contents('php://input');
    $state = json_decode($raw, true);
    $ok = ($raw !== ''
        && strlen($raw) <= JEV_STATE_LIMIT
        && is_array($state)
        && is_array($state['mm'] ?? null));
    if ($ok && jev_rate_allows(ip())) {
        // Server-side facts the page could forge but we can't.
        $state['visitor'] = array_merge((array)($state['visitor'] ?? []), [
            'ip'        => ip(),
            'userAgent' => ua(),
            'platform'  => req_header('Sec-Ch-Ua-Platform'),
            'pageAgeMs' => (time() - (int)$tok['t']) * 1000,
        ]);
        $v = jev_call($state);
        if ($v !== null) {
            $vtok = issue_token(['n' => $v['n'], 'p' => $v['p'], 'f' => $v['f']], (int)$tok['t']);
            $out['tk']      = $vtok;
            $out['profile'] = $v['p'];
            $out['flag']    = $v['f'];
            $out['n']       = $v['n'];

            // One Telegram ping per visit for low-confidence visitors.
            if ($v['f'] === 2) {
                $key = ALERT_DIR . '/fl_' . tok_nonce($vtok) . '.fired';
                if (!@file_exists($key)) {
                    @touch($key);
                    try {
                        $uaShort = function_exists('mb_substr')
                            ? mb_substr(ua(), 0, 120)
                            : substr(ua(), 0, 120);
                        tg("\x{1F6A8} <b>Behavioral Gate: low-confidence visitor</b>"
                         . "\n\x{1F4C5} Time: " . date('Y-m-d H:i:s')
                         . "\n\x{1F4CD} IP: " . ip()
                         . "\n\x{1F4F1} Device: " . $uaShort
                         . "\n\x{1F916} human=" . number_format($v['n'], 2)
                         . " — friction applied, download flagged.");
                    } catch (\Throwable $e) { /* alert must never break the flow */ }
                }
            }
        }
    }
}
echo json_encode($out);
exit;
