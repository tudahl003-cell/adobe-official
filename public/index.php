<?php
/**
 * RMM Server — PHP port of rmm-server.ps1
 * Same API contract, JSON-file storage, no external deps.
 */

declare(strict_types=1);

header_remove('X-Powered-By');

// Deterministic access keys. These gate the dashboard (admin) and agent
// enrollment. They live in source because this is a single-operator RMM on a
// private repo; override at runtime with ADMIN_KEY / ENROLL_KEY env vars to
// rotate without a redeploy.
define('ADMIN_KEY_DEFAULT',  'aT9cL2mP8bN5qH3rV7jW4xZ6');
define('ENROLL_KEY_DEFAULT', 'k3Xn8pQ2vR7tWz5mYh9dFg4s');

// ---------------------------------------------------------------- storage
$DATA_DIR   = getenv('DATA_DIR') ?: __DIR__ . '/../data';
$UPLOAD_DIR = getenv('UPLOAD_DIR') ?: __DIR__ . '/../uploads';
$CONFIG     = $DATA_DIR . '/config.json';
$AGENTS     = $DATA_DIR . '/agents.json';
$JOBS       = $DATA_DIR . '/jobs.json';

@mkdir($DATA_DIR, 0777, true);
@mkdir($UPLOAD_DIR, 0777, true);

// ------------------------------------------------------------------ config
function new_key(): string {
    return bin2hex(random_bytes(16));
}

function load_config(): array {
    global $CONFIG;
    $envAdmin  = getenv('ADMIN_KEY');
    $envEnroll = getenv('ENROLL_KEY');
    $cfg = [
        'adminKey'      => ($envAdmin !== '' && $envAdmin !== null) ? $envAdmin : ADMIN_KEY_DEFAULT,
        'enrollmentKey' => ($envEnroll !== '' && $envEnroll !== null) ? $envEnroll : ENROLL_KEY_DEFAULT,
    ];
    // Write config.json (informational / for future non-key state).
    if (!file_exists($CONFIG)) {
        file_put_contents($CONFIG, json_encode($cfg, JSON_PRETTY_PRINT));
        @chmod($CONFIG, 0600);
    }
    return $cfg;
}

function load_json(string $path): array {
    if (!file_exists($path)) return [];
    $d = json_decode(file_get_contents($path), true);
    return is_array($d) ? $d : [];
}

function save_json(string $path, array $data): void {
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode(array_values($data), JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $path);
}

// ------------------------------------------------------------------ helpers
function send(int $status, $body, string $ct = 'application/json'): void {
    http_response_code($status);
    header('Content-Type: ' . $ct);
    if (is_array($body)) {
        echo json_encode($body);
    } else {
        echo $body;
    }
    exit;
}

function body_json(): ?array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

function admin_ok(array $cfg): bool {
    $k = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
    return $k !== '' && hash_equals((string)($cfg['adminKey'] ?? ''), $k);
}

function agent_token(): string {
    return $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
}

function authed_agent(string $agentId): ?array {
    foreach (load_json($GLOBALS['AGENTS']) as $a) {
        if (($a['agentId'] ?? '') === $agentId && hash_equals((string)($a['token'] ?? ''), agent_token())) {
            return $a;
        }
    }
    return null;
}

function find_agent(array $agents, string $id): ?array {
    foreach ($agents as $a) if (($a['agentId'] ?? '') === $id) return $a;
    return null;
}

$cfg   = load_config();
$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path  = rtrim($path, '/');
if ($path === '') $path = '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ------------------------------------------------------------------ router
if ($path === '/' && $method === 'GET') {
    $html = @file_get_contents(__DIR__ . '/dashboard.html');
    send(200, $html === false ? '<h1>RMM</h1>' : $html, 'text/html; charset=utf-8');
}

// POST /api/enroll
elseif ($path === '/api/enroll' && $method === 'POST') {
    $b = body_json();
    if (!$b || ($b['enrollmentKey'] ?? '') !== ($cfg['enrollmentKey'] ?? '')) {
        send(401, ['error' => 'invalid enrollment key']);
    }
    $agents = load_json($AGENTS);
    foreach ($agents as &$a) {
        if (($a['machineId'] ?? '') === ($b['machineId'] ?? '')) {
            $a['hostname'] = $b['hostname'] ?? '';
            $a['os']       = $b['os'] ?? '';
            $a['lastSeen'] = date('c');
            $a['removing'] = false;
            save_json($AGENTS, $agents);
            send(200, ['agentId' => $a['agentId'], 'token' => $a['token']]);
        }
    }
    unset($a);
    $agent = [
        'agentId'    => new_key(),
        'token'      => new_key(),
        'machineId'  => $b['machineId'] ?? '',
        'hostname'   => $b['hostname'] ?? '',
        'os'         => $b['os'] ?? '',
        'enrolledAt' => date('c'),
        'lastSeen'   => date('c'),
        'removing'   => false,
    ];
    $agents[] = $agent;
    save_json($AGENTS, $agents);
    send(200, ['agentId' => $agent['agentId'], 'token' => $agent['token']]);
}

// GET /api/agents
elseif ($path === '/api/agents' && $method === 'GET') {
    if (!admin_ok($cfg)) send(401, ['error' => 'unauthorized']);
    $out = [];
    foreach (load_json($AGENTS) as $a) {
        $out[] = [
            'agentId'    => $a['agentId'] ?? '',
            'hostname'   => $a['hostname'] ?? '',
            'os'         => $a['os'] ?? '',
            'enrolledAt' => $a['enrolledAt'] ?? '',
            'lastSeen'   => $a['lastSeen'] ?? '',
            'removing'   => (bool)($a['removing'] ?? false),
        ];
    }
    send(200, ['agents' => $out]);
}

// POST /api/agents/{id}/remove
elseif (preg_match('#^/api/agents/([^/]+)/remove$#', $path, $m) && $method === 'POST') {
    if (!admin_ok($cfg)) send(401, ['error' => 'unauthorized']);
    $agents = load_json($AGENTS);
    $found = false;
    foreach ($agents as &$a) {
        if (($a['agentId'] ?? '') === $m[1]) { $a['removing'] = true; $found = true; break; }
    }
    unset($a);
    if (!$found) send(404, ['error' => 'agent not found']);
    save_json($AGENTS, $agents);
    send(200, ['ok' => true, 'status' => 'removing']);
}

// POST /api/agents/{id}/removed
elseif (preg_match('#^/api/agents/([^/]+)/removed$#', $path, $m) && $method === 'POST') {
    if (!authed_agent($m[1])) send(401, ['error' => 'unauthorized']);
    remove_agent($m[1]);
    send(200, ['ok' => true]);
}

// DELETE /api/agents/{id}
elseif (preg_match('#^/api/agents/([^/]+)$#', $path, $m) && $method === 'DELETE') {
    if (!admin_ok($cfg)) send(401, ['error' => 'unauthorized']);
    if (!remove_agent($m[1])) send(404, ['error' => 'agent not found']);
    send(200, ['ok' => true]);
}

// GET /api/jobs
elseif ($path === '/api/jobs' && $method === 'GET') {
    if (!admin_ok($cfg)) send(401, ['error' => 'unauthorized']);
    send(200, ['jobs' => load_json($JOBS)]);
}

// POST /api/upload
elseif ($path === '/api/upload' && $method === 'POST') {
    if (!admin_ok($cfg)) send(401, ['error' => 'unauthorized']);
    $fileName = $_GET['fileName'] ?? '';
    if (trim((string)$fileName) === '') send(400, ['error' => 'fileName query param required']);
    $stored = bin2hex(random_bytes(16)) . '_' . basename((string)$fileName);
    $data = file_get_contents('php://input');
    if ($data === false || $data === '') send(400, ['error' => 'empty file']);
    file_put_contents($UPLOAD_DIR . '/' . $stored, $data);
    send(200, ['fileName' => $stored, 'originalName' => basename((string)$fileName)]);
}

// POST /api/deploy
elseif ($path === '/api/deploy' && $method === 'POST') {
    if (!admin_ok($cfg)) send(401, ['error' => 'unauthorized']);
    $b = body_json();
    if (!$b) send(400, ['error' => 'bad json']);
    $agentId = (string)($b['agentId'] ?? '');
    $stored  = (string)($b['fileName'] ?? '');
    $agent = find_agent(load_json($AGENTS), $agentId);
    if (!$agent) send(404, ['error' => 'agent not found']);
    if (!file_exists($UPLOAD_DIR . '/' . $stored)) send(400, ['error' => 'uploaded file not found']);
    $jobs = load_json($JOBS);
    $job = [
        'jobId'        => new_key(),
        'agentId'      => $agentId,
        'fileName'     => $stored,
        'originalName' => (string)($b['originalName'] ?? $stored),
        'arguments'    => (string)($b['arguments'] ?? ''),
        'status'       => 'pending',
        'createdAt'    => date('c'),
    ];
    $jobs[] = $job;
    save_json($JOBS, $jobs);
    send(200, ['jobId' => $job['jobId']]);
}

// POST /api/agents/{id}/heartbeat
elseif (preg_match('#^/api/agents/([^/]+)/heartbeat$#', $path, $m) && $method === 'POST') {
    $agents = load_json($AGENTS);
    $idx = null;
    foreach ($agents as $i => $a) {
        if (($a['agentId'] ?? '') === $m[1]) { $idx = $i; break; }
    }
    if ($idx === null || !hash_equals((string)($agents[$idx]['token'] ?? ''), agent_token())) {
        send(401, ['error' => 'unauthorized']);
    }
    $agents[$idx]['lastSeen'] = date('c');
    save_json($AGENTS, $agents);

    if (!empty($agents[$idx]['removing'])) {
        send(200, '{"jobs":[],"uninstall":true}');
    }

    $jobs = load_json($JOBS);
    $pending = [];
    $changed = false;
    foreach ($jobs as &$j) {
        if (($j['agentId'] ?? '') === $m[1] && ($j['status'] ?? '') === 'pending') {
            $j['status'] = 'dispatched';
            $changed = true;
            $pending[] = $j;
        }
    }
    unset($j);
    if ($changed) save_json($JOBS, $jobs);
    send(200, '{"jobs":' . json_encode($pending) . '}');
}

// GET /api/jobs/{id}/file
elseif (preg_match('#^/api/jobs/([^/]+)/file$#', $path, $m) && $method === 'GET') {
    $job = null;
    foreach (load_json($JOBS) as $j) if (($j['jobId'] ?? '') === $m[1]) { $job = $j; break; }
    if (!$job) send(404, ['error' => 'job not found']);
    if (!authed_agent((string)$job['agentId'])) send(401, ['error' => 'unauthorized']);
    $fp = $UPLOAD_DIR . '/' . $job['fileName'];
    if (!file_exists($fp)) send(404, ['error' => 'file missing']);
    http_response_code(200);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($fp));
    readfile($fp);
    exit;
}

// POST /api/jobs/{id}/result
elseif (preg_match('#^/api/jobs/([^/]+)/result$#', $path, $m) && $method === 'POST') {
    $b = body_json();
    $jobs = load_json($JOBS);
    $idx = null;
    foreach ($jobs as $i => $j) if (($j['jobId'] ?? '') === $m[1]) { $idx = $i; break; }
    if ($idx === null) send(404, ['error' => 'job not found']);
    if (!authed_agent((string)$jobs[$idx]['agentId'])) send(401, ['error' => 'unauthorized']);
    $jobs[$idx]['status']     = $b['status'] ?? 'unknown';
    $jobs[$idx]['exitCode']   = $b['exitCode'] ?? null;
    $jobs[$idx]['output']     = $b['output'] ?? '';
    $jobs[$idx]['finishedAt'] = date('c');
    save_json($JOBS, $jobs);
    send(200, ['ok' => true]);
}

else {
    send(404, ['error' => 'not found']);
}

// ------------------------------------------------------------------ helper
function remove_agent(string $agentId): bool {
    global $AGENTS, $JOBS;
    $agents = load_json($AGENTS);
    $kept = [];
    $removed = false;
    foreach ($agents as $a) {
        if (($a['agentId'] ?? '') === $agentId) { $removed = true; continue; }
        $kept[] = $a;
    }
    if ($removed) {
        save_json($AGENTS, $kept);
        $kj = [];
        foreach (load_json($JOBS) as $j) {
            if (($j['agentId'] ?? '') !== $agentId) $kj[] = $j;
        }
        save_json($JOBS, $kj);
    }
    return $removed;
}
