<?php

require_once 'cmdRoute.php';
require_once 'redisCache.php';
require_once 'tgInterface.php';

$env = loadEnv();
$apiToken = $env['BOT_TOKEN'];
$botAccount = $env['BOT_NAME'];

$apiPath = 'https://api.telegram.org/bot' . $apiToken; // Telegram API接口
$webhook = json_decode(file_get_contents("php://input"), TRUE); // Webhook接受信息

$isCallback = isset($webhook['callback_query']) ? true : false;
if ($isCallback) {
    $message = $webhook['callback_query']['message'];
    $messageText = $webhook['callback_query']['data'];
    $messageFrom = $webhook['callback_query']['from'];
} else {
    $message = $webhook['message'];
    $messageText = $webhook['message']['text'];
    $messageFrom = $webhook['message']['from'];
}

$chat = $message['chat'];
$chatId = $chat['id'];
$messageId = $message['message_id'];
$isGroup = ($chat['type'] === 'group') ? true : false;
$userId = $messageFrom['id'];
$userName = $messageFrom['first_name'];
$userAccount = $messageFrom['username'];
$userLanguage = $messageFrom['language_code'];

foreach ($cmds as $cmd) {
    if (strpos($messageText, '/' . $cmd) === 0) { // 判断请求开头
        $rawParam = substr($messageText, strlen($cmd) + 1); // 获取请求参数
        if ($isGroup && strpos($rawParam, '@' . $botAccount) === 0) {
            $rawParam = substr($rawParam, strlen($botAccount) + 1); // 去除群组中的@
        }
        if (strlen($rawParam) != 0 && substr($rawParam, 0, 1) !== ' ') { break; } // 命令后必须带空格
        $rawParam = trim($rawParam); // 消除前后空格
        if ($isCallback) {
            routeCallback($cmd, $rawParam);
        } else {
            route($cmd, $rawParam);
        }
    }
}

function loadEnv() { // 载入环境变量
    $file = fopen('.env', 'r');
    $data = array();
    while (!feof($file)) { // 逐行读入文件
        $record = explode('=', trim(fgets($file)));
        if (count($record) === 2) { // 合法记录
            $data[$record[0]] = $record[1];
        }
    }
    fclose($file);
    return $data;
}

?>
