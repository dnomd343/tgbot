<?php

class whoisQuery { // Whois查询
    private function whois($content, $host = '') { // 执行Whois查询
        $cmd = $GLOBALS['env']['WHOIS_TOOL'] . ' ' . $content;
        if ($host != '') {
            $cmd .= ' -h ' . $host;
        }
        return shell_exec($cmd);
    }

    private function formatParam($param) { // 格式化输入参数
        if (strtoupper(substr($param, 0, 2)) === 'AS') {
            $asNum = substr($param, 2 - strlen($param));
            if (is_numeric($asNum)) { return 'AS' . $asNum; } // AS + 纯数字
        }
        if (filter_var($param, FILTER_VALIDATE_IP)) { // IP地址
            if(filter_var($param, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) { // IPv6地址
                $param = (new DNS)->zipIPv6($param); // 格式化IPv6地址
            }
            return $param; // IPv4 / IPv6
        }
        $info = (new Domain)->analyse($param);
        if (isset($info['site'])) { // 请求参数识别到有效域名
            return $info['site'];
        }
        return null;
    }

    private function getWhoisInfo($param) { // 发起whois查询 输入 AS+编号 / IP地址 / 网址
        $notFound = array(
            'No whois server is known for this kind of object.',
            'Unknown AS number or IP network. Please upgrade this program.'
        );
        if (strpos($param, 'AS') === 0 || filter_var($param, FILTER_VALIDATE_IP)) { // 请求内容为 AS编号 / IP地址
            $content = $this->whois($param);
            foreach ($notFound as $msg) { // 匹配错误信息
                if (trim($content) === $msg) {
                    $content = $this->whois($param, 'whois.iana.org'); // 手动查询IANA服务器
                }
            }
            return array(
                'param' => $param,
                'content' => $content
            );
        }
        $content = $this->whois($param); // 请求内容为域名
        if (trim($content) === 'No whois server is known for this kind of object.') { // 无内置whois服务器地址
            $domain = explode('.', $param);
            $tld = '.' . $domain[count($domain) - 1]; // 获取网址顶级域名
            $whoisServer = (new Domain)->getWhoisServer($tld); // 获取对应Whois服务器
            if ($whoisServer == null) {
                return array(
                    'param' => $domain,
                    'content' => 'TLD currently has no whois server' // 顶级域无Whois服务器
                );
            }
            $content = $this->whois($param, $whoisServer); // 手动向对应服务器查询
        }
        return array(
            'param' => $domain,
            'content' => $content
        );
    }

    public function whoisInfo($param) { // Whois查询接口 带缓存
        $param = $this->formatParam($param);
        if ($param == '') { return null; } // 输入错误
        // TODO: redis cache
        $info = $this->getWhoisInfo($param); // 发起查询
        return $info;
    }
}

class whoisQueryEntry {
    public function query($rawParam) {
        // TODO: help message / enshort whois info / splite long message
        $content = (new whoisQuery)->whoisInfo($rawParam)['content'];
        tgApi::sendMessage(array(
            'text' => $content,
            'disable_web_page_preview' => 'true' // 不显示页面预览
        ));
    }
}

?>
