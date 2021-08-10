<?php

class kmsCheck {
    private $api = 'https://kms.343.re';

    private function isHost($host) { // 判断host是否合法
        preg_match('/^(?=^.{3,255}$)[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+$/', $host, $match);
        if (count($match) !== 0) { // 域名
            if (!is_numeric(substr($host, -1))) { return true; } // 域名最后一位不为数字
        }
        if (filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) { // IPv4地址
            return true;
        }
        return false;
    }

    public function check($server) {
        $temp = explode(':', $server);
        if (count($temp) === 1) { // 不带:的请求
            
            if ($this->isHost($temp[0])) {
                $host = $server;
                $port = 1688;
            } else {
                return array(
                    'status' => 'error',
                    'message' => 'Illegal host'
                );
            }
        } else if (count($temp) === 2) { // 带一个:的请求
            if ($this->isHost($temp[0])) {
                $host = $temp[0];
            } else {
                return array(
                    'status' => 'error',
                    'message' => 'Illegal host'
                );
            }
            $port = $temp[1];
            if ($port < 0 || $port > 65535) {
                return array(
                    'status' => 'error',
                    'message' => 'Illegal port'
                );
            }
        } else { // 带多个:的请求
            return array(
                'status' => 'error',
                'message' => 'Illegal request'
            );
        }
        return array(
            'status' => 'ok',
            'host' => $host,
            'port' => $port
        );
    }

    private function simpStr($str) { // 简化版本名称
        preg_match_all('/[0-9a-zA-Z]/', $str, $match);
        return implode('', $match[0]);
    }

    public function getKmsVersions($type) {
        $kmsKeys = json_decode(file_get_contents($this->api . '/' . $type . '/json'), true);
        foreach ($kmsKeys as $version => $kmsKey) {
            $buttons[] = array([ // 生成按钮列表
                'text' => $version,
                'callback_data' => '/kms ' . $this->simpStr($version)
            ]);
        }
        $buttons[] = array([
            'text' => '<< Go back <<',
            'callback_data' => '/kms keys'
        ]);
        return array(
            'text' => 'Which version did you need?',
            'reply_markup' => json_encode(array( // 显示列表按钮
                'inline_keyboard' => $buttons
            ))
        );
    }

    public function getKmsKeys($targetVersion) { // 显示指定版本的KMS密钥列表
        $kmsKeys = json_decode(file_get_contents($this->api . '/json'), true);
        foreach ($kmsKeys as $version => $kmsKey) { // 比对压缩以后的名称
            if ($this->simpStr($version) === $targetVersion) { break; } // 匹配成功
        }
        $msg = '*' . $version . ' KMS Keys*' . PHP_EOL . PHP_EOL;
        foreach ($kmsKey as $row) {
            $msg .= $row['name'] . '：`' . $row['key'] . '`' . PHP_EOL . PHP_EOL;
        }
        return array(
            'parse_mode' => 'Markdown',
            'text' => $msg
        );
    }

    public function checkKms($host, $port) {
        $server = $host . ':' . $port;
        $url = $this->api . '/check?host=' . $host . '&port=' . $port;
        $content = json_decode(file_get_contents($url), true); // 请求上游接口
        switch ($content['status']) {
            case 'ok':
                return array(
                    'parse_mode' => 'Markdown',
                    'text' => '`' . $server . '`' . PHP_EOL . 'KMS服务*正常运行*'
                );
            case 'error':
                return array(
                    'parse_mode' => 'Markdown',
                    'text' => '`' . $server . '`' . PHP_EOL . 'KMS服务*无法使用*'
                );
            default:
                return array(
                    'text' => 'Server error'
                );
        }
    }
}

function kmsCheck($rawParam) { // KMS测试入口
    global $chatId;
    if ($rawParam == '' || $rawParam === 'help') { // 显示使用说明
        sendMessage($chatId, array(
            'parse_mode' => 'Markdown',
            'text' => '*Usage:*  `/kms IP/Domain[:port]`',
            'reply_markup' => json_encode(array( // 获取KMS密钥按钮
                'inline_keyboard' => array([[
                    'text' => 'Get KMS Keys',
                    'callback_data' => '/kms keys'
                ]])
            ))
        ));
        return;
    }
    $check = (new kmsCheck)->check($rawParam);
    if ($check['status'] === 'error') { // 输入格式有误
        sendMessage($chatId, array(
            'text' => $check['message']
        ));
        return;
    }
    $message = json_decode(sendMessage($chatId, array(
        'parse_mode' => 'Markdown',
        'text' => '`' . $rawParam . '`' . PHP_EOL . 'KMS服务检测中...'
    )), true);
    sendPayload(array(
        'method' => 'editMessageText',
        'chat_id' => $chatId,
        'message_id' => $message['result']['message_id'],
    ) + (new kmsCheck)->checkKms($check['host'], $check['port'])); // 发起查询并返回结果
}

function kmsCheckCallback($rawParam) { // KMS测试回调入口
    global $chatId, $messageId;
    $selectMsg = array(
        'text' => 'Which one did you need?',
        'reply_markup' => json_encode(array(
            'inline_keyboard' => array(
                array([
                    'text' => 'Windows',
                    'callback_data' => '/kms win'
                ]),
                array([
                    'text' => 'Windows Server',
                    'callback_data' => '/kms win-server'
                ]),
                array([
                    'text' => 'Activation Command',
                    'callback_data' => '/kms cmd'
                ])
            )
        ))
    );
    $actiCmd = '```' . PHP_EOL . 'slmgr /upk' . PHP_EOL . 'slmgr /ipk {KMS_KEY}' . PHP_EOL;
    $actiCmd .= 'slmgr /skms {KMS_HOST}' . PHP_EOL . 'slmgr /ato' . PHP_EOL . 'slmgr /dlv';
    $actiCmd .= PHP_EOL . '```';
    switch ($rawParam) {
        case 'keys':
            sendPayload(array(
                'method' => 'editMessageText',
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ) + $selectMsg);
            return;
        case 'cmd':
            sendPayload(array(
                'method' => 'editMessageText',
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'parse_mode' => 'Markdown',
                'text' => $actiCmd
            ));
            return;
        case 'win':
        case 'win-server':
            sendPayload(array(
                'method' => 'editMessageText',
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ) + (new kmsCheck)->getKmsVersions($rawParam));
            return;
    }
    sendPayload(array(
        'method' => 'editMessageText',
        'chat_id' => $chatId,
        'message_id' => $messageId
    ) + (new kmsCheck)->getKmsKeys($rawParam));
}

?>
