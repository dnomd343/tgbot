<?php

class Curl { // Curl模拟http请求
    public $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36 Edg/92.0.902.67';

    public function get($url, $timeOut = 30) { // curl模拟Get 默认30s超时
        return $this->run(array(
            [CURLOPT_URL, $url],
            [CURLOPT_RETURNTRANSFER, 1],
            [CURLOPT_CONNECTTIMEOUT, $timeOut],
            [CURLOPT_USERAGENT, $this->ua]
        ));
    }

    public function post($url, $data, $timeOut = 30) { // curl模拟Post 默认30s超时
        return $this->run(array(
            [CURLOPT_URL, $url],
            [CURLOPT_RETURNTRANSFER, 1],
            [CURLOPT_CONNECTTIMEOUT, $timeOut],
            [CURLOPT_USERAGENT, $this->ua],
            [CURLOPT_POST, 1],
            [CURLOPT_POSTFIELDS, $data]
        ));
    }

    private function run($configs) { // 发起curl请求
        $curl = curl_init();
        foreach ($configs as $config) {
            curl_setopt($curl, $config[0], $config[1]);
        }
        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }
}

?>