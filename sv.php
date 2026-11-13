<?php
// SonoVault proxy — keeps the API key on the server and adds CORS headers.
// Upload next to the ingestion tool (e.g. /ingestion/sv.php).
$KEY = 'svk_live_1JkenkhNl9I4xnF56OrRBN25puKALgFv';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['https://kh-projects.online', 'https://www.kh-projects.online'], true)) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
}
header('Access-Control-Expose-Headers: X-SV-Proxy, Retry-After');
header('X-SV-Proxy: 1');
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$path = $_GET['path'] ?? '';
if (!preg_match('#^/v1/tracks/(search|isrc/[A-Za-z0-9]{8,15})$#', $path)) {
  http_response_code(400); echo '{"error":"bad path"}'; exit;
}
$qs = $_GET; unset($qs['path']);
$url = 'https://api.sonovault.now' . $path . ($qs ? '?' . http_build_query($qs) : '');

$retryAfter = null;
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 20,
  CURLOPT_HTTPHEADER => ['x-api-key: ' . $KEY, 'Accept: application/json'],
  CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$retryAfter) {
    if (stripos($h, 'retry-after:') === 0) $retryAfter = trim(substr($h, 12));
    return strlen($h);
  },
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($body === false) { http_response_code(502); echo '{"error":"upstream unreachable"}'; exit; }
if ($retryAfter !== null) header('Retry-After: ' . $retryAfter);
http_response_code($code);
echo $body;
