<?php

class ntpServer { // 获取NTP服务器列表
    private $db, $ntpDB = './db/ntpServer.db'; // NTP服务器数据库

    private function getListName($listId) { // 获取对应组的名称
        $res = $this->db->query('SELECT * FROM `ntp_list` WHERE id=' . $listId . ';');
        return $res->fetchArray(SQLITE3_ASSOC)['name'];
    }

    public function getList() { // 获取所有NTP服务器地址
        $this->db = new SqliteDB($this->ntpDB);
        $res = $this->db->query('SELECT * FROM `ntp_host`;');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $index = $row['list_id'];
            unset($row['list_id']);
            $list[$this->getListName($index)][] = $row;
        }
        return $list;
    }
}

class ntpCheck { // NTP服务器检查
    private function formatOffset($str) { // 格式化偏移时间
        $num = number_format($str, 6) * 1000; // s -> ms
        $str = sprintf("%1\$.3f", $num); // 补零到小数点后3位
        if ($num > 0) {
            $str = '+' . $str; // 正数前加+
        }
        return $str . 'ms';
    }

    private function sortByIp($servers) { // 排序算法
        $temp = array();
        foreach ($servers as $val){
            $temp[] = $val['Server'];
        }
        sort($temp);
        $temp = array_flip($temp);
        $sort = array();
        foreach ($servers as $val) {
            $temp_1 = $val['Server'];
            $temp_2 = $temp[$temp_1];
            $sort[$temp_2] = $val;
        }
        asort($sort);
        return $sort;
    }

    private function sortServer($servers) { // 按顺序排列服务器
        foreach ($servers as $server) {
            if(filter_var($server['Server'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipv4[] = $server; // 提取IPv4服务器
            }
            if(filter_var($server['Server'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ipv6[] = $server; // 提取IPv6服务器
            }
        }
        if (isset($ipv4)) { // 存在IPv4服务器
            foreach ($ipv4 as $index => $ip) {
                $ipv4[$index]['Server'] = ip2long($ip['Server']); // IPv4预处理
            }
            $ipv4 = $this->sortByIp($ipv4); // 排序IPv4服务器
            foreach ($ipv4 as $index => $ip) {
                $ip['Server'] = long2ip($ip['Server']); // IPv4恢复
                $result[] = $ip;
            }
        }
        if (isset($ipv6)) { // 存在IPv6服务器
            foreach ($ipv6 as $index => $ip) {
                $ipv6[$index]['Server'] = (new DNS)->ip2long6($ip['Server']); // IPv6预处理
            }
            $ipv6 = $this->sortByIp($ipv6); // 排序IPv6服务器
            foreach ($ipv6 as $index => $ip) {
                $ip['Server'] = (new DNS)->long2ip6($ip['Server']); // IPv6恢复
                $result[] = $ip;
            }
        }
        return (!isset($result)) ? array() : $result; // 无结果 返回空数组
    }

    private function getNtpStatus($host) { // 获取NTP服务器状态
        $html = (new Curl)->post('https://servertest.online/ntp', array(
            'a' => $host,
            'c' => 'Query+both'
        ));
        if ($html == '') { return null; } // 服务错误
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
        $data = (!isset($data)) ? array() : $data; // 无结果时为空数组
        return $this->sortServer($data); // 排序后返回
    }

    public function isCache($host) { // 检查NTP服务器是否已缓存
        $redis = new RedisCache('ntp');
        $servers = $redis->getData($host);
        return (!$servers) ? false : true;
    }

    public function ntpStatus($host) { // 检测NTP服务器状态
        $redis = new RedisCache('ntp');
        $servers = $redis->getData($host); // 查询缓存数据
        if (!$servers) { // 缓存未命中
            $servers = $this->getNtpStatus($host); // 发起测试
            if ($servers === null) { return null; } // 服务接口错误
            $redis->setData($host, json_encode($servers), 900); // 缓存15min
        } else { // 缓存命中
            $servers = json_decode($servers, true); // 使用缓存数据
        }
        return $servers;
    }
}

class ntpCheckEntry { // NTP功能入口
    private function hashGroupName($str) { // 计算组名的哈希值 取前12位
        return substr(md5($str), 0, 12);
    }

    private function showNtpList() { // 列出所有NTP服务器组
        $ntpList = (new ntpServer)->getList();
        foreach ($ntpList as $index => $ntpHosts) {
            $buttons[] = array([ // 生成按钮列表
                'text' => $index,
                'callback_data' => '/ntp ' . $this->hashGroupName($index)
            ]);
        }
        return array(
            'text' => 'Which one did you like?',
            'reply_markup' => json_encode(array( // 按钮列表
                'inline_keyboard' => $buttons
            ))
        );
    }

    private function showNtpServer($targetGroup) { // 列出指定组的NTP服务器
        $ntpList = (new ntpServer)->getList();
        foreach ($ntpList as $index => $ntpHosts) { // 搜索目标组
            if ($this->hashGroupName($index) === $targetGroup) { break; }
        }
        $msg = '*' . $index . '*' . PHP_EOL; // 显示组名
        foreach ($ntpHosts as $ntpHost) {
            if ($ntpHost['desc'] !== '') {
                $msg .= $ntpHost['desc'] . '：'; // 服务器描述
            }
            $msg .= '`' . $ntpHost['host'] . '`' . PHP_EOL; // 服务器地址
        }
        return array(
            'text' => $msg,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array([[ // 返回上一级 按钮
                    'text' => ' << Go back <<',
                    'callback_data' => '/ntp servers'
                ]])
            ))
        );
    }

    private function genMessage($host, $servers) { // 生成返回信息
        if (count($servers) === 0) { // NTP服务不可用
            $msg = '`' . $host . '`' . PHP_EOL;
            $msg .= 'NTP Server *Offline*' . PHP_EOL . PHP_EOL;
            return $msg;
        }
        $msg = '`' . $host . '`' . PHP_EOL;
        $msg .= 'NTP Server *Normally*' . PHP_EOL . PHP_EOL; // NTP服务器正常
        foreach ($servers as $server) { // 显示所有服务器
            $msg .= '`' . $server['Server'] . '`' . PHP_EOL;
            $msg .= '_Stratum:_ ' . $server['Stratum'] . PHP_EOL;
            $msg .= '_Offset:_ ' . $server['Offset'] . PHP_EOL;
            $msg .= PHP_EOL;
        }
        return $msg;
    }

    private function sendNtpStatus($host) { // 检查并发送NTP服务器状态
        if ((new ntpCheck)->isCache($host)) { // 状态已缓存
            $servers = (new ntpCheck)->ntpStatus($host);
            tgApi::sendMarkdown($this->genMessage($host, $servers)); // 发送服务器状态
            return;
        }
        $message = tgApi::sendMarkdown('`' . $host . '`' . PHP_EOL . 'NTP Server Checking...');
        $messageId = json_decode($message, true)['result']['message_id']; // 未缓存 发送缓冲消息
        $servers = (new ntpCheck)->ntpStatus($host); // 发起查询
        if ($servers === null) { // 上游接口错误
            $message = 'Server error';
        } else {
            $message = $this->genMessage($host, $servers); // 生成返回信息
        }
        tgApi::editMessage(array( // 返回查询结果
            'text' => $message,
            'parse_mode' => 'Markdown',
            'message_id' => $messageId
        ));
    }

    private function sendHelp() { // 显示使用说明
        $helpMessage = array(
            'parse_mode' => 'Markdown',
            'text' => '*Usage:*  `/ntp IP/Domain`',
            'reply_markup' => json_encode(array( // 显示 NTP服务器列表 按钮
                'inline_keyboard' => array([[
                    'text' => 'Get NTP Servers',
                    'callback_data' => '/ntp servers'
                ]])
            ))
        );
        tgApi::sendMessage($helpMessage); // 发送使用说明
    }

    public function query($rawParam) { // NTP测试查询入口
        if ($rawParam == '' || $rawParam === 'help') {
            $this->sendHelp(); // 显示使用说明
        } else if (!(new Domain)->isHost($rawParam)) {
            tgApi::sendText('Illegal host'); // 输入错误
        } else {
            $this->sendNtpStatus($rawParam); // 检查并发送NTP服务器状态
        }
    }

    public function callback($rawParam) { // NTP测试回调入口
        if ($rawParam === 'servers') {
            $content = $this->showNtpList(); // 显示可选组
        } else {
            $content = $this->showNtpServer($rawParam); // 显示指定组的服务器列表
        }
        tgApi::editMessage(array(
            'message_id' => $GLOBALS['tgEnv']['messageId']
        ) + $content); // 输出结果
    }
}

?>
