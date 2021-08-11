<?php

class tgApi {
    public function sendText($msg, $chatId = 0) { // 发送纯文本
        return tgApi::sendMessage(array(
            'text' => $msg
        ), $chatId);
    }

    public function sendMessage($params, $chatId = 0) { // 发送消息
        if ($chatId === 0) { // 未指定chatId
            global $tgEnv;
            $chatId = $tgEnv['chatId'];
        }
        $params += array (
            'method' => 'sendMessage',
            'chat_id' => $chatId
        );
        return tgApi::sendPayload($params);
    }

    public function editMessage($params, $chatId = 0) { // 修改消息
        if ($chatId === 0) { // 未指定chatId
            global $tgEnv;
            $chatId = $tgEnv['chatId'];
        }
        $params += array (
            'method' => 'editMessageText',
            'chat_id' => $chatId
        );
        return tgApi::sendPayload($params);
    }

    public function deleteMessage($params, $chatId = 0) { // 删除消息
        if ($chatId === 0) { // 未指定chatId
            global $tgEnv;
            $chatId = $tgEnv['chatId'];
        }
        $params += array (
            'method' => 'deleteMessage',
            'chat_id' => $chatId
        );
        return tgApi::sendPayload($params);
    }

    public function sendPayload($payload) { // 发送原始数据
        global $tgEnv;
        $url = $tgEnv['apiPath'] . '/' . $payload['method'] . '?';
        foreach ($payload as $param => $content) {
            $url .= '&' . $param . '=' . urlencode($content);
        }
        return file_get_contents($url);
    }
}

function sendPayload($payload) { // 发送API请求
    global $apiPath;
    $url = $apiPath . '/' . $payload['method'] . '?';
    foreach ($payload as $param => $content) {
        $url .= '&' . $param . '=' . urlencode($content);
    }
    return file_get_contents($url);
}

function sendMessage($chatId, $params) { // 发送消息
    $params += array (
        'method' => 'sendMessage',
        'chat_id' => $chatId
    );
    return sendPayload($params);
}

function sendDocument($chatId, $params) { // 发送文件
    $params += array (
        'method' => 'sendDocument',
        'chat_id' => $chatId
    );
    return sendPayload($params);
}

function sendText($chatId, $msg) { // 发送纯文本
    return sendMessage($chatId, array(
        'text' => $msg
    ));
}

function sendAuto($chatId, $content) { // 自动判别发送类型
    if (isset($content['document'])) { // 以文件类型发送
        sendDocument($chatId, $content);
    } else {
        sendMessage($chatId, $content);
    }
}

function debugDump() { // 调试接口
    global $webhook;
    global $isCallback, $isGroup;
    global $messageId, $chatId, $userId;
    global $messageText, $userName, $userAccount, $userLanguage;
    $msg .= 'isCallback: ' . ($isCallback ? 'true' : 'false') . PHP_EOL;
    $msg .= 'isGroup: ' . ($isGroup ? 'true' : 'false') . PHP_EOL;
    $msg .= 'messageText: ' . $messageText . PHP_EOL;
    $msg .= 'messageId: ' . $messageId . PHP_EOL;
    $msg .= 'chatId: ' . $chatId . PHP_EOL;
    $msg .= 'userId: ' . $userId . PHP_EOL;
    $msg .= 'userName: ' . $userName . PHP_EOL;
    $msg .= 'userAccount: ' . $userAccount . PHP_EOL;
    $msg .= 'userLanguage: ' . $userLanguage . PHP_EOL;
    sendText($chatId, $msg);
    sendText($chatId, json_encode($webhook));
}

?>
