<?php

class icpQuery { // ICP备案查询
    private $apiPath;

    public function __construct() {
        $this->apiPath = 'https://apidatav2.chinaz.com/single/icp?key=' . $GLOBALS['env']['ICP_KEY'] . '&domain=';
    }

    private function getIcpInfo($domain) { // ICP备案查询
        $domain = urlencode((new Punycode)->decode($domain)); // API接口需要URL编码原始域名
        $info = json_decode(file_get_contents($this->apiPath . $domain), true); // 请求API接口
        if ($info['StateCode'] === 1) {
            return $info['Result'] + array( // 存在ICP备案
                'time' => time(),
                'hasIcp' => true
            );
        } else if ($info['StateCode'] === 0) {
            if ($info['Reason'] !== '暂无备案信息') { return null; } // 服务错误
            return array( // 无ICP备案
                'time' => time(),
                'hasIcp' => false
            );
        } else {
            return null; // 服务错误
        }
    }

    public function isCache($domain) { // 查询域名是否存在缓存
        $redis = new RedisCache('icp');
        $info = $redis->getData($domain); // 查询缓存数据
        return ($info) ? true : false;
    }

    public function icpInfo($domain, $isCache = true) { // ICP查询入口 默认带缓存
        $redis = new RedisCache('icp');
        $info = $redis->getData($domain); // 查询缓存数据
        if (!$isCache || !$info) { // 不缓存 或 缓存未命中
            $info = $this->getIcpInfo($domain); // 发起查询
            if ($info === null) { return null; } // 服务错误
            $redis->setData($domain, json_encode($info)); // 缓存ICP信息 永久
        } else { // 缓存命中
            $info = json_decode($info, true); // 使用缓存数据
        }
        return $info;
    }
}

class icpQueryEntry { // ICP信息查询入口
    private function genMessage($domain, $info) { // 生成ICP数据消息
        if ($info === null) { return 'Server error'; } // 服务错误
        $msg = '`' . (new Punycode)->decode($domain) . '`' . PHP_EOL;
        if (!$info['hasIcp']) { return $msg . '暂无备案信息'; } // 无ICP备案
        $msg .= '*类型：*' . $info['CompanyType'] . PHP_EOL;
        if ($info['Owner'] != '') { // 负责人为空不显示
            $msg .= '*负责人：*' . $info['Owner'] . PHP_EOL;
        }
        if ($info['Owner'] != $info['CompanyName']) { // 名称与负责人重复时不显示
            $msg .= '*名称：*' . $info['CompanyName'] . PHP_EOL;
        }
        $msg .= '*主页：*';
        $pages = explode(';', $info['MainPage']);
        foreach ($pages as $page) { // 可能存在多个主页 逐个输出
            $msg .= '`' . $page . '`  ';
        }
        $msg .= PHP_EOL;
        $msg .= '*网站名：*' . $info['SiteName'] . PHP_EOL;
        $msg .= '*审核时间：*' . $info['VerifyTime'] . PHP_EOL;
        $msg .= '*许可证号：*' . $info['SiteLicense'] . PHP_EOL;
        return $msg;
    }

    private function sendIcpInfo($domain) { // 查询并发送ICP备案信息
        if (!(new icpQuery)->isCache($domain)) { // 未缓存
            $headerMsg = '`' . (new Punycode)->decode($domain) . '`' . PHP_EOL;
            $message = tgApi::sendMarkdown($headerMsg . 'ICP备案信息查询中...'); // 发送缓冲信息
            $messageId = json_decode($message, true)['result']['message_id'];
            $info = (new icpQuery)->icpInfo($domain); // 发起查询
            tgApi::editMessage(array(
                'parse_mode' => 'Markdown',
                'message_id' => $messageId, // 替换原消息
                'text' => $this->genMessage($domain, $info)
            ));
        } else { // 已缓存
            $info = (new icpQuery)->icpInfo($domain); // 获取缓存信息
            $message = tgApi::sendMarkdown($this->genMessage($domain, $info)); // 先输出
            if (time() - $info['time'] < 90 * 86400) { return; } // 缓存有效期90day
            $infoRenew = (new icpQuery)->icpInfo($domain, false); // 不带缓存查询
            $messageId = json_decode($message, true)['result']['message_id'];
            unset($info['time']);
            unset($infoRenew['time']);
            if ($info !== $infoRenew) { // 数据出现变化
                tgApi::editMessage(array(
                    'parse_mode' => 'Markdown',
                    'message_id' => $messageId,
                    'text' => $this->genMessage($domain, $infoRenew) // 更新信息
                ));
            }
        }
    }

    public function query($rawParam) { // ICP备案查询入口
        if ($rawParam == '' || $rawParam === 'help') {
            tgApi::sendMarkdown('*Usage:*  `/icp domain`'); // 显示使用说明
            return;
        }
        $content = (new Domain)->analyse($rawParam);
        if (!isset($content['domain'])) { // 格式错误
            tgApi::sendText('Illegal Request');
        } else if (!isset($content['tld'])) { // 未知TLD
            tgApi::sendText('Unknow TLD');
        } else if ((new Domain)->icpTldInfo($content['tld']) === null) { // TLD无法ICP备案
            tgApi::sendMarkdown('`' . $content['tld'] . '` is not allowed in ICP');
        } else {
            $this->sendIcpInfo($content['site']); // 查询并输出域名备案信息
        }
    }
}

?>
