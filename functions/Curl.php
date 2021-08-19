<?php

class Curl {
    public function get($url, $timeOut = 30) { // curl模拟Get 默认30s超时
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeOut);
        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }
}

?>