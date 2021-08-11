<?php

class tgDC {
    private function getDcDetail($dc) { // 获取DC信息
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
                return array();
        }
    }

    private function curl($url, $timeOut = 5) { // curl模拟 默认5s超时
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeOut);
        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }

    private function checkAccount($account) { // 检查用户名是否合法
        preg_match('/^[a-zA-Z0-9_]+$/', $account, $match);
        if (count($match) === 0 or strlen($account) < 5) { // 用户名由至少5位 0-9/a-z/A-Z/_ 组成
            return false;
        }
        if (substr($account, 0, 1) === '_' || substr($account, -1) === '_') { // 不能以_开头结尾
            return false;
        }
        return true;
    }

    private function getUserInfo($account) { // 获取Telegram用户信息
        $info['account'] = $account;
        $info['name'] = null;
        $info['dc'] = null;
        
        $html = $this->curl('https://t.me/' . $account); // 获取原始HTML数据
        $html = preg_replace('/[\t\n\r]+/', '', $html); // 去除干扰
        if (!is_string($html) || $html == '') { return $info; } // 用户名无效
        $avatarRegex = '/<img class="tgme_page_photo_image" src="([^<>]+)">/';
        $nameRegex = '/<span dir="auto">(.+?)<\/span>/';
        preg_match($avatarRegex, $html, $avatarMatch); // 匹配目标头像
        preg_match($nameRegex, $html, $nameMatch); // 匹配目标名称
        if ($nameMatch[1]) {
            $info['name'] = $nameMatch[1]; // 获取用户名
        }
        if ($avatarMatch[1]) {
            $avatarUrl = $avatarMatch[1]; // 获取头像链接
        }
        if ($avatarUrl) { // 头像存在
            $dcRegex = '/https:\/\/cdn(.+)\.telesco\.pe\//';
            preg_match_all($dcRegex, $avatarUrl, $dcMatch); // 根据cdn?.telesco.pe获取DC
            if ($dcMatch[1]) {
                $info['dc'] = 'DC' . $dcMatch[1][0];
            }
        }
        if ($info['dc']) {
            $info += $this->getDcDetail($info['dc']); // 匹配DC参数
        }
        return $info;
    }

    private function getUserInfoCache($account) { // 获取用户信息 带缓存
        $redis = new redisCache('tgdc');
        $info = $redis->getData($account); // 查询缓存数据
        if (!$info) { // 缓存未命中
            $info = $this->getUserInfo($account); // 发起查询
            if (!$info['name'] && !$info['dc']) { // 用户名与头像均无
                $cacheTTL = 120; // 缓存2min
            } else if ($info['name'] && !$info['dc']) { // 存在用户名但未设置头像
                $cacheTTL = 20; // 缓存20s
            } else {
                $cacheTTL = 3600; // 其余情况缓存1h
            }
            $redis->setData($account, json_encode($info), $cacheTTL); // 缓存数据
        } else { // 缓存命中
            $info = json_decode($info, true); // 使用缓存数据
        }
        return $info;
    }

    public function getInfo($account) { // 外部调用入口
        if (substr($account, 0, 1) === '@') { // 用户名前可带有@
            $account = substr($account, 1);
        }
        if (!$this->checkAccount($account)) { // 用户名不合法
            return array(
                'text' => '用户名无效'
            );
        }
        $info = $this->getUserInfoCache($account);
        if (!$info['name'] && !$info['dc']) { // 用户名与头像均无
            return array(
                'text' => '@' . $info['account'] . ' 无法识别'
            );
        } else if ($info['name'] && !$info['dc']) { // 存在用户名但未设置头像
            return array(
                'text' => '@' . $info['account'] . ' 未设置头像或不可见'
            );
        }
        $msg = '@' . $info['account'] . ' (' . $info['name'] . ')' . PHP_EOL;
        $msg .= '<i>' . $info['as'] . '</i> <code>(' . $info['ip'] . ')</code>' . PHP_EOL;
        $msg .= '<b>' . $info['dc'] . '</b> - ' . $info['addr'] . PHP_EOL;
        return array(
            'parse_mode' => 'HTML', // HTML格式输出
            'text' => $msg
        );
    }
}

function tgDC($rawParam) { // DC查询入口
    global $chatId, $userAccount, $isGroup;
    if ($rawParam !== '') { // 请求不为空
        sendMessage($chatId, (new tgDC)->getInfo($rawParam)); // 查询并返回数据
        return;
    }
    if (!$isGroup) { // 群组不发送帮助信息
        $message = json_decode(sendMessage($chatId, array( // 发送帮助信息
            'parse_mode' => 'Markdown',
            'text' => '*Usage:*  `/dc username`'
        )), true);
    }
    sendMessage($chatId, (new tgDC)->getInfo($userAccount)); // 查询并返回数据
    if ($isGroup) { return; }
    sleep(15);
    sendPayload(array( // 删除帮助信息
        'method' => 'deleteMessage',
        'chat_id' => $chatId,
        'message_id' => $message['result']['message_id']
    ));
}

?>
