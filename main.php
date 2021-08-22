<?php

require_once 'env.php';
require_once 'route.php';
require_once 'functions/DNS.php';
require_once 'functions/Curl.php';
require_once 'functions/Domain.php';
require_once 'functions/DNSSEC.php';
require_once 'functions/Punycode.php';
require_once 'functions/SqliteDB.php';
require_once 'functions/RedisCache.php';
require_once 'functions/TgInterface.php';

fastcgi_finish_request(); // 断开连接
$env = loadEnv('.env'); // 载入环境变量
$apiToken = $env['BOT_TOKEN'];
$botAccount = $env['BOT_NAME']; // 机器人用户名
ini_set('date.timezone', $env['TIME_ZONE']); // 设置时区
$apiPath = 'https://api.telegram.org/bot' . $apiToken; // Telegram API接口
$webhook = json_decode(file_get_contents("php://input"), TRUE); // Webhook接受信息

$isCallback = isset($webhook['callback_query']) ? true : false; // 是否为回调请求
if ($isCallback) { // 回调请求模式
    $message = $webhook['callback_query']['message'];
    $messageText = $webhook['callback_query']['data'];
    $messageFrom = $webhook['callback_query']['from'];
} else { // 直接请求模式
    $message = $webhook['message'];
    $messageText = $webhook['message']['text'];
    $messageFrom = $webhook['message']['from'];
}
$chat = $message['chat'];

$tgEnv = array(
    'isGroup' => ($chat['type'] === 'group') ? true : false, // 是否为群组
    'isCallback' => $isCallback, // 是否为回调请求
    'messageText' => $messageText, // 请求/回调 文本内容
    'messageId' => $message['message_id'], // 请求/回调 消息ID
    'chatId' => $chat['id'], // 会话ID
    'userId' => $messageFrom['id'], // 请求者用户ID
    'userName' => $messageFrom['first_name'], // 请求者名字
    'userAccount' => $messageFrom['username'], // 请求者用户名
    'userLanguage' => $messageFrom['language_code'] // 请求者语言
);

route($messageText); // 发往请求路由

?>
