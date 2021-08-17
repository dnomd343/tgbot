<?php

class tldQueryEntry {
    private $tldDB = './db/tldInfo.db';

    private function getTldInfo($tld) {
        $db = new SqliteDB($this->tldDB);
        $res = $db->query('SELECT * FROM `iana` WHERE tld="' . $tld . '";');
        $info = $res->fetchArray(SQLITE3_ASSOC);
        if (!$info) { return null; }
        $info['manager'] = json_decode(base64_decode($info['manager']), true);
        $info['admin_contact'] = json_decode(base64_decode($info['admin_contact']), true);
        $info['tech_contact'] = json_decode(base64_decode($info['tech_contact']), true);
        $info['nameserver'] = json_decode(base64_decode($info['nameserver']), true);
        return $info;
    }

    private function genMessage($info) { // 生成返回消息
        $msg = '`' . $info['tld'] . '` `(` `' . $info['type'] . '` `)`' . PHP_EOL;
        if (count($info['manager']) !== 0) {
            $msg .= '*Manager*' . PHP_EOL;
            foreach ($info['manager']['name'] as $row) {
                $msg .= '  ' . $row . PHP_EOL;
            }
            foreach ($info['manager']['addr'] as $row) {
                $msg .= '  _' . $row . '_' . PHP_EOL;
            }
        }
        if (count($info['admin_contact']) !== 0) {
            $contact = $info['admin_contact'];
            $msg .= '*Administrative Contact*' . PHP_EOL;
            $msg .= '  ' . $contact['name'] . PHP_EOL;
            $msg .= '  ' . $contact['org'] . PHP_EOL;
            foreach ($contact['addr'] as $row) {
                $msg .= '  _' . $row . '_' . PHP_EOL;
            }
            if ($contact['email'] != '') {
                $msg .= '  Email: _' . $contact['email'] . '_' . PHP_EOL;
            }
            if ($contact['voice'] != '') {
                $msg .= '  Voice: _' . $contact['voice'] . '_' . PHP_EOL;
            }
            if ($contact['fax'] != '') {
                $msg .= '  Fax: _' . $contact['fax'] . '_' . PHP_EOL;
            }
        }
        if (count($info['tech_contact']) !== 0) {
            $contact = $info['tech_contact'];
            $msg .= '*Technical Contact*' . PHP_EOL;
            $msg .= '  ' . $contact['name'] . PHP_EOL;
            $msg .= '  ' . $contact['org'] . PHP_EOL;
            foreach ($contact['addr'] as $row) {
                $msg .= '  _' . $row . '_' . PHP_EOL;
            }
            if ($contact['email'] != '') {
                $msg .= '  Email: _' . $contact['email'] . '_' . PHP_EOL;
            }
            if ($contact['voice'] != '') {
                $msg .= '  Voice: _' . $contact['voice'] . '_' . PHP_EOL;
            }
            if ($contact['fax'] != '') {
                $msg .= '  Fax: _' . $contact['fax'] . '_' . PHP_EOL;
            }
        }
        if (count($info['nameserver']) !== 0) {
            $nameserver = $info['nameserver'];
            $msg .= '*Name Servers*' . PHP_EOL;
            foreach ($nameserver as $host => $ips) {
                $msg .= '  `' . $host . '`' . PHP_EOL;
                foreach ($ips as $ip) {
                    $msg .= '    `' . $ip . '`' . PHP_EOL;
                }
            }
        }
        if ($info['website'] != '') {
            $msg .= '*Website:* ' . $info['website'] . PHP_EOL;
        }
        if ($info['whois'] != '') {
            $msg .= '*Whois Server:* `' . $info['whois'] . '`' . PHP_EOL;
        }
        $msg .= '*Registration date:* _' . $info['regist_date'] . '_' . PHP_EOL;
        $msg .= '*Record last updated:* _' . $info['last_updated'] . '_' . PHP_EOL;
        return $msg;
    }

    public function query($rawParam) { // TLD数据查询入口
        if (substr($rawParam, 0, 1) !== '.') { // 补上.
            $rawParam = '.' . $rawParam;
        }
        $info = $this->getTldInfo($rawParam);
        if (!$info) {
            tgApi::sendMarkdown('`' . $rawParam . '`' . PHP_EOL . 'TLD not found');
            return;
        }
        tgApi::sendMessage(array(
            'text' => $this->genMessage($info),
            'parse_mode' => 'Markdown', // Markdown格式输出
            'disable_web_page_preview' => 'true' // 不显示页面预览
        ));
    }
}

?>
