<?php

class DNS {
    public function resolveA($domain) { // DNS解析A记录
        $ipAddr = array();
        $rs = dns_get_record(strtolower($domain), DNS_A);
        foreach ($rs as $record) {
            $ipAddr[] = ip2long($record['ip']);
        }
        sort($ipAddr); // 解析结果排序
        foreach ($ipAddr as &$ip) {
            $ip = long2ip($ip);
        }
        return $ipAddr;
    }

    public function resolveAAAA($domain) { // DNS解析AAAA记录
        $ipAddr = array();
        $rs = dns_get_record(strtolower($domain), DNS_AAAA);
        foreach ($rs as $record) {
            $ipAddr[] = $this->ip2long6($record['ipv6']);
        }
        sort($ipAddr); // 解析结果排序
        foreach ($ipAddr as &$ip) {
            $ip = $this->long2ip6($ip);
        }
        return $ipAddr;
    }
    
    public function resolveIP($domain) { // DNS解析IP记录 A/AAAA
        $ipAddr = array();
        $ipv4 = $this->resolveA($domain);
        foreach ($ipv4 as $ip) {
            $ipAddr[] = $ip;
        }
        $ipv6 = $this->resolveAAAA($domain);
        foreach ($ipv6 as $ip) {
            $ipAddr[] = $ip;
        }
        return $ipAddr;
    }

    public function ip2long6($ipv6) { // 压缩IPv6地址为long
        $ip_n = inet_pton($ipv6);
        $bits = 15;
        while ($bits >= 0) {
          $bin = sprintf("%08b", (ord($ip_n[$bits])));
          $ipv6long = $bin.$ipv6long;
          $bits--;
        }
        return gmp_strval(gmp_init($ipv6long, 2), 10);
    }
      
    public function long2ip6($ipv6long) { // 解压long为IPv6地址
        $bin = gmp_strval(gmp_init($ipv6long, 10), 2);
        if (strlen($bin) < 128) {
            $pad = 128 - strlen($bin);
            for ($i = 1; $i <= $pad; $i++) {
                $bin = '0' . $bin;
            }
        }
        $bits = 0;
        while ($bits <= 7) {
            $bin_part = substr($bin, ($bits * 16), 16);
            $ipv6 .= dechex(bindec($bin_part)) . ':';
            $bits++;
        }
        return inet_ntop(inet_pton(substr($ipv6, 0, -1)));
    }
}

?>
