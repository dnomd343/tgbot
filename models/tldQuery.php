<?php

class tldQueryEntry { // TLD信息查询入口
    private $tldDB = './db/tldInfo.db'; // TLD信息数据库

    private function getTldInfo($tld) { // 获取TLD详细信息
        $db = new SqliteDB($this->tldDB);
        $res = $db->query('SELECT * FROM `iana` WHERE tld="' . $tld . '";');
        $info = $res->fetchArray(SQLITE3_ASSOC);
        if (!$info) { return null; } // 查无该TLD
        $info['manager'] = json_decode(base64_decode($info['manager']), true); // Base64解码 + JSON解码
        $info['admin_contact'] = json_decode(base64_decode($info['admin_contact']), true);
        $info['tech_contact'] = json_decode(base64_decode($info['tech_contact']), true);
        $info['nameserver'] = json_decode(base64_decode($info['nameserver']), true);
        $info['dnssec'] = json_decode(base64_decode($info['dnssec']), true);
        return $info;
    }

    private function genMessage($info, $icp, $subTld) { // 生成返回消息
        $msg = '`' . (new Punycode)->decode($info['tld']) . '` `(` `' . $info['type'];
        if ($info['active'] === 'yes') { // TLD是否有效解析
            $msg .= '` `)`' . PHP_EOL;
        } else {
            $msg .= ' not active` `)`' . PHP_EOL;
        }
        if (count($info['manager']) !== 0) { // 域名管理者信息
            $msg .= '*Manager*' . PHP_EOL;
            foreach ($info['manager']['name'] as $row) {
                $msg .= '  ' . $row . PHP_EOL;
            }
            foreach ($info['manager']['addr'] as $row) {
                $msg .= '  _' . $row . '_' . PHP_EOL;
            }
        }
        if (count($info['admin_contact']) !== 0) { // 域名管理员联系人
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
        if (count($info['tech_contact']) !== 0) { // 域名技术支持联系人
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
        if (count($info['nameserver']) !== 0) { // 域名NS服务器信息
            $nameserver = $info['nameserver'];
            $msg .= '*Name Servers*' . PHP_EOL;
            foreach ($nameserver as $host => $ips) {
                $msg .= '  `' . $host . '`' . PHP_EOL;
                foreach ($ips as $ip) {
                    $msg .= '    `' . $ip . '`' . PHP_EOL;
                }
            }
        }
        if ($subTld !== null) { // 输出次级顶级域
            $msg .= '*Sub TLD*' . PHP_EOL;
            foreach ($subTld as $tld) {
                $msg .= '  `' . (new Punycode)->decode($tld) . '`' . PHP_EOL;
            }
        }
        if (count($info['dnssec']) !== 0) { // 域名DNSSEC状态
            if ($info['dnssec']['type'] === 1) { // 正常DNSSEC
                $msg .= '*DNSSEC*' . PHP_EOL;
                foreach ($info['dnssec']['ds'] as $ds) {
                    $msg .= '  *Tag: ' . $ds['tag'] . '*' . PHP_EOL;
                    if (strlen($ds['hash']) === 64) { // SHA256
                        $msg .= '    `' . substr($ds['hash'], 0, 32) . '`' . PHP_EOL;
                        $msg .= '    `' . substr($ds['hash'], -32) . '`' . PHP_EOL;
                    } else if (strlen($ds['hash']) === 40){ // SHA1
                        $msg .= '    `' . substr($ds['hash'], 0, 32) . '`' . PHP_EOL;
                        $msg .= '    `' . substr($ds['hash'], -8) . '`' . PHP_EOL;
                    }
                    $msg .= '    Algorithm: _' . $ds['algorithm'] . ' ('; // 算法类型
                    $msg .= (new DNSSEC)->algorithmDesc($ds['algorithm']) . ')_' . PHP_EOL;
                    $msg .= '    Digest type: _' . $ds['digest'] . ' ('; // 摘要类型
                    $msg .= (new DNSSEC)->digestDesc($ds['digest']) . ')_' . PHP_EOL;                    
                }
            } else if ($info['dnssec']['type'] === 3) { // 启用DNSSEC 但未部署DS记录
                $msg .= '*DNSSEC:* signed, but without DS record.' . PHP_EOL;
            } else { // 未启用DNSSEC
                $msg .= '*DNSSEC:* unsigned' . PHP_EOL;
            }
        }
        if ($icp !== null) { // ICP备案信息
            $msg .= '*ICP Detail*' . PHP_EOL;
            $msg .= '  管理机构: ';
            if ($icp['org'] === '空') {
                $msg .= '未知' . PHP_EOL;
            } else {
                $msg .= $icp['org'] . PHP_EOL;
                $msg .= '  机构主页: ';
                foreach ($icp['site'] as $site) {
                    $msg .= '`' . $site . '` ';
                }
                $msg .= PHP_EOL;
            }
        }
        if ($info['website'] != '') { // 所有者主页
            $msg .= '*Website:* ' . $info['website'] . PHP_EOL;
        }
        if ($info['whois'] != '') { // Whois服务器信息
            $msg .= '*Whois Server:* `' . $info['whois'] . '`' . PHP_EOL;
        }
        $msg .= '*Registration date:* _' . $info['regist_date'] . '_' . PHP_EOL; // 注册日期
        $msg .= '*Record last updated:* _' . $info['last_updated'] . '_' . PHP_EOL; // 最后更改时间
        return $msg;
    }

    public function query($rawParam) { // TLD数据查询入口
        if ($rawParam == '' || $rawParam === 'help') {
            tgApi::sendMessage(array( // 发送使用说明
                'parse_mode' => 'Markdown',
                'text' => '*Usage:*  `/tld top-level-domain`',
            ));
            return;
        }
        if (substr($rawParam, 0, 1) !== '.') { // 补上.
            $rawParam = '.' . $rawParam;
        }
        $rawParam = (new Punycode)->encode($rawParam);
        $info = $this->getTldInfo($rawParam);
        if (!$info) { // 查无该TLD
            $rawParam = (new Punycode)->decode($rawParam);
            tgApi::sendMarkdown('`' . $rawParam . '`' . ' not found');
            return;
        }
        $icp = (new Domain)->icpTldInfo($rawParam); // 查询ICP信息
        $subTld = (new Domain)->getSubTld($rawParam); // 查询次级域信息
        tgApi::sendMessage(array(
            'text' => $this->genMessage($info, $icp, $subTld),
            'parse_mode' => 'Markdown', // Markdown格式输出
            'disable_web_page_preview' => 'true' // 不显示页面预览
        ));
    }
}

?>
