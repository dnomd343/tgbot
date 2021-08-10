<?php

class ntpCheck {
    private $redisSetting = array( // redis缓存配置
        'host' => '127.0.0.1',
        'port' => 6379,
        'passwd' => '',
        'prefix' => 'ntp-'
    );

    private function isHost($host) { // 判断host是否合法
        preg_match('/^(?=^.{3,255}$)[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+$/', $host, $match);
        if (count($match) !== 0) { // 域名
            if (!is_numeric(substr($host, -1))) { return true; } // 域名最后一位不为数字
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) { // IP地址
            return true;
        }
        return false;
    }

    private function curlPost($url, $data) { // curl模拟post操作 40s超时
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 40);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }

    private function getRedisData($host) { // 查询Redis缓存，不存在返回NULL
        $redis = new Redis();
        $redis->connect($this->redisSetting['host'], $this->redisSetting['port']);
        if ($this->redisSetting['passwd'] != '') {
            $redis->auth($this->redisSetting['passwd']);
        }
        $redisKey = $this->redisSetting['prefix'] . $host;
        $redisValue = $redis->exists($redisKey) ? $redis->get($redisKey) : NULL;
        return $redisValue;
    }
    
    private function setRedisData($host, $data, $cacheTTL) { // 写入信息到Redis缓存
        $redis = new Redis();
        $redis->connect($this->redisSetting['host'], $this->redisSetting['port']);
        if ($this->redisSetting['passwd'] != '') {
            $redis->auth($this->redisSetting['passwd']);
        }
        $redisKey = $this->redisSetting['prefix'] . $host;
        $redis->set($redisKey, $data); // 写入数据库
        $redis->pexpire($redisKey, $cacheTTL); // 设置过期时间
    }

    private function getNtpStatus($host) { // 获取NTP服务器状态
        $html = $this->curlPost('https://servertest.online/ntp', array(
            'a' => $host,
            'c' => 'Query+both'
        ));
        preg_match('/<\/form>[\s\S]+<footer>/', $html, $match); // 切取数据部分
        preg_match('/<label>[\s\S]+<footer>/', $match[0], $match); // 去除前部干扰
        $match = substr($match[0], 0, strlen($match[0]) - 8); // 去除后部<footer>
        $match = strtr($match, array('<h2>IPv6 test results</h2>' => '<br><br>')); // 去除中间干扰
        $match = explode('<br>', $match); // 以<br>为界限切割
        $match[] = ''; // 添加空记录方便分组
        foreach ($match as $row) { // 匹配分组
            if ($row == '') {
                if (count($temp) === 5) {
                    $record[] = $temp;
                    $temp = array();
                }
                continue;
            }
            $temp[] = $row;
        }
        foreach ($record as $group) { // 检查组内容是否正常
            $group['ok'] = '';
            foreach ($group as $index => $row) { // 遍历组内每一行
                if ($index === 'ok') { continue; }
                $row = strtr($row, array('<label>' => ''));
                $row = strtr($row, array(':</label>' => '|'));
                $content = explode('|', $row); // 切取参数
                if (count($content) !== 2) { unset($group['ok']); } // 格式不合格
                $group[$content[0]] = $content[1];
                unset($group[$index]); // 删除原始记录
            }
            if (!isset($group['ok'])) { continue; } // 剔除不合格组
            if ($group['Result'] !== '<b class=com>OK</b>') { continue; }
            if (!filter_var($group['Server'], FILTER_VALIDATE_IP)) { continue; }
            unset($group['Result']);
            unset($group['ok']);
            $data[] = $group;
        }
        return ($data === null) ? array() : $data;
    }

    public function checkHost($host) { // 检测host合法性
        if ($this->isHost($host)) {
            return array('status' => 'ok');
        } else {
            return array('status' => 'error');
        }
    }

    private function ntpStatus($host) { // 检测NTP服务器状态 带缓存
        $servers = $this->getRedisData($host); // 查询缓存数据
        if (!$servers) { // 缓存未命中
            $servers = $this->getNtpStatus($host); // 发起测试
            $this->setRedisData($host, json_encode($servers), 300000); // 缓存5min
        } else { // 缓存命中
            $servers = json_decode($servers, true); // 使用缓存数据
        }
        return $servers;
    }

    public function checkNtp($host) {
        $servers = $this->ntpStatus($host);
        if (count($servers) === 0) {
            $msg = '`' . $host . '`' . PHP_EOL;
            $msg .= 'NTP Server *Offline*' . PHP_EOL . PHP_EOL;
            return array(
                'parse_mode' => 'Markdown',
                'text' => $msg
            );
        }
        $msg = '`' . $host . '`' . PHP_EOL;
        $msg .= 'NTP Server *Normally*' . PHP_EOL . PHP_EOL;
        foreach ($servers as $server) {
            $msg .= '`' . $server['Server'] . '`' . PHP_EOL;
            $msg .= '_Stratum:_ ' . $server['Stratum'] . PHP_EOL;
            $msg .= '_Offset:_ ' . $server['Offset'] . PHP_EOL;
            $msg .= PHP_EOL;
        }
        return array(
            'parse_mode' => 'Markdown',
            'text' => $msg
        );
    }
}

function ntpCheck($rawParam) { // NTP测试入口
    global $chatId;
    if ((new ntpCheck)->checkHost($rawParam)['status'] === 'error') {
        sendText($chatId, 'Illegal host'); // 输入错误
        return;
    }
    $message = json_decode(sendMessage($chatId, array(
        'parse_mode' => 'Markdown',
        'text' => '`' . $rawParam . '`' . PHP_EOL . 'NTP Server Checking...'
    )), true);
    sendPayload(array(
        'method' => 'editMessageText',
        'chat_id' => $chatId,
        'message_id' => $message['result']['message_id'],
    ) + (new ntpCheck)->checkNtp($rawParam)); // 发起查询并返回结果
}

?>
