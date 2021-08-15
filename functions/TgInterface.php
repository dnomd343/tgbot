<?php

class tgApi {
    public function sendText($msg, $chatId = 0) { // 发送纯文本
        return tgApi::sendMessage(['text' => $msg], $chatId);
    }

    public function sendMarkdown($msg, $chatId = 0) { // 发送Markdown格式消息
        return tgApi::sendMessage(array(
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ), $chatId);
    }

    public function sendMessage($params, $chatId = 0) { // 发送消息
        return tgApi::sendByMethod('sendMessage', $params, $chatId);
    }

    public function editMessage($params, $chatId = 0) { // 修改消息
        return tgApi::sendByMethod('editMessageText', $params, $chatId);
    }

    public function deleteMessage($params, $chatId = 0) { // 删除消息
        return tgApi::sendByMethod('deleteMessage', $params, $chatId);
    }

    public function sendDocument($params, $chatId = 0) { // 发送文件
        return tgApi::sendByMethod('sendDocument', $params, $chatId);
    }

    public function sendByMethod($method, $params, $chatId = 0) { // 按指定方式发送数据
        if ($chatId === 0) { // 未指定chatId
            global $tgEnv;
            $chatId = $tgEnv['chatId'];
        }
        $params += array (
            'method' => $method,
            'chat_id' => $chatId
        );
        return tgApi::sendPayload($params);
    }

    public function sendPayload($payload) { // 发送原始数据
        global $apiPath;
        $url = $apiPath . '/' . $payload['method'] . '?';
        foreach ($payload as $param => $content) {
            $url .= '&' . $param . '=' . urlencode($content);
        }
        return file_get_contents($url);
    }

    function debugDump() { // 调试接口
        global $webhook, $tgEnv;
        $msg .= 'isCallback: ' . ($tgEnv['isCallback'] ? 'true' : 'false') . PHP_EOL;
        $msg .= 'isGroup: ' . ($tgEnv['isGroup'] ? 'true' : 'false') . PHP_EOL;
        $msg .= 'messageText: ' . $tgEnv['messageText'] . PHP_EOL;
        $msg .= 'messageId: ' . $tgEnv['messageId'] . PHP_EOL;
        $msg .= 'chatId: ' . $tgEnv['chatId'] . PHP_EOL;
        $msg .= 'userId: ' . $tgEnv['userId'] . PHP_EOL;
        $msg .= 'userName: ' . $tgEnv['userName'] . PHP_EOL;
        $msg .= 'userAccount: ' . $tgEnv['userAccount'] . PHP_EOL;
        $msg .= 'userLanguage: ' . $tgEnv['userLanguage'] . PHP_EOL;
        tgApi::sendText($msg);
        tgApi::sendText(json_encode($webhook));
    }
}

?>
