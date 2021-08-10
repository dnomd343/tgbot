<?php

class cfopPic {
    private function picList() {
        return array(
            'text' => 'CFOP魔方公式合集',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array(
                    array(
                        array(
                            'text' => '网页下载',
                            'url' => 'https://res.dnomd343.top/Share/cfop/'
                        )
                    ),
                    array(
                        array(
                            'text' => '获取全部',
                            'callback_data' => '/cfop all'
                        )
                    ),
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

    private function sendPic($type) { // 返回图片文件ID
        switch ($type) {
            case 'gan':
                $fileId = 'BQACAgUAAxkBAAIBtGEOLnr4Q6D4Z_80bgfXq5xsZMeWAAKtAwACWy55VOU-SGKqc7aMIAQ';
                break;
            case 'mfg':
                $fileId = 'BQACAgUAAxkBAAIB3WEOVHKeYrrGhFo-GffB0W-tQRKlAALQAwACWy55VGny8ArGMkfoIAQ';
                break;
            case 'yx':
                $fileId = 'BQACAgUAAxkBAAIB32EOVISFQbgmir2abj6QkgqaSX1WAALRAwACWy55VMEuU9lCYTYWIAQ';
                break;
        }
        return array(
            'document' => $fileId
        );
    }

    public function getPic($type) {
        switch ($type) {
            case 'gan':
            case 'mfg':
            case 'yx':
                return $this->sendPic($type);
            case '':
                return $this->picList();
            default:
                return array(
                    'text' => '未知公式'
                );
        }
    }
}

function cfopPic($rawParam) { // 发送CFOP图片入口
    global $chatId;
    sendAuto($chatId, (new cfopPic)->getPic($rawParam));
}

function cfopPicCallback($rawParam) { // 发送CFOP图片回调入口
    global $chatId, $messageId;
    if ($rawParam === 'all') {
        sendAuto($chatId, (new cfopPic)->getPic('gan'));
        sendAuto($chatId, (new cfopPic)->getPic('mfg'));
        sendAuto($chatId, (new cfopPic)->getPic('yx'));
        sendPayload(array( // 删除源消息
            'method' => 'deleteMessage',
            'chat_id' => $chatId,
            'message_id' => $messageId
        ));
        return;
    }
    sendAuto($chatId, (new cfopPic)->getPic($rawParam));
}

?>
