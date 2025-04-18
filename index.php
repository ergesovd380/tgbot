<?php
$token = '7880216260:AAEH_48sDqzvSz413sS-kIA9HRgcvxQBmUY';
$api = "https://api.telegram.org/bot$token/";
$dataFile = 'users.json'; // для хранения состояния пользователя

// Получаем входящее сообщение
$update = json_decode(file_get_contents('php://input'), true);
$chat_id = $update['message']['chat']['id'];
$text = $update['message']['text'] ?? '';

$users = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];

if (!isset($users[$chat_id]['step'])) {
    $users[$chat_id]['step'] = 0;
}

switch ($users[$chat_id]['step']) {
    case 0:
        sendMessage($chat_id, "Привет! Введите ваше ФИО:");
        $users[$chat_id]['step'] = 1;
        break;

    case 1:
        $users[$chat_id]['fio'] = $text;
        $users[$chat_id]['step'] = 2;
        sendMessage($chat_id, "Выберите категорию:\niPhone\niPad\nMacBook");
        break;

    case 2:
        $users[$chat_id]['category'] = $text;
        $category = $text;
        $options = [
            "iPhone" => ["12", "13", "14", "15", "16"],
            "iPad" => ["Air", "Pro", "Mini"],
            "MacBook" => ["Air", "Pro", "M1", "M2"]
        ];

        if (isset($options[$category])) {
            $msg = "Выберите модель:\n" . implode("\n", $options[$category]);
            $users[$chat_id]['step'] = 3;
            $users[$chat_id]['options'] = $options[$category];
            sendMessage($chat_id, $msg);
        } else {
            sendMessage($chat_id, "Неверная категория. Выберите из: iPhone, iPad, MacBook");
        }
        break;

    case 3:
        $users[$chat_id]['model'] = $text;
        sendMessage($chat_id, "Спасибо! Ваши данные сохранены. Вас скоро свяжет менеджер.");

        // Теперь можно отправить данные в Битрикс24
        sendToBitrix24($users[$chat_id]);

        $users[$chat_id]['step'] = 4;
        break;
}

file_put_contents($dataFile, json_encode($users));

function sendMessage($chat_id, $text) {
    global $api;
    file_get_contents($api . "sendMessage?chat_id=$chat_id&text=" . urlencode($text));
}

function sendToBitrix24($data) {
    $webhook = 'https://YOUR_BITRIX24_URL/rest/1/WEBHOOK_KEY/crm.deal.add.json';

    $dealData = [
        'fields' => [
            'TITLE' => 'Заявка из Telegram',
            'NAME' => $data['fio'],
            'COMMENTS' => "Категория: " . $data['category'] . "\nМодель: " . $data['model'],
            'STAGE_ID' => 'NEW'
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dealData));
    $response = curl_exec($ch);
    curl_close($ch);
}
