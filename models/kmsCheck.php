<?php

class kmsCheck {
    private $api = 'https://kms.343.re/check?';

    private function isHost($host) { // 判断host是否合法
        preg_match('/^(?=^.{3,255}$)[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+$/', $host, $match);
        if (count($match) !== 0) { // 域名
            return true;
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

    public function checkKms($host, $port) {
        $server = $host . ':' . $port;
        $url = $this->api . 'host=' . $host . '&port=' . $port;
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
            'text' => '*Usage:*  `/kms IP/Domain[:port]`'
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
        'message_id' => $message['result']['message_id']
    ) + (new kmsCheck)->checkKms($check['host'], $check['port'])); // 发起查询并返回结果
}

?>
