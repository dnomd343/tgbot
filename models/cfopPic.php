<?php

class cfopPicEntry { // 获取CFOP公式图片
    private function sendMenu() { // 发送菜单
        $buttons = array(
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
        );
        tgApi::sendMessage(array(
            'text' => 'CFOP魔方公式合集',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => $buttons
            ))
        ));
    }

    private function sendCfopPic($type) { // 发送图片
        global $env;
        if ($type === 'gan') {
            $picId = $env['CFOP_GAN'];
        } else if ($type === 'mfg') {
            $picId = $env['CFOP_MFG'];
        } else if ($type === 'yx') {
            $picId = $env['CFOP_YX'];
        } else if ($type === '' || $type === 'help') {
            $this->sendMenu();
        } else {
            tgApi::sendText('未知公式');
        }
        if (isset($picId)) {
            tgApi::sendDocument(array(
                'document' => $picId
            ));
        }
    }

    public function query($rawParam) { // CFOP图片查询入口
        $this->sendCfopPic($rawParam);
    }

    public function callback($rawParam) { // CFOP图片回调入口
        if ($rawParam !== 'all') {
            $this->sendCfopPic($rawParam);
            return;
        }
        $this->sendCfopPic('gan');
        $this->sendCfopPic('mfg');
        $this->sendCfopPic('yx');
        tgApi::deleteMessage(array( // 删除源消息
            'message_id' => $GLOBALS['tgEnv']['messageId']
        ));
    }
}

?>
