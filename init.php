<?php

function initBot($webhook) { // 初始化机器人
    $webhook = json_decode($webhook, true);
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
    return array(
        'myInfo' => getMyself($GLOBALS['env']['BOT_TOKEN']), // bot信息
        'isGroup' => ($message['chat']['type'] === 'group') ? true : false, // 是否为群组
        'isCallback' => $isCallback, // 是否为回调请求
        'messageText' => $messageText, // 请求/回调 文本内容
        'messageId' => $message['message_id'], // 请求/回调 消息ID
        'chatId' => $message['chat']['id'], // 会话ID
        'userId' => $messageFrom['id'], // 请求者用户ID
        'userName' => $messageFrom['first_name'], // 请求者名字
        'userAccount' => $messageFrom['username'], // 请求者用户名
        'userLanguage' => $messageFrom['language_code'] // 请求者语言
    );
}

function getMyself($token) { // 获取bot信息
    $redis = new RedisCache('me');
    $info = $redis->getData('info'); // 查询缓存数据
    if (!$info) { // 缓存未命中
        $url = 'https://api.telegram.org/bot' . $token . '/getMe'; // API查询
        $info = json_decode(file_get_contents($url), true)['result'];
        $info = array(
            'id' => $info['id'],
            'name' => $info['first_name'],
            'account' => $info['username']
        );
        $redis->setData('info', json_encode($info), 2 * 3600); // 缓存2小时
    } else { // 缓存命中
        $info = json_decode($info, true); // 使用缓存数据
    }
    return $info;
}

function loadEnv($filename) { // 读取环境变量文件
    $file = fopen($filename, 'r');
    $data = array();
    while (!feof($file)) { // 逐行读入文件
        $raw = trim(fgets($file));
        if ($raw == '') { continue; } // 跳过空行
        if (substr($raw, 0, 1) === '#') { continue; } // 跳过注释
        $record = explode('=', $raw);
        if (count($record) === 2) {
            $data[trim($record[0])] = trim($record[1]); // 合法记录
        }
    }
    fclose($file);
    return $data;
}

?>