<?php

class Curl {
    public $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36 Edg/92.0.902.67';

    public function get($url, $timeOut = 30) { // curl模拟Get 默认30s超时
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeOut);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->ua);
        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }
}

?>