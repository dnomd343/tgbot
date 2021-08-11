<?php

class kmsCheck {
    private $api = 'https://kms.343.re/';

    public function getKmsKeys($type) {
        switch ($type) {
            case '':
                $url = $this->api . 'json';
                break;
            case 'win':
            case 'win-server':
                $url = $this->api . $type . '/json';
                break;
            default:
                return array();
        }
        return json_decode(file_get_contents($url), true);
    }

    public function kmsStatus($host, $port) {
        $server = $host . ':' . $port;
        $url = $this->api . 'check?host=' . $host . '&port=' . $port;
        $content = json_decode(file_get_contents($url), true); // 请求上游接口
        switch ($content['status']) {
            case 'ok':
                return array(
                    'status' => 'ok',
                    'online' => true,
                    'server' => $server
                );
            case 'error':
                return array(
                    'status' => 'ok',
                    'online' => false,
                    'server' => $server
                );
            default:
                return array(
                    'status' => 'error',
                    'message' => 'Server error'
                );
        }
    }
}

class kmsCheckEntry {
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

    private function formatCheck($server) {
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

    private function getKmsVersions($type) { // 获取win或win-server的版本列表
        $kmsKeys = (new kmsCheck)->getKmsKeys($type);
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

    private function getKmsKeys($targetVersion) { // 显示指定版本的KMS密钥列表
        $kmsKeys = (new kmsCheck)->getKmsKeys('');
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

    private function checkKms($host, $port) { // 检查KMS服务器状态
        $content = (new kmsCheck)->kmsStatus($host, $port);
        if ($content['status'] === 'ok') {
            if ($content['online'] === true) {
                return array(
                    'parse_mode' => 'Markdown',
                    'text' => '`' . $content['server'] . '`' . PHP_EOL . 'KMS服务*正常运行*'
                );
            } else {
                return array(
                    'parse_mode' => 'Markdown',
                    'text' => '`' . $content['server'] . '`' . PHP_EOL . 'KMS服务*无法使用*'
                );
            }
        } else {
            return array(
                'text' => $content['message']
            );
        }
    }

    public function query($rawParam) { // kmsCheck查询入口
        if ($rawParam == '' || $rawParam === 'help') { // 显示使用说明
            tgApi::sendMessage(array(
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
        $check = $this->formatCheck($rawParam);
        if ($check['status'] === 'error') { // 输入格式有误
            tgApi::sendText($check['message']);
            return;
        }
        $message = json_decode(tgApi::sendMessage(array(
            'parse_mode' => 'Markdown',
            'text' => '`' . $rawParam . '`' . PHP_EOL . 'KMS服务检测中...'
        )), true);
        fastcgi_finish_request(); // 断开连接
        tgApi::editMessage(array(
            'message_id' => $message['result']['message_id'],
        ) + $this->checkKms($check['host'], $check['port'])); // 发起查询并返回结果
    }

    public function callback($rawParam) { // kmsCheck回调入口
        global $tgEnv;
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
                tgApi::editMessage(array(
                    'message_id' => $tgEnv['messageId'],
                ) + $selectMsg);
                return;
            case 'cmd':
                tgApi::editMessage(array(
                    'message_id' => $tgEnv['messageId'],
                    'parse_mode' => 'Markdown',
                    'text' => $actiCmd,
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array([[
                            'text' => '<< Go back <<',
                            'callback_data' => '/kms keys'
                        ]])
                    ))
                ));
                return;
            case 'win':
            case 'win-server':
                tgApi::editMessage(array(
                    'message_id' => $tgEnv['messageId'],
                ) + $this->getKmsVersions($rawParam));
                return;
        }
        tgApi::editMessage(array(
            'message_id' => $tgEnv['messageId']
        ) + $this->getKmsKeys($rawParam));
    }
}

?>
