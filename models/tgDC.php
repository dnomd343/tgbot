<?php

class tgDC { // 查询用户DC
    private function getDcDetail($dc) { // 返回DC详细信息
        switch ($dc) {
            case 'DC1':
                return array(
                    'as' => 'AS59930',
                    'ip' => '149.154.175.0/24',
                    'addr' => '美国佛罗里达州迈阿密'
                );
            case 'DC2':
            case 'DC3':
            case 'DC4':
                return array(
                    'as' => 'AS62041',
                    'ip' => '149.154.160.0/21',
                    'addr' => '荷兰阿姆斯特丹'
                );
            case 'DC5':
                return array(
                    'as' => 'AS62014',
                    'ip' => '149.154.168.0/22',
                    'addr' => '新加坡'
                );
            default:
                return array(); // 错误输入
        }
    }

    private function getUserInfo($account) { // 获取Telegram用户信息
        $info['dc'] = null;
        $info['name'] = null;
        $info['account'] = $account;
        $html = (new Curl)->get('https://t.me/' . $account); // 获取原始HTML数据
        $html = preg_replace('/[\t\n\r]+/', '', $html); // 去除干扰
        if (!is_string($html) || $html == '') {
            return $info + [ 'time' => time() ]; // 用户名无效
        }
        $avatarRegex = '/<img class="tgme_page_photo_image" src="([^<>]+)">/';
        $nameRegex = '/<span dir="auto">(.+?)<\/span>/';
        preg_match($avatarRegex, $html, $avatarMatch); // 匹配目标头像
        preg_match($nameRegex, $html, $nameMatch); // 匹配目标名称
        if (isset($nameMatch[1])) {
            $info['name'] = $nameMatch[1]; // 获取用户名
        }
        if (isset($avatarMatch[1])) { // 头像可见
            $dcRegex = '/https:\/\/cdn([1-5])\.telesco\.pe\//';
            preg_match($dcRegex, $avatarMatch[1], $dcMatch); // 根据cdn?.telesco.pe获取DC
            if (isset($dcMatch[1])) {
                $info['dc'] = 'DC' . $dcMatch[1]; // DC匹配成功
            }
        }
        if ($info['dc'] != null) {
            $info += $this->getDcDetail($info['dc']); // 载入DC详细信息
        }
        $info['time'] = time(); // 记录查询时间戳
        return $info;
    }

    public function getInfo($account, $isCache = true) { // 获取用户信息 默认带缓存
        $redis = new RedisCache('tgdc');
        $info = $redis->getData($account); // 查询缓存数据
        if (!$isCache || !$info) { // 不缓存 或 缓存未命中
            $info = $this->getUserInfo($account); // 发起查询
            $redis->setData($account, json_encode($info)); // 缓存数据 永久
        } else { // 缓存命中
            $info = json_decode($info, true); // 使用缓存数据
        }
        return $info;
    }
}

class tgDCEntry { // DC查询入口
    private function checkAccount($account) { // 检查用户合法性
        preg_match('/^[a-zA-Z0-9_]+$/', $account, $match);
        if (count($match) === 0 or strlen($account) < 5) { // 用户名由至少5位 0-9/a-z/A-Z/_ 组成
            return false;
        }
        if (substr($account, 0, 1) === '_' || substr($account, -1) === '_') { // 不能以_开头结尾
            return false;
        }
        return true;
    }

    private function sendHelp() { // 显示帮助信息
        $message = tgApi::sendMarkdown('*Usage:*  `/dc username`');
        $message = json_decode($message, true);
        return $message['result']['message_id']; // 返回消息ID
    }

    private function genMessage($info) { // 生成返回信息
        if (!$info['name'] && !$info['dc']) { // 用户名与头像均无
            return '@' . $info['account'] . ' 无法识别';
        } else if ($info['name'] && !$info['dc']) { // 存在用户名但未设置头像
            return '@' . $info['account'] . ' 未设置头像或不可见';
        }
        $msg = '@' . $info['account'] . ' (' . $info['name'] . ')' . PHP_EOL;
        $msg .= '_' . $info['as'] . '_ ';
        $msg .= '`(``' . $info['ip'] . '``)`' . PHP_EOL;
        $msg .= '*' . $info['dc'] . '* - ' . $info['addr'] . PHP_EOL;
        return $msg; // 返回正常查询结果
    }

    private function sendInfo($account) { // 查询并发送用户信息
        if (!$this->checkAccount($account)) { // 用户名不合法
            tgApi::sendText('用户名无效');
            return;
        }
        $info = (new tgDC)->getInfo($account); // 带缓存查询
        $message = tgApi::sendMarkdown($this->genMessage($info)); // 发送预查询信息
        if (!$info['name'] && !$info['dc']) {
            $cacheTime = 300; // 未设置用户名或用户不存在 缓存5min
        } else if ($info['name'] && !$info['dc']) {
            $cacheTime = 20; // 用户头像不可见 缓存20s
        } else {
            $cacheTime = 86400; // 用户正常 缓存24h
        }
        if ($cacheTime < time() - $info['time']) { // 数据过期 
            $messageId = json_decode($message, true)['result']['message_id'];
            $infoRenew = (new tgDC)->getInfo($account, false); // 不带缓存 重新查询
            unset($info['time']);
            unset($infoRenew['time']);
            if ($info !== $infoRenew) { // 数据出现变化
                tgApi::editMessage(array(
                    'parse_mode' => 'Markdown',
                    'message_id' => $messageId,
                    'text' => $this->genMessage($infoRenew) // 更新信息
                ));
            }
        }
    }

    public function query($rawParam) { // tgDC查询入口
        global $tgEnv;
        if ($rawParam === 'help') { $this->sendHelp(); } // 显示使用说明
        if ($rawParam == '') {
            $rawParam = $tgEnv['userAccount']; // 空指令时查询对方信息
            if (!$tgEnv['isGroup']) {
                $messageId = $this->sendHelp(); // 非群组发送使用说明
            }
        }
        if (substr($rawParam, 0, 1) === '@') {
            $rawParam = substr($rawParam, 1); // 去除用户名前@
        }
        $this->sendInfo($rawParam); // 查询并发送用户信息
        if (!isset($messageId)) { return; }
        sleep(10); // 延迟10s
        tgApi::deleteMessage(array( // 删除使用说明
            'message_id' => $messageId
        ));
    }
}

?>
