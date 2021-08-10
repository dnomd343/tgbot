<?php

class whoisQuery {
    public function query($domain) {
        $temp = explode('.', $domain);
        $tld = $temp[count($temp) - 1];
        $servers = array(
            "com" => "whois.verisign-grs.com",
            "net" => "whois.verisign-grs.com",
            "org" => "whois.pir.org",
            "info" => "whois.afilias.info",
            "biz" => "whois.neulevel.biz",
            "us" => "whois.nic.us",
            "uk" => "whois.nic.uk",
            "ca" => "whois.cira.ca",
            "tel" => "whois.nic.tel",
            "ie" => "whois.iedr.ie",
            "it" => "whois.nic.it",
            "li" => "whois.nic.li",
            "no" => "whois.norid.no",
            "cc" => "whois.nic.cc",
            "eu" => "whois.eu",
            "nu" => "whois.nic.nu",
            "au" => "whois.aunic.net",
            "de" => "whois.denic.de",
            "ws" => "whois.worldsite.ws",
            "sc" => "whois2.afilias-grs.net",
            "mobi" => "whois.dotmobiregistry.net",
            "pro" => "whois.registrypro.pro",
            "edu" => "whois.educause.net",
            "tv" => "whois.nic.tv",
            "travel" => "whois.nic.travel",
            "name" => "whois.nic.name",
            "in" => "whois.inregistry.net",
            "me" => "whois.nic.me",
            "at" => "whois.nic.at",
            "be" => "whois.dns.be",
            "cn" => "whois.cnnic.cn",
            "asia" => "whois.nic.asia",
            "ru" => "whois.ripn.ru",
            "ro" => "whois.rotld.ro",
            "aero" => "whois.aero",
            "fr" => "whois.nic.fr",
            "se" => "whois.iis.se",
            "nl" => "whois.sidn.nl",
            "nz" => "whois.srs.net.nz",
            "mx" => "whois.nic.mx",
            "tw" => "whois.apnic.net",
            "ch" => "whois.nic.ch",
            "hk" => "whois.hknic.net.hk",
            "ac" => "whois.nic.ac",
            "ae" => "whois.nic.ae",
            "af" => "whois.nic.af",
            "ag" => "whois.nic.ag",
            "al" => "whois.ripe.net",
            "am" => "whois.amnic.net",
            "as" => "whois.nic.as",
            "az" => "whois.ripe.net",
            "ba" => "whois.ripe.net",
            "bg" => "whois.register.bg",
            "bi" => "whois.nic.bi",
            "bj" => "www.nic.bj",
            "br" => "whois.nic.br",
            "bt" => "whois.netnames.net",
            "by" => "whois.ripe.net",
            "bz" => "whois.belizenic.bz",
            "cd" => "whois.nic.cd",
            "ck" => "whois.nic.ck",
            "cl" => "nic.cl",
            "coop" => "whois.nic.coop",
            "cx" => "whois.nic.cx",
            "cy" => "whois.ripe.net",
            "cz" => "whois.nic.cz",
            "dk" => "whois.dk-hostmaster.dk",
            "dm" => "whois.nic.cx",
            "dz" => "whois.ripe.net",
            "ee" => "whois.eenet.ee",
            "eg" => "whois.ripe.net",
            "es" => "whois.ripe.net",
            "fi" => "whois.ficora.fi",
            "fo" => "whois.ripe.net",
            "gb" => "whois.ripe.net",
            "ge" => "whois.ripe.net",
            "gl" => "whois.ripe.net",
            "gm" => "whois.ripe.net",
            "gov" => "whois.nic.gov",
            "gr" => "whois.ripe.net",
            "gs" => "whois.adamsnames.tc",
            "hm" => "whois.registry.hm",
            "hn" => "whois2.afilias-grs.net",
            "hr" => "whois.ripe.net",
            "hu" => "whois.ripe.net",
            "il" => "whois.isoc.org.il",
            "int" => "whois.isi.edu",
            "iq" => "vrx.net",
            "ir" => "whois.nic.ir",
            "is" => "whois.isnic.is",
            "je" => "whois.je",
            "jp" => "whois.jprs.jp",
            "kg" => "whois.domain.kg",
            "kr" => "whois.nic.or.kr",
            "la" => "whois2.afilias-grs.net",
            "lt" => "whois.domreg.lt",
            "lu" => "whois.restena.lu",
            "lv" => "whois.nic.lv",
            "ly" => "whois.lydomains.com",
            "ma" => "whois.iam.net.ma",
            "mc" => "whois.ripe.net",
            "md" => "whois.nic.md",
            "mil" => "whois.nic.mil",
            "mk" => "whois.ripe.net",
            "ms" => "whois.nic.ms",
            "mt" => "whois.ripe.net",
            "mu" => "whois.nic.mu",
            "my" => "whois.mynic.net.my",
            "nf" => "whois.nic.cx",
            "pl" => "whois.dns.pl",
            "pr" => "whois.nic.pr",
            "pt" => "whois.dns.pt",
            "sa" => "saudinic.net.sa",
            "sb" => "whois.nic.net.sb",
            "sg" => "whois.nic.net.sg",
            "sh" => "whois.nic.sh",
            "si" => "whois.arnes.si",
            "sk" => "whois.sk-nic.sk",
            "sm" => "whois.ripe.net",
            "st" => "whois.nic.st",
            "su" => "whois.ripn.net",
            "tc" => "whois.adamsnames.tc",
            "tf" => "whois.nic.tf",
            "th" => "whois.thnic.net",
            "tj" => "whois.nic.tj",
            "tk" => "whois.nic.tk",
            "tl" => "whois.domains.tl",
            "tm" => "whois.nic.tm",
            "tn" => "whois.ripe.net",
            "to" => "whois.tonic.to",
            "tp" => "whois.domains.tl",
            "tr" => "whois.nic.tr",
            "ua" => "whois.ripe.net",
            "uy" => "nic.uy",
            "uz" => "whois.cctld.uz",
            "va" => "whois.ripe.net",
            "vc" => "whois2.afilias-grs.net",
            "ve" => "whois.nic.ve",
            "vg" => "whois.adamsnames.tc",
            "yu" => "whois.ripe.net"        
        );
        if (!isset($servers[$tld])){
            die('Error: No matching nic server found!');
        }
        $server = $servers[$tld];
        $output = '';
        if ($conn = fsockopen ($server, 43)) {
            fputs($conn, $domain."\r\n");
            while(!feof($conn)) {
                $output .= fgets($conn, 128);
            }
            fclose($conn);
        } else {
            die('Error: Could not connect to ' . $server . '!');
        }
        return $output;
    }
}

function whoisQuery($rawParam) {
    global $chatId;
    $content = (new whoisQuery)->query($rawParam);
    sendMessage($chatId, array(
        'text' => $content,
        'disable_web_page_preview' => 'true' // 不显示页面预览
    ));
}

?>