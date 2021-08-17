<?php

class cfopPicEntry {
    private function getCfopMsg() {
        return array(
            'text' => 'CFOP魔方公式合集',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array(
                    array([
                        'text' => '网页下载',
                        'url' => 'https://res.dnomd343.top/Share/cfop/'
                    ]),
                    array([
                        'text' => '获取全部',
                        'callback_data' => '/cfop all'
                    ]),
                    array(
                        array(
                            'text' => 'GAN',
                            'callback_data' => '/cfop gan'
                        ),
                        array(
                            'text' => '魔方格',
                            'callback_data' => '/cfop mfg'
                        ),
                        array(
                            'text' => '裕鑫',
                            'callback_data' => '/cfop yx'
                        )
                    )
                )
            ))
        );
    }

    private function getPicId($type) { // 返回图片文件ID
        global $env;
        switch ($type) {
            case 'gan':
                return $env['CFOP_GAN'];
            case 'mfg':
                return $env['CFOP_MFG'];
            case 'yx':
                return $env['CFOP_YX'];
        }
    }

    private function getPic($type) { // 获取图片
        switch ($type) {
            case 'gan':
            case 'mfg':
            case 'yx':
                return array(
                    'document' => $this->getPicId($type)
                );
            case '':
                return $this->getCfopMsg();
            default:
                return array(
                    'text' => '未知公式'
                );
        }
    }

    private function sendCfopPic($params) { // 发送图片或信息
        if ($params['document']) {
            tgApi::sendDocument($params);
        } else {
            tgApi::sendMessage($params);
        }
    }

    public function query($rawParam) { // CFOP图片查询入口
        $this->sendCfopPic($this->getPic($rawParam));
    }

    public function callback($rawParam) { // CFOP图片回调入口
        if ($rawParam === 'all') {
            global $tgEnv;
            tgApi::deleteMessage(array( // 删除源消息
                'message_id' => $tgEnv['messageId']
            ));
            $this->sendCfopPic($this->getPic('gan'));
            $this->sendCfopPic($this->getPic('mfg'));
            $this->sendCfopPic($this->getPic('yx'));
            return;
        }
        $this->sendCfopPic($this->getPic($rawParam));
    }
}

?>
