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
        $info['dnssec'] = json_decode(base64_decode($info['dnssec']), true);
        return $info;
    }

    private function genMessage($info) { // 生成返回消息
        $msg = '`' . $info['tld'] . '` `(` `' . $info['type'];
        if ($info['active'] === 'yes') {
            $msg .= '` `)`' . PHP_EOL;
        } else {
            $msg .= ' not active` `)`' . PHP_EOL;
        }
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
        if (count($info['dnssec']) !== 0) {
            if ($info['dnssec']['type'] === 1) { // 正常DNSSEC
                $msg .= '*DNSSEC*' . PHP_EOL;
                foreach ($info['dnssec']['ds'] as $ds) {
                    $msg .= '  *Tag: ' . $ds['tag'] . '*' . PHP_EOL;
                    if (strlen($ds['hash']) === 64) {
                        $msg .= '    `' . substr($ds['hash'], 0, 32) . '`' . PHP_EOL;
                        $msg .= '    `' . substr($ds['hash'], -32) . '`' . PHP_EOL;
                    } else if (strlen($ds['hash']) === 40){
                        $msg .= '    `' . substr($ds['hash'], 0, 32) . '`' . PHP_EOL;
                        $msg .= '    `' . substr($ds['hash'], -8) . '`' . PHP_EOL;
                    }
                    $msg .= '    Algorithm: _' . $ds['algorithm'];
                    if ($ds['algorithm'] === '1') {
                        $msg .= ' (RSA/MD5)';
                    } else if ($ds['algorithm'] === '3') {
                        $msg .= ' (DSA/SHA1)';
                    } else if ($ds['algorithm'] === '5') {
                        $msg .= ' (RSA/SHA-1)';
                    } else if ($ds['algorithm'] === '6') {
                        $msg .= ' (DSA-NSEC3-SHA1)';
                    } else if ($ds['algorithm'] === '7') {
                        $msg .= ' (RSASHA1-NSEC3-SHA1)';
                    } else if ($ds['algorithm'] === '8') {
                        $msg .= ' (RSA/SHA-256)';
                    } else if ($ds['algorithm'] === '10') {
                        $msg .= ' (RSA/SHA-512)';
                    } else if ($ds['algorithm'] === '12') {
                        $msg .= ' (GOST R 34.10-2001)';
                    } else if ($ds['algorithm'] === '13') {
                        $msg .= ' (ECDSA Curve P-256 with SHA-256)';
                    } else if ($ds['algorithm'] === '14') {
                        $msg .= ' (ECDSA Curve P-384 with SHA-384)';
                    } else if ($ds['algorithm'] === '15') {
                        $msg .= ' (Ed25519)';
                    } else if ($ds['algorithm'] === '16') {
                        $msg .= ' (Ed448)';
                    }
                    $msg .= '_' . PHP_EOL;
                    $msg .= '    Digest type: _' . $ds['digest'];
                    if ($ds['digest'] === '1') {
                        $msg .= ' (SHA-1)';
                    } else if ($ds['digest'] === '2') {
                        $msg .= ' (SHA-256)';
                    }
                    $msg .= '_' . PHP_EOL;                    
                }
            } else if ($info['dnssec']['type'] === 3) { // 启用DNSSEC 但未部署DS记录
                $msg .= '*DNSSEC:* signed, but without DS record.' . PHP_EOL;
            } else { // 未启用DNSSEC
                $msg .= '*DNSSEC:* unsigned' . PHP_EOL;
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
