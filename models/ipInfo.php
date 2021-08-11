<?php

class ipInfo {
    private $apiPath = 'https://api.343.re/ip/';

    public function isDomain($domain) { // 检测是否为域名
        preg_match('/^(?=^.{3,255}$)[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+$/', $domain, $match);
        return (count($match) != 0);
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

    private function dnsResolveIPv4($domain) { // DNS解析A记录
        $ipAddr = array();
        $rs = dns_get_record(strtolower($domain), DNS_A);
        foreach ($rs as $record) {
            $ipAddr[] = $record['ip'];
        }
        return $ipAddr;
    }
    
    private function dnsResolveIPv6($domain) { // DNS解析AAAA记录
        $ipAddr = array();
        $rs = dns_get_record(strtolower($domain), DNS_AAAA);
        foreach ($rs as $record) {
            $ipAddr[] = $record['ipv6'];
        }
        return $ipAddr;
    }
    
    public function dnsResolve($domain) { // DNS解析IP记录
        $ipAddr = array();
        $ipv4 = $this->dnsResolveIPv4($domain);
        foreach ($ipv4 as $ip) {
            $ipAddr[] = $ip;
        }
        $ipv6 = $this->dnsResolveIPv6($domain);
        foreach ($ipv6 as $ip) {
            $ipAddr[] = $ip;
        }
        return $ipAddr;
    }

    public function getInfo($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) { // IP地址不合法
            return array(
                'text' => 'Illegal IP Address'
            );
        }
        $info = json_decode(file_get_contents($this->apiPath . $ip), true);
        if ($info['status'] !== 'T') { // 上游接口错误
            return array(
                'text' => 'Server Error'
            );
        }
        $msg = '<b>IP:</b> <code>' . $info['ip'] . '</code>' . PHP_EOL;
        if ($info['as'] != NULL) {
            $msg .= '<b>AS:</b> <a href="https://bgpview.io/asn/' . substr($info['as'], 2) . '">';
            $msg .= $info['as'] . '</a>' . PHP_EOL;
        }
        if ($info['loc'] != NULL) {
            $msg .= '<b>Location:</b> <a href="https://earth.google.com/web/@';
            $msg .= $info['loc'] . ',9.963a,7999.357d,35y,-34.3h,45t,0r/data=KAI">';
            $msg .= $this->coorFormat($info['loc']) . '</a>' . PHP_EOL;
        }
        if ($info['city'] != NULL) { $msg .= '<b>City:</b> ' . $info['city'] . PHP_EOL; }
        if ($info['region'] != NULL) { $msg .= '<b>Region:</b> ' . $info['region'] . PHP_EOL; }
        if ($info['country'] != NULL) { $msg .= '<b>Country:</b> ' . $info['country'] . PHP_EOL; }
        if ($info['timezone'] != NULL) { $msg .= '<b>Timezone:</b> ' . $info['timezone'] . PHP_EOL; }
        if ($info['isp'] != NULL) { $msg .= '<b>ISP:</b> ' . $info['isp'] . PHP_EOL; }
        if ($info['scope'] != NULL) { $msg .= '<b>Scope:</b> <code>' . $info['scope'] . '</code>' . PHP_EOL; }
        if ($info['detail'] != NULL) { $msg .= '<b>Detail:</b> ' . $info['detail'] . PHP_EOL; }
        return array(
            'parse_mode' => 'HTML', // HTML格式输出
            'disable_web_page_preview' => 'true', // 不显示页面预览
            'text' => $msg,
            'reply_markup' => json_encode(array( // 显示按钮
                'inline_keyboard' => array([[
                    'text' => 'View on echoIP',
                    'url' => 'https://ip.dnomd343.top/?ip=' . $ip
                ]])
            ))
        );
    }
}

function ipInfo($rawParam) { // IP查询入口
    global $chatId;
    if ($rawParam == '' || $rawParam === 'help') { // 显示使用说明
        sendMessage($chatId, array(
            'parse_mode' => 'Markdown',
            'text' => '*Usage:*  `/ip IPv4/IPv6/Domain`',
            'reply_markup' => json_encode(array( // 显示echoIP按钮
                'inline_keyboard' => array([[
                    'text' => 'Get my IP address',
                    'url' => 'https://ip.dnomd343.top/'
                ]])
            ))
        ));
        return;
    }
    if (filter_var($rawParam, FILTER_VALIDATE_IP)) { // 参数为IP地址
        sendMessage($chatId, (new ipInfo)->getInfo($rawParam));
        return;
    }
    if ((new ipInfo)->isDomain($rawParam)) {
        $ips = (new ipInfo)->dnsResolve($rawParam);
        if (count($ips) == 0) { // 解析不到IP记录
            sendMessage($chatId,  array(
                'parse_mode' => 'Markdown',
                'text' => 'Nothing found of `' . $rawParam . '`'
            ));
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
                'callback_data' => '/ip ' . $rawParam
            ]);
        }
        sendMessage($chatId,  array(
            'parse_mode' => 'Markdown',
            'text' => 'DNS resolve of `' . $rawParam . '`',
            'reply_markup' => json_encode(array( // 显示IP列表按钮
                'inline_keyboard' => $buttons
            ))
        ));
    }
}

function ipInfoCallback($rawParam) { // IP查询回调入口
    global $chatId;
    $content = explode(',', $rawParam);
    if (filter_var($rawParam, FILTER_VALIDATE_IP)) { // 参数为IP地址
        ipInfo($rawParam);
    } else { // 参数为域名
        $ips = (new ipInfo)->dnsResolve($rawParam);
        foreach ($ips as $ip) {
            ipInfo($ip); // 逐个输出
        }
    }
}

?>