<?php

class ntpDB extends SQLite3 {
    public function __construct() {
        $this->open('./db/ntpServer.db'); // NTP服务器数据库
    }
}

class ntpList {
    private function getListName($list_id) { // 获取对应组的名称
        $db = new ntpDB;
        $res = $db->query('SELECT * FROM `ntp_list` WHERE id=' . $list_id . ';');
        return $res->fetchArray(SQLITE3_ASSOC)['name'];
    }

    public function getNtpList() { // 获取所有NTP服务器地址
        $db = new ntpDB;
        $res = $db->query('SELECT * FROM `ntp_host`;');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $index = $row['list_id'];
            unset($row['list_id']);
            $data[$this->getListName($index)][] = $row;
        }
        return $data;
    }
}

class ntpCheck {
    private function formatOffset($str) { // 格式化Offset
        $num = number_format($str, 6) * 1000; // s -> ms
        $str = sprintf("%1\$.3f", $num); // 补零到小数点后3位
        if ($num > 0) {
            $str = '+' . $str; // 正数前加+
        }
        return $str . 'ms';
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
            $group['Offset'] = $this->formatOffset($group['Offset']); // 转换为ms
            $data[] = $group;
        }
        return ($data === null) ? array() : $data;
    }

    public function ntpStatus($host) { // 检测NTP服务器状态 带缓存
        $redis = new redisCache('ntp');
        $servers = $redis->getData($host); // 查询缓存数据
        if (!$servers) { // 缓存未命中
            $servers = $this->getNtpStatus($host); // 发起测试
            $redis->setData($host, json_encode($servers), 300); // 缓存5min
        } else { // 缓存命中
            $servers = json_decode($servers, true); // 使用缓存数据
        }
        return $servers;
    }
}

class ntpCheckEntry {
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

    private function hashGroupName($str) { // 计算组名的哈希值 取前12位
        return substr(md5($str), 0, 12);
    }

    private function showList() { // 列出所有NTP服务器组
        $ntpList = (new ntpList)->getNtpList();
        foreach ($ntpList as $index => $ntpHosts) {
            $buttons[] = array([ // 生成按钮列表
                'text' => $index,
                'callback_data' => '/ntp ' . $this->hashGroupName($index)
            ]);
        }
        return array(
            'text' => 'Which one did you like?',
            'reply_markup' => json_encode(array( // 列表按钮
                'inline_keyboard' => $buttons
            ))
        );
    }

    private function showNtpServer($targetGroup) { // 列出指定组的NTP服务器
        $ntpList = (new ntpList)->getNtpList();
        foreach ($ntpList as $index => $ntpHosts) {
            if ($this->hashGroupName($index) === $targetGroup) { break; }
        }
        $msg = '*' . $index . '*' . PHP_EOL;
        foreach ($ntpHosts as $ntpHost) {
            if ($ntpHost['desc'] !== '') {
                $msg .= $ntpHost['desc'] . '：';
            }
            $msg .= '`' . $ntpHost['host'] . '`' . PHP_EOL;
        }
        return array(
            'parse_mode' => 'Markdown',
            'text' => $msg,
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array([[
                    'text' => ' << Go back <<',
                    'callback_data' => '/ntp servers'
                ]])
            ))
        );
    }

    private function checkNtp($host) { // 检查NTP服务器状态
        $servers = (new ntpCheck)->ntpStatus($host);
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

    public function query($rawParam) { // NTP测试查询入口
        if ($rawParam == '' || $rawParam === 'help') { // 显示使用说明
            tgApi::sendMessage(array(
                'parse_mode' => 'Markdown',
                'text' => '*Usage:*  `/ntp IP/Domain`',
                'reply_markup' => json_encode(array( // 获取NTP服务列表
                    'inline_keyboard' => array([[
                        'text' => 'Get NTP Servers',
                        'callback_data' => '/ntp servers'
                    ]])
                ))
            ));
            return;
        }
        if (!$this->isHost($rawParam)) {
            tgApi::sendText('Illegal host'); // 输入错误
            return;
        }
        $message = json_decode(tgApi::sendMessage(array(
            'parse_mode' => 'Markdown',
            'text' => '`' . $rawParam . '`' . PHP_EOL . 'NTP Server Checking...'
        )), true);
        fastcgi_finish_request(); // 断开连接
        tgApi::editMessage(array(
            'message_id' => $message['result']['message_id'],
        ) + $this->checkNtp($rawParam)); // 发起查询并返回结果
    }

    public function callback($rawParam) { // NTP测试回调入口
        global $tgEnv;
        if ($rawParam === 'servers') {
            $content = $this->showList(); // 显示可选组
        } else {
            $content = $this->showNtpServer($rawParam); // 显示指定组的服务器列表
        }
        tgApi::editMessage(array(
            'message_id' => $tgEnv['messageId']
        ) + $content); // 输出结果
    }
}

?>
