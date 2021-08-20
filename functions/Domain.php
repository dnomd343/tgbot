<?php

class Domain { // 域名相关功能
    public function isDomain($domain) { // 检测是否为域名
        preg_match('/^(?=^.{3,255}$)[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+$/', $domain, $match);
        return (count($match) != 0);
    }
}

class extractDomain {
    private $tldDB = './db/tldInfo.db'; // 顶级域名数据库

    private function getAllTlds() { // 获取所有顶级域 含次级域
        $db = new SqliteDB($this->tldDB);
        $res = $db->query('SELECT record FROM `list`;');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $tlds[] = $row['record'];
        }
        return $tlds; // Unicode字符使用Punycode编码
    }

    private function isDomain($domain) { // 检测是否为域名
        preg_match('/^(?=^.{3,255}$)[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+$/', $domain, $match);
        return (count($match) != 0);
    }

    private function getDomain($url) { // 从URL获取域名
        $url = preg_replace('/^[\w]+:\/\//', '', $url); // 去除协议字段
        $url = explode('?', $url)[0]; // 去除请求参数内容
        $domain = explode('/', $url)[0]; // 分离域名
        return (new Punycode)->encode($domain);
    }

    private function getTld($domain) { // 搜索域名TLD
        $tlds = $this->getAllTlds(); // 获取TLD列表
        foreach ($tlds as $tld) {
            if (substr($domain, -strlen($tld)) === $tld) { // 匹配测试
                $target[] = $tld;
            }
        }
        if (count($target) === 0) {
            return ''; // 匹配不到TLD
        };
        $type = 0;
        foreach ($target as $tld) { // 遍历可能的结果
            $num = substr_count($tld, '.');
            if ($type < $num) { // 获取.个数最多的
                $type = $num;
                $result = $tld;
            }
        }
        return $result; // 返回网站顶级域名
    }

    private function getSite($domain, $tld) { // 获取主域名
        $domain = explode('.', $domain);
        $num = count($domain) - substr_count($tld, '.');
        return $domain[$num - 1] . $tld;
    }

    public function analyse($url) { // 分析域名信息
        $domain = $this->getDomain($url);
        if (!$this->isDomain($domain)) { // 域名不合格
            return array();
        }
        $tld = $this->getTld($domain);
        if ($tld == '') { // 匹配不到TLD
            return array(
                'domain' => $domain
            );
        }
        return array(
            'domain' => $domain,
            'tld' => $tld,
            'site' => $this->getSite($domain, $tld)
        );
    }
}

?>