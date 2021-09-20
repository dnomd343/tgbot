<?php

require_once 'init.php';
require_once 'route.php';
require_once 'functions/DNS.php';
require_once 'functions/Curl.php';
require_once 'functions/Domain.php';
require_once 'functions/DNSSEC.php';
require_once 'functions/TgReply.php';
require_once 'functions/Punycode.php';
require_once 'functions/SqliteDB.php';
require_once 'functions/RedisCache.php';
require_once 'functions/TgInterface.php';

$env = loadEnv('.env'); // 载入环境变量
fastcgi_finish_request(); // 断开连接
ini_set('date.timezone', $env['TIME_ZONE']); // 设置时区
$tgEnv = initBot(file_get_contents("php://input")); // 初始化bot配置
tgApi::debug();
route($tgEnv['messageText']); // 发往请求路由

?>
