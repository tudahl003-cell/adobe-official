<?php
// TEMP diagnostic: what does the Railway container's egress to the Jev API
// actually return? Deploy, fetch, read, then delete. Not part of the app.
require __DIR__ . '/../lib.php';

$state = [
    'vp' => '1920x1080', 'dpr' => 1.25, 'sw' => '1920x1080',
    'ua' => ua(), 'mm' => [[10,700,400],[120,720,410],[300,760,405],[800,820,500]],
    'kd' => [200,450,700], 'wh' => [[400,120]], 'cl' => [[900,'800,450']],
    'ml' => 3000, 'top' => 3400,
];
$payload = json_encode([
    'state' => $state, 'model' => 'jev-latest',
    'questions' => [
        'human' => ['type'=>'noul','instructions'=>"Is this a real human?"],
        'profile' => ['type'=>'choice','instructions'=>'Pick profile','criteria'=>['fast'=>'human','robust'=>'uncertain']],
    ],
], JSON_UNESCAPED_SLASHES);

// Attempt 1: the exact path the app uses (stream context, 4s).
$ctx = stream_context_create(['http' => [
    'method'=>'POST',
    'header'=>"Content-Type: application/json\r\nUser-Agent: adobe-landing/1.0\r\nAuthorization: Bearer " . JEV_KEY,
    'content'=>$payload, 'timeout'=>JEV_TIMEOUT, 'ignore_errors'=>true,
]]);
$t0 = microtime(true);
$body = @file_get_contents(JEV_URL, false, $ctx);
$elapsed = round((microtime(true)-$t0)*1000);
$out = ['app_path' => ['ok'=>$body!==false, 'elapsed_ms'=>$elapsed,
    'body'=> $body===false ? null : substr($body,0,300)]];

// Attempt 2: longer timeout, to see if it's a timing issue vs a hard block.
$ctx2 = stream_context_create(['http' => [
    'method'=>'POST',
    'header'=>"Content-Type: application/json\r\nAuthorization: Bearer " . JEV_KEY,
    'content'=>$payload, 'timeout'=>20, 'ignore_errors'=>true,
]]);
$t0 = microtime(true);
$body2 = @file_get_contents(JEV_URL, false, $ctx2);
$out['long20s'] = ['ok'=>$body2!==false, 'elapsed_ms'=>round((microtime(true)-$t0)*1000),
    'body'=> $body2===false ? null : substr($body2,0,300)];

// Attempt 3: plain DNS/HTTP reachability probe.
$t0 = microtime(true);
$probe = @file_get_contents('https://api.typesafe.ai/', false,
    stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true]]));
$out['plain_get'] = ['ok'=>$probe!==false, 'elapsed_ms'=>round((microtime(true)-$t0)*1000),
    'len'=>$probe===false?null:strlen($probe)];

$out['php_sockets'] = extension_loaded('sockets');
$out['php_ssl'] = (bool)in_array('ssl', stream_get_transports());
header('Content-Type: application/json');
echo json_encode($out);
