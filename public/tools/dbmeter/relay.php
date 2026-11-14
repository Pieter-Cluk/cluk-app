<?php
// dB-meter relay: de meter stuurt ~1x/sec zijn waarden, displays (via een deellink) halen ze op.
// Elke meting heeft een eigen, willekeurige code. Bij stoppen wordt de link afgesloten.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$room = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['room'] ?? ''));
if (strlen($room) < 4 || strlen($room) > 32) { http_response_code(400); echo '{"error":"room"}'; exit; }

$dir = __DIR__ . '/data';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$file = $dir . '/' . $room . '.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['end'])) {                       // meting gestopt: link afsluiten
        if (is_file($file)) { file_put_contents($file, '{"status":"ended"}', LOCK_EX); }
        echo '{"ok":true}'; exit;
    }
    $body = file_get_contents('php://input', false, null, 0, 65536);
    if ($body === false || strlen($body) > 60000 || json_decode($body) === null) {
        http_response_code(400); echo '{"error":"body"}'; exit;
    }
    file_put_contents($file, $body, LOCK_EX);
    if (mt_rand(1, 300) === 1) {                     // oude metingen (> 1 dag) opruimen
        foreach (glob($dir . '/*.json') as $f) { if (filemtime($f) < time() - 86400) { @unlink($f); } }
    }
    echo '{"ok":true}'; exit;
}

if (!is_file($file)) { http_response_code(404); echo '{"status":"unknown"}'; exit; }
$data = file_get_contents($file);
if (strpos($data, '"status":"ended"') !== false) { echo '{"status":"ended"}'; exit; }
clearstatcache();
if (time() - filemtime($file) > 20) { echo '{"status":"inactive"}'; exit; }   // meter niet meer actief
echo $data;
