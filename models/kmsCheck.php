<?php

class kmsKeys { // KMS密钥获取
    private $db, $kmsDB = './db/kmsKeys.db'; // KMS密钥数据库

    private function getVersionName($type, $versionId) { // 获取对应版本的名称
        $res = $this->db->query('SELECT * FROM `' . $type . '_version` WHERE version_id=' . $versionId . ';');
        return $res->fetchArray(SQLITE3_ASSOC)['version_name'];
    }
    
    private function getKmsKeys($type) { // 获取指定类型的密钥信息 win/win-server
        $this->db = new SqliteDB($this->kmsDB);
        $res = $this->db->query('SELECT * FROM `' . $type . '`;');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $index = $row['version'];
            unset($row['version']);
            $data[$this->getVersionName($type, $index)][] = $row;
        }
        return $data;
    }

    public function getKeys($type) { // 获取指定类型的各版本及KMS密钥
        switch ($type) {
            case '': // win和win-server系列
                return $this->getKmsKeys('win') + $this->getKmsKeys('win-server');
            case 'win': // win系列
                return $this->getKmsKeys('win');
            case 'win-server': // win-server系列
                return $this->getKmsKeys('win-server');
            default:
                return array(); // 未知类型 返回空数组
        }
    }
}

class kmsCheck { // KMS服务器检查
    private function getKmsStatus($host, $port) { // 获取KMS服务器状态
        if(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . $host . ']'; // IPv6地址需用中括号包围
        }
        $cmd = $GLOBALS['env']['KMS_VLMCS'] . ' ';
        $cmd .= $host . ':' . $port; // 加入目标服务器信息
        $raw = shell_exec($cmd . ' -G temp'); // 执行vlmcs测试
        preg_match_all('/Sending activation request \(KMS V6\)/', $raw, $match);
        return (count($match[0]) === 6) ? true : false; // 返回KMS服务器状态
    }

    public function isCache($server) { // 检查KMS服务器是否已缓存
        $redis = new RedisCache('kms');
        $status = $redis->getData($server);
        return (!$status) ? false : true;
    }

    public function kmsStatus($host, $port) { // 检测KMS服务器状态
        $redis = new RedisCache('kms');
        $server = $host . ':' . $port;
        $status = $redis->getData($server);
        if (!$status) { // 缓存未命中
            if ($this->getKmsStatus($host, $port)) { // 测试服务器状态
                $status = [ 'status' => 'online' ]; // 服务正常
            } else {
                $status = [ 'status' => 'offline' ]; // 服务掉线
            }
            $redis->setData($server, json_encode($status), 300); // 缓存5min
        } else { // 缓存命中
            $status = json_decode($status, true); // 使用缓存数据
        }
        return $status;
    }
}

class kmsCheckEntry { // KMS功能入口
    private function formatCheck($server) { // 输入参数格式检查
        $temp = explode(':', $server);
        if (count($temp) === 1) { // 不带:的请求
            if (!(new Domain)->isHost($temp[0])) { return null; } // 错误请求
            return array(
                'host' => $temp[0],
                'port' => 1688
            );
        } else if (count($temp) === 2) { // 带一个:的请求
            if (!(new Domain)->isHost($temp[0])) { return null; } // 错误请求
            if ($temp[1] < 0 || $temp[1] > 65535) { return null; } // 错误请求
            return array(
                'host' => $temp[0],
                'port' => $temp[1]
            );
        } else { // 带多个:的请求
            return null; // 错误请求
        }
    }

    private function genMessage($server, $status) { // 生成KMS状态消息
        $msg = '`' . $server . '`' . PHP_EOL;
        if ($status['status'] === 'online') {
            return $msg . 'KMS服务*正常运行*'; // 服务正常
        } else {
            return $msg . 'KMS服务*无法使用*'; // 服务异常
        }
    }

    private function sendKmsStatus($host, $port) { // 检查并发送KMS服务器状态
        $server = $host . ':' . $port;
        if ((new kmsCheck)->isCache($server)) { // 状态已缓存
            $status = (new kmsCheck)->kmsStatus($host, $port);
            tgApi::sendMarkdown($this->genMessage($server, $status)); // 发送服务器状态
            return;
        }
        $message = tgApi::sendMarkdown('`' . $server . '`' . PHP_EOL . 'KMS服务检测中...');
        $messageId = json_decode($message, true)['result']['message_id']; // 未缓存 发送缓冲消息
        $status = (new kmsCheck)->kmsStatus($host, $port); // 发起查询
        tgApi::editMessage(array( // 返回查询结果
            'parse_mode' => 'Markdown',
            'message_id' => $messageId,
            'text' => $this->genMessage($server, $status)
        ));
    }

    private function simpStr($str) { // 简化版本名称
        preg_match_all('/[0-9a-zA-Z]/', $str, $match);
        return implode('', $match[0]);
    }

    private function genKmsKeys($targetVersion) { // 显示指定版本的KMS密钥列表
        $content = explode('|', $targetVersion);
        $type = $content[0]; // 获取类型 win/win-server
        $targetVersion = $content[1]; // 获取目标版本
        $kmsKeys = (new kmsKeys)->getKeys($type); // 获取该类型的所有版本
        foreach ($kmsKeys as $version => $kmsKey) { // 比对压缩以后的名称
            if ($this->simpStr($version) === $targetVersion) { break; } // 匹配成功
        }
        $msg = '*' . $version . ' KMS Keys*' . PHP_EOL . PHP_EOL;
        foreach ($kmsKey as $row) {
            $msg .= $row['name'] . '：`' . $row['key'] . '`' . PHP_EOL . PHP_EOL;
        }
        return array(
            'text' => $msg,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(array( // 返回按钮
                'inline_keyboard' => array([[
                    'text' => '<< Go back <<',
                    'callback_data' => '/kms ' . $type
                ]])
            ))
        );
    }

    private function genKmsVersions($type) { // 获取版本列表 win/win-server
        $kmsKeys = (new kmsKeys)->getKeys($type);
        foreach ($kmsKeys as $version => $kmsKey) {
            $buttons[] = array([ // 生成按钮列表
                'text' => $version,
                'callback_data' => '/kms ' . $type . '|' . $this->simpStr($version)
            ]);
        }
        $buttons[] = array([
            'text' => '<< Go back <<',
            'callback_data' => '/kms menu' // 加入返回按钮
        ]);
        return array(
            'text' => 'Which version did you need?',
            'reply_markup' => json_encode(array( // 显示列表按钮
                'inline_keyboard' => $buttons
            ))
        );
    }

    private function genActiveCmd() { // 生成KMS激活命令
        $kmsHost = $GLOBALS['env']['KMS_HOST'];
        $actiCmd = '```' . PHP_EOL . 'slmgr /upk' . PHP_EOL . 'slmgr /ipk {KMS_KEY}' . PHP_EOL;
        $actiCmd .= 'slmgr /skms ' . $kmsHost . PHP_EOL . 'slmgr /ato' . PHP_EOL . 'slmgr /dlv';
        $actiCmd .= PHP_EOL . '```';
        return array(
            'text' => $actiCmd,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(array( // 返回按钮
                'inline_keyboard' => array([[
                    'text' => '<< Go back <<',
                    'callback_data' => '/kms menu'
                ]])
            ))
        );
    }

    private function genSelectMsg() { // 生成KMS版本选择消息
        return array(
            'text' => 'Which one did you need?',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array( // 功能选择按钮
                    array([
                        'text' => 'Windows',
                        'callback_data' => '/kms win' // Windows密钥
                    ]),
                    array([
                        'text' => 'Windows Server',
                        'callback_data' => '/kms win-server' // Windows Server密钥
                    ]),
                    array([
                        'text' => 'Activation Command',
                        'callback_data' => '/kms cmd' // 激活命令
                    ])
                )
            ))
        );
    }

    private function sendHelp() { // 发送使用说明
        $helpMessage = array(
            'parse_mode' => 'Markdown',
            'text' => '*Usage:*  `/kms IP/Domain[:port]`',
            'reply_markup' => json_encode(array( // 加入 获取KMS密钥 按钮
                'inline_keyboard' => array([[
                    'text' => 'Get KMS Keys',
                    'callback_data' => '/kms menu'
                ]])
            ))
        );
        tgApi::sendMessage($helpMessage);
    }

    public function query($rawParam) { // KMS测试查询入口
        if ($rawParam == '' || $rawParam === 'help') { // 显示使用说明
            $this->sendHelp();
            return;
        }
        $server = $this->formatCheck($rawParam);
        if ($server === null) {
            tgApi::sendText('Illegal request'); // 输入格式错误
            return;
        }
        $this->sendKmsStatus($server['host'], $server['port']); // 检查并发送KMS服务器状态
    }

    public function callback($rawParam) { // KMS测试回调入口
        $messageId = $GLOBALS['tgEnv']['messageId'];
        switch ($rawParam) {
            case 'menu': // 选择菜单
                $message = $this->genSelectMsg();
                break;
            case 'cmd': // KMS激活命令
                $message = $this->genActiveCmd();
                break;
            case 'win': // Windows激活密钥
            case 'win-server': // Windows Server激活密钥
                $message = $this->genKmsVersions($rawParam);
                break;
            default:
                $message = $this->genKmsKeys($rawParam); // 显示密钥列表
        }
        tgApi::editMessage(array(
            'message_id' => $messageId // 修改源消息内容
        ) + $message);
    }
}

?>
