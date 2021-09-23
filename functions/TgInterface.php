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

    public function md_encode($str) {
        return strtr($str, array(
            '_' => '\_',
            '*' => '\*',
            '[' => '\[',
            '`' => '\`',
        ));
    }

    function debug() { // 调试接口
        global $tgEnv;
        $msg = '_---- tgEnv Content ----_' . PHP_EOL;
        $msg .= '*myId:* ' . $tgEnv['myInfo']['id'] . PHP_EOL;
        $msg .= '*myName:* ' . tgApi::md_encode($tgEnv['myInfo']['name']) . PHP_EOL;
        $msg .= '*myAccount:* ' . tgApi::md_encode($tgEnv['myInfo']['account']) . PHP_EOL;
        $msg .= '*isCallback:* ' . ($tgEnv['isCallback'] ? 'true' : 'false') . PHP_EOL;
        $msg .= '*isGroup:* ' . ($tgEnv['isGroup'] ? 'true' : 'false') . PHP_EOL;
        $msg .= '*messageText:* ' . tgApi::md_encode($tgEnv['messageText']) . PHP_EOL;
        $msg .= '*messageId:* ' . $tgEnv['messageId'] . PHP_EOL;
        $msg .= '*chatId:* ' . $tgEnv['chatId'] . PHP_EOL;
        $msg .= '*userId:* ' . $tgEnv['userId'] . PHP_EOL;
        $msg .= '*userName:* ' . tgApi::md_encode($tgEnv['userName']) . PHP_EOL;
        $msg .= '*userAccount:* ' . tgApi::md_encode($tgEnv['userAccount']) . PHP_EOL;
        $msg .= '*userLanguage:* ' . tgApi::md_encode($tgEnv['userLanguage']) . PHP_EOL;
        tgApi::sendMarkdown($msg);
    }
}

?>
