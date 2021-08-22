<?php

class punycodeEntry { // Punycode编码转换入口
    private function checkErr($punycode) { // 处理Punycode库错误警告
        if ($punycode->errFlag) {
            return array(
                'status' => 'error',
                'message' => $punycode->errMsg
            );
        } else {
            return array(
                'status' => 'ok'
            );
        }
    }

    private function encode($str) { // Punycode编码
        $punycode = new Punycode;
        $response['data'] = $punycode->encode($str);
        return $this->checkErr($punycode) + $response;
    }

    private function decode($str) { // Punycode解码
        $punycode = new Punycode;
        $response['data'] = $punycode->decode($str);
        return $this->checkErr($punycode) + $response;
    }

    public function query($rawParam) { // Punycode转换查询入口
        $encode = $this->encode($rawParam);
        $decode = $this->decode($rawParam);
        if ($decode['data'] === strtolower($rawParam)) { // 输入为明文
            $msg = '`' . $encode['data'] . '`' . PHP_EOL;
            if ($encode['status'] !== 'ok') {
                $errMsg = $encode['message'];
            }
        } else { // 输入为编码
            $msg = '`' . $decode['data'] . '`' . PHP_EOL;
            if ($decode['status'] !== 'ok') {
                $errMsg = $decode['message'];
            }
        }
        if (isset($errMsg)) { // 存在警告
            if (substr($rawParam, 0, 1) !== '.' && substr($rawParam, -1) !== '.') { // 首尾不为.时发送警告内容
                $msg .= '*Warning:* ' . $errMsg;
            }
        }
        tgApi::sendMarkdown($msg);
    }
}

?>