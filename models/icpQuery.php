<?php

class icpQuery {
    private $apiPath;

    public function __construct() {
        global $env;
        $this->apiPath = $env['ICP_API'] . '?key=' . $env['ICP_KEY'] . '&domain=';
    }

    private function getIcpInfo($domain) { // ICP备案查询
        $domain = urlencode((new Punycode)->decode($domain));
        $info = json_decode(file_get_contents($this->apiPath . $domain), true);
        if ($info['StateCode'] === 1) { // 存在ICP备案
            $info = array(
                'status' => 'ok',
                'hasIcp' => true
            ) + $info['Result'];
        } else if ($info['StateCode'] === 0) { // 无ICP备案
            if ($info['Reason'] !== '暂无备案信息') {
                return array(
                    'status' => 'error',
                    'message' => $info['Reason']
                );
            }
            $info = array(
                'status' => 'ok',
                'hasIcp' => false,
                'icpMsg' => $info['Reason']
            );
        } else {
            $info = array(
                'status' => 'error', // 服务错误
                'message' => 'Server error'
            );
        }
        return $info;
    }

    public function isCache($domain) { // 查询域名是否存在缓存
        $redis = new redisCache('icp');
        $info = $redis->getData($domain); // 查询缓存数据
        return ($info) ? true : false;
    }

    public function icpInfo($domain) { // ICP查询入口 带缓存
        $redis = new redisCache('icp');
        $info = $redis->getData($domain); // 查询缓存数据
        if (!$info) { // 缓存未命中
            $info = $this->getIcpInfo($domain); // 执行查询
            if ($info['status'] !== 'ok') { // 查询错误
                return $info;
            }
            unset($info['status']);
            $redis->setData($domain, json_encode($info), 90 * 86400); // 缓存90day
        } else { // 缓存命中
            $info = json_decode($info, true); // 使用缓存数据
        }
        return array(
            'status' => 'ok'
        ) + $info;
    }
}

class icpQueryEntry {
    private function getIcpTlds() { // 获取所有可ICP备案的顶级域
        $db = new SqliteDB('./db/tldInfo.db');
        $punycode = new Punycode();
        $res = $db->query('SELECT tld FROM `icp`;');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $tlds[] = $punycode->encode($row['tld']); // 转为Punycode编码
        }
        return $tlds; // Unicode字符使用Punycode编码
    }

    private function isIcpEnable($tld) { // 检查TLD是否允许ICP备案
        $icpTlds = $this->getIcpTlds();
        foreach ($icpTlds as $icpTld) { // 遍历所有可ICP域名
            if ($icpTld === $tld) {
                return true; // 允许备案
            }
        }
        return false; // 无法备案
    }

    private function check($str) { // 检查输入参数
        $content = (new extractDomain)->analyse($str);
        if (!isset($content['domain'])) { // 格式错误
            return array(
                'status' => 'error',
                'message' => 'Illegal Request'
            );
        }
        if (!isset($content['tld'])) { // 未知TLD
            return array(
                'status' => 'error',
                'message' => 'Unknow TLD'
            );
        }
        if (!$this->isIcpEnable($content['tld'])) {
            return array(
                'status' => 'error',
                'message' => '`' . $content['tld'] . '` is not allowed in ICP'
            );
        }
        return array(
            'status' => 'ok',
            'domain' => $content['site'] // 返回主域名
        );
    }

    public function query($rawParam) { // ICP备案查询入口
        if ($rawParam == '' || $rawParam === 'help') { // 显示使用说明
            tgApi::sendMessage(array(
                'parse_mode' => 'Markdown',
                'text' => '*Usage:*  `/icp domain`',
            ));
            return;
        }
        $content = $this->check($rawParam);
        if ($content['status'] !== 'ok') { // 请求参数错误
            tgApi::sendMarkdown($content['message']);
            return;
        }
        $isCache = true;
        $domain = $content['domain'];
        $msg = '`' . (new Punycode)->decode($domain) . '`' . PHP_EOL;
        if (!(new icpQuery)->isCache($domain)) { // 域名信息未缓存
            $message = tgApi::sendMarkdown($msg . 'ICP备案信息查询中...');
            $message = json_decode($message, true);
            $isCache = false;
        }
        $info = (new icpQuery)->icpInfo($domain); // 发起查询
        if ($info['status'] !== 'ok') {
            if ($isCache) { // 没有缓冲信息 直接发送
                tgApi::sendText($info['message']); // 查询出错
            } else {
                tgApi::editMessage(array(
                    'text' => $info['message'],
                    'message_id' => $message['result']['message_id']
                ));
            }
            return;
        }
        if (!$info['hasIcp']) { // 没有ICP备案
            $content = array(
                'parse_mode' => 'Markdown',
                'text' => $msg . $info['icpMsg']
            );
            if ($isCache) { // 没有缓冲信息 直接发送
                tgApi::sendMessage($content);
            } else {
                tgApi::editMessage(array(
                    'message_id' => $message['result']['message_id']
                ) + $content);
            }
            return;
        }
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
        if ($isCache) { // 没有缓冲信息 直接发送
            tgApi::sendMarkdown($msg); // 返回查询数据
        } else {
            tgApi::editMessage(array( // 返回查询数据 修改原消息
                'parse_mode' => 'Markdown',
                'text' => $msg,
                'message_id' => $message['result']['message_id']
            ));
        }
        
    }
}

?>
