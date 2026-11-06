<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Записываем в лог, что скрипт запустился
file_put_contents(__DIR__ . '/debug.txt', "--- Новый запрос: " . date('H:i:s') . " ---\n", FILE_APPEND);

$input = file_get_contents('php://input');
file_put_contents(__DIR__ . '/debug.txt', "Входящие данные: " . $input . "\n", FILE_APPEND);

$data = json_decode($input, true);

if (!$data) {
    file_put_contents(__DIR__ . '/debug.txt', "ОШИБКА: Не удалось прочитать JSON.\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Ошибка чтения данных.']);
    exit;
}

$dir = __DIR__ . '/admin/data';
$file = $dir . '/orders.json';

file_put_contents(__DIR__ . '/debug.txt', "Путь к папке: $dir\n", FILE_APPEND);

if (!is_dir($dir)) {
    file_put_contents(__DIR__ . '/debug.txt', "ОШИБКА: Папка не существует.\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Папка admin/data не найдена.']);
    exit;
}

if (!is_writable($dir)) {
    file_put_contents(__DIR__ . '/debug.txt', "ОШИБКА: Нет прав на запись в папку.\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Папка admin/data защищена от записи.']);
    exit;
}

$orders = [];
if (file_exists($file)) {
    $content = file_get_contents($file);
    $orders = json_decode($content, true);
    if (!is_array($orders)) $orders = [];
}

$newOrder = [
    'id' => uniqid(),
    'date' => date('d.m.Y H:i:s'),
    'name' => htmlspecialchars($data['name'] ?? ''),
    'phone' => htmlspecialchars($data['phone'] ?? ''),
    'address' => htmlspecialchars($data['address'] ?? ''),
    'status' => 'new'
];

array_unshift($orders, $newOrder);
$result = file_put_contents($file, json_encode($orders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

if ($result === false) {
    file_put_contents(__DIR__ . '/debug.txt', "ОШИБКА: Не удалось записать в orders.json.\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Ошибка записи файла.']);
} else {
    file_put_contents(__DIR__ . '/debug.txt', "УСПЕХ: Заказ сохранен.\n", FILE_APPEND);
    echo json_encode(['status' => 'success', 'message' => 'Заказ сохранен']);
}
?>