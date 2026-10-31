<?php
error_reporting(0);
ini_set('display_errors', '0');

// Универсальный обработчик заявок для всех сайтов-спутников.
// Принимает JSON {phone, flat?, page?} и пересылает в общий Telegram-бот.
// Один и тот же файл можно класть в корень любого проекта без правок —
// источник (какой это сайт) передаётся полем "page" из формы на странице.

header('Content-Type: application/json; charset=utf-8');

// Разрешённые домены — добавляйте новые проекты сюда по мере запуска
$allowed_origins = [
    'https://peredvizhniki2-rbi.ru',
    'https://sinopskaya-lsr.ru',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// Токен и chat_id общего бота
$token   = '8081525354:AAEC6_d2mgJQuaVnjYe8QepU8sHF3KQt1Fk';
$chat_id = '5689491476';

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

$clean = function ($v, $len) {
    return mb_substr(trim(strip_tags((string)$v)), 0, $len);
};

$phone = isset($payload['phone']) ? $clean($payload['phone'], 40)  : '';
$flat  = isset($payload['flat'])  ? $clean($payload['flat'], 200)  : '';
$page  = isset($payload['page'])  ? $clean($payload['page'], 150) : '';

if ($phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'empty_fields']);
    exit;
}

$text = "🏠 Новая заявка\n\n"
      . "Телефон: {$phone}\n";

if ($page !== '') {
    $text .= "Проект: {$page}\n";
}
if ($flat !== '') {
    $text .= "\n📐 Интересует: {$flat}\n";
}

$text .= "\nДата: " . date('d.m.Y H:i');

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'no_curl', 'detail' => 'PHP cURL extension is not enabled on this server']);
    exit;
}

$ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'chat_id' => $chat_id,
        'text'    => $text,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'telegram_unreachable', 'detail' => $curlError]);
    exit;
}

$tgResult = json_decode($response, true);
if (!isset($tgResult['ok']) || $tgResult['ok'] !== true) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'telegram_rejected', 'http_code' => $httpCode, 'detail' => $response]);
    exit;
}

echo json_encode(['ok' => true]);
