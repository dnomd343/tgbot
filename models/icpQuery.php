<?php

class icpQuery {
    private $apiPath = 'https://apidatav2.chinaz.com/single/icp';

    public function __construct() {
        global $env;
        $this->apiPath .= '?key=' . $env['ICP_KEY'] . '&domain=';
    }

    private function getIcpInfo($domain) { // ICP备案查询
        $info = json_decode(file_get_contents($this->apiPath . $domain), true);
        if ($info['StateCode'] === 1) { // 存在ICP备案
            $info = array(
                'status' => 'ok',
                'hasIcp' => true
            ) + $info['Result'];
        } else if ($info['StateCode'] === 0) { // 无ICP备案
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

    public function icpInfo($domain) { // ICP查询入口 带缓存
        $redis = new redisCache('icp');
        $info = $redis->getData($domain); // 查询缓存数据
        if (!$info) { // 缓存未命中
            $info = $this->getIcpInfo($domain); // 执行查询
            if ($info['status'] !== 'ok') { // 查询错误
                return $info;
            }
            unset($info['status']);
            $redis->setData($domain, json_encode($info), 30 * 86400); // 缓存30day
        } else { // 缓存命中
            $info = json_decode($info, true); // 使用缓存数据
        }
        return array(
            'status' => 'ok'
        ) + $info;
    }
}

class icpQueryEntry {
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
            tgApi::sendText($content['message']);
            return;
        }
        $domain = $content['domain'];
        $info = (new icpQuery)->icpInfo($domain); // 发起查询
        if ($info['status'] !== 'ok') {
            tgApi::sendText($info['message']); // 查询出错
            return;
        }
        if (!$info['hasIcp']) { // 没有ICP备案
            tgApi::sendMessage(array(
                'parse_mode' => 'Markdown',
                'text' => '`' . $domain . '`' . PHP_EOL . $info['icpMsg']
            ));
            return;
        }
        $msg = '`' . $domain . '`' . PHP_EOL;
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
        tgApi::sendMessage(array( // 返回查询数据
            'parse_mode' => 'Markdown',
            'text' => $msg
        ));
    }
}

?>
