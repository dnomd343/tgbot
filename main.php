<?php

require_once 'cmdRoute.php';
require_once 'functions/Punycode.php';
require_once 'functions/SqliteDB.php';
require_once 'functions/RedisCache.php';
require_once 'functions/TgInterface.php';
require_once 'functions/ExtractDomain.php';

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
$tgEnv = array(
    'isCallback' => $isCallback,
    'isGroup' => ($chat['type'] === 'group') ? true : false,
    'messageText' => $messageText,
    'messageId' => $message['message_id'],
    'chatId' => $chat['id'],
    'userId' => $messageFrom['id'],
    'userName' => $messageFrom['first_name'],
    'userAccount' => $messageFrom['username'],
    'userLanguage' => $messageFrom['language_code']
);

foreach ($cmds as $cmd) {
    if (strpos($messageText, '/' . $cmd) === 0) { // 判断请求开头
        $rawParam = substr($messageText, strlen($cmd) + 1); // 获取请求参数
        if ($tgEnv['isGroup'] && strpos($rawParam, '@' . $botAccount) === 0) {
            $rawParam = substr($rawParam, strlen($botAccount) + 1); // 去除群组中的@
        }
        if (strlen($rawParam) != 0 && substr($rawParam, 0, 1) !== ' ') { break; } // 命令后必须带空格
        $rawParam = trim($rawParam); // 消除前后空格
        route($cmd, $rawParam); // 路由命令
    }
}

function loadEnv() { // 载入环境变量
    $file = fopen('.env', 'r');
    $data = array();
    while (!feof($file)) { // 逐行读入文件
        $raw = trim(fgets($file));
        if ($raw == '') { continue; } // 跳过空行
        if (substr($raw, 0, 1) === '#') { continue; } // 跳过注释
        $record = explode('=', $raw);
        if (count($record) === 2) { // 合法记录
            $data[$record[0]] = $record[1];
        }
    }
    fclose($file);
    return $data;
}

?>
