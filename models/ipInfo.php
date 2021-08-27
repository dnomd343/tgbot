<?php

class ipInfo { // 查询IP详细信息
    private $apiPath;

    public function __construct() {
        $this->apiPath = 'https://' . $GLOBALS['env']['ECHOIP_HOST'] . '/info/';
    }

    private function changeCoor($num) { // 转为时分秒格式
        $stage_1 = floor($num);
        $stage_3 = ($num - $stage_1) * 60;
        $stage_2 = floor($stage_3);
        $stage_3 = round(($stage_3 - $stage_2) * 60);
        return $stage_1 . '°' . $stage_2 . '′' . $stage_3 . '"';
    }

    private function coorFormat($rawStr) { // 格式化经纬度字符串
        $lat = number_format(trim(explode(',', $rawStr)[0]), 8); // 纬度
        $str = '(' . $this->changeCoor(abs($lat));
        $str .= ($lat < 0) ? 'S' : 'N';
        $lon = number_format(trim(explode(',', $rawStr)[1]), 8); // 经度
        $str .= ', ' . $this->changeCoor(abs($lon));
        $str .= ($lon < 0) ? 'W' : 'E';
        $str .= ')';
        return $str;
    }

    private function getIpInfo($ip) { // 向上游查询IP信息
        $content = (new Curl)->get($this->apiPath . $ip);
        $info = json_decode($content, true);
        if ($info['status'] !== 'T') { return null; }
        unset($info['status']);
        return $info + array(
            'locCoor' => $this->coorFormat($info['loc']) // 经纬度格式
        );
    }

    public function getInfo($ip) { // 查询IP信息 错误返回null
        $redis = new RedisCache('ip');
        $info = $redis->getData($ip); // 查询缓存数据
        if (!$info) { // 缓存未命中
            $info = $this->getIpInfo($ip);
            if ($info === null) { return null; } // 服务错误 返回null
            $redis->setData($ip, json_encode($info), 43200); // 缓存12h
        } else { // 缓存命中
            $info = json_decode($info, true); // 使用缓存数据
        }
        return $info; // 查询成功 返回结果
    }
}

class ipInfoEntry { // IP信息查询入口
    private function genMessage($info) { // 生成返回信息
        $msg = '<b>IP:</b> <code>' . $info['ip'] . '</code>' . PHP_EOL;
        if ($info['as'] != NULL) {
            $msg .= '<b>AS:</b> <a href="https://bgpview.io/asn/' . substr($info['as'], 2) . '">';
            $msg .= $info['as'] . '</a>' . PHP_EOL;
        }
        if ($info['loc'] != NULL) {
            $msg .= '<b>Location:</b> <a href="https://earth.google.com/web/@';
            $msg .= $info['loc'] . ',9.963a,7999.357d,35y,-34.3h,45t,0r/data=KAI">';
            $msg .= $info['locCoor'] . '</a>' . PHP_EOL;
        }
        if ($info['city'] != NULL) { $msg .= '<b>City:</b> ' . $info['city'] . PHP_EOL; }
        if ($info['region'] != NULL) { $msg .= '<b>Region:</b> ' . $info['region'] . PHP_EOL; }
        if ($info['country'] != NULL) { $msg .= '<b>Country:</b> ' . $info['country'] . PHP_EOL; }
        if ($info['timezone'] != NULL) { $msg .= '<b>Timezone:</b> ' . $info['timezone'] . PHP_EOL; }
        if ($info['isp'] != NULL) { $msg .= '<b>ISP:</b> ' . $info['isp'] . PHP_EOL; }
        if ($info['scope'] != NULL) { $msg .= '<b>Scope:</b> <code>' . $info['scope'] . '</code>' . PHP_EOL; }
        if ($info['detail'] != NULL) { $msg .= '<b>Detail:</b> ' . $info['detail'] . PHP_EOL; }
        return array(
            'text' => $msg,
            'parse_mode' => 'HTML', // HTML格式输出
            'disable_web_page_preview' => 'true', // 不显示页面预览
            'reply_markup' => $this->genButton('View on echoIP', $info['ip']) // 显示按钮
        );
    }

    private function genButton($text, $ip = '') { // 生成ehcoIP页面链接按钮 默认为主页
        $url = 'https://' . $GLOBALS['env']['ECHOIP_HOST'] . '/';
        if ($ip !== '') { $url .= '?ip=' . $ip; }
        return json_encode(array(
            'inline_keyboard' => array([[ // echoIP按钮
                'text' => $text,
                'url' => $url
            ]])
        ));
    }

    private function sendInfo($ip) { // 查询并发送IP信息
        $info = (new ipInfo)->getInfo($ip);
        if ($info === null) {
            tgApi::sendText('Server error'); // 上游查询错误
            return;
        }
        tgApi::sendMessage($this->genMessage($info));
    }

    private function sendDomainInfo($domain) { // 查询并发送域名解析结果
        $ips = (new DNS)->resolveIP($domain);
        if (count($ips) == 0) { // 解析不到IP记录
            tgApi::sendMarkdown('Nothing found of `' . $domain . '`');
            return;
        }
        foreach ($ips as $ip) {
            $buttons[] = array([ // 生成按钮列表
                'text' => $ip,
                'callback_data' => '/ip ' . $ip
            ]);
        }
        if (count($ips) >= 2) {
            $buttons[] = array([ // 两个及以上的IP 添加显示全部的按钮
                'text' => 'Get all detail',
                'callback_data' => '/ip ' . $domain
            ]);
        }
        tgApi::sendMessage(array(
            'parse_mode' => 'Markdown',
            'text' => 'DNS resolve of `' . $domain . '`',
            'reply_markup' => json_encode(array( // IP列表按钮
                'inline_keyboard' => $buttons
            ))
        ));
    }

    private function sendHelp() { // 发送使用说明
        $helpMessage = array(
            'parse_mode' => 'Markdown',
            'text' => '*Usage:*  `/ip IPv4/IPv6/Domain`',
            'reply_markup' => $this->genButton('Get my IP address') // echoIP主页链接
        );
        tgApi::sendMessage($helpMessage);
    }

    public function query($rawParam) { // ipInfo查询入口
        if ($rawParam === 'help') {
            $this->sendHelp(); // 显示使用说明
        } else if ($rawParam == '') {
            if ($GLOBALS['tgEnv']['isGroup']) { // 此时为群组
                $this->sendHelp(); // 显示使用说明
            } else {
                tgReply::add('/ip');
                tgApi::sendText('Please send the IP / Domain');
            }
        } else if (filter_var($rawParam, FILTER_VALIDATE_IP)) { // 参数为IP地址
            $this->sendInfo($rawParam); // 查询并发送IP信息
        } else if ((new Domain)->isDomain($rawParam)) { // 参数为域名
            $this->sendDomainInfo($rawParam); // 查询并发送域名信息
        } else {
            tgApi::sendText('Illegal Request'); // 非法请求
        }
    }

    public function callback($rawParam) { // ipInfo回调入口
        if (filter_var($rawParam, FILTER_VALIDATE_IP)) { // 参数为IP地址
            $this->query($rawParam);
        } else { // 参数为域名
            tgApi::deleteMessage(array(
                'message_id' => $GLOBALS['tgEnv']['messageId']
            ));
            $ips = (new DNS)->resolveIP($rawParam);
            foreach ($ips as $ip) {
                $this->query($ip); // 逐个输出
            }
        }
    }
}

?>
