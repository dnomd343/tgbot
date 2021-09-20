<?php

class tgApi { // Telegram消息发送接口
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
        $url = 'https://api.telegram.org/bot' . $GLOBALS['env']['BOT_TOKEN'] . '/' . $payload['method'] . '?';
        foreach ($payload as $param => $content) {
            $url .= '&' . $param . '=' . urlencode($content);
        }
        return file_get_contents($url);
    }

    function debug() { // 调试接口
        global $tgEnv;
        $msg = '<i>---- tgEnv Content ----</i>' . PHP_EOL;
        $msg .= '<b>myId:</b> ' . $tgEnv['myInfo']['id'] . PHP_EOL;
        $msg .= '<b>myName:</b> ' . $tgEnv['myInfo']['name'] . PHP_EOL;
        $msg .= '<b>myAccount:</b> ' . $tgEnv['myInfo']['account'] . PHP_EOL;
        $msg .= '<b>isCallback:</b> ' . ($tgEnv['isCallback'] ? 'true' : 'false') . PHP_EOL;
        $msg .= '<b>isGroup:</b> ' . ($tgEnv['isGroup'] ? 'true' : 'false') . PHP_EOL;
        $msg .= '<b>messageText:</b> ' . $tgEnv['messageText'] . PHP_EOL;
        $msg .= '<b>messageId:</b> ' . $tgEnv['messageId'] . PHP_EOL;
        $msg .= '<b>chatId:</b> ' . $tgEnv['chatId'] . PHP_EOL;
        $msg .= '<b>userId:</b> ' . $tgEnv['userId'] . PHP_EOL;
        $msg .= '<b>userName:</b> ' . $tgEnv['userName'] . PHP_EOL;
        $msg .= '<b>userAccount:</b> ' . $tgEnv['userAccount'] . PHP_EOL;
        $msg .= '<b>demo:</b> ' . 'dnom<html>d343' . PHP_EOL;
        $msg .= '<b>userLanguage:</b> ' . $tgEnv['userLanguage'] . PHP_EOL;
        tgApi::sendMessage(array(
            'text' => $msg,
            'parse_mode' => 'HTML', // HTML格式输出
        ));
    }
}

?>
