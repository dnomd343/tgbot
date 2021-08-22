<?php

class DNSSEC {
    public function algorithmDesc($id) { // 获取算法类型
        switch ($id) {
            case '1':
                return 'RSA/MD5';
            case '3':
                return 'DSA/SHA1';
            case '5':
                return 'RSA/SHA-1';
            case '6':
                return 'DSA-NSEC3-SHA1';
            case '7':
                return 'RSASHA1-NSEC3-SHA1';
            case '8':
                return 'RSA/SHA-256';
            case '10':
                return 'RSA/SHA-512';
            case '12':
                return 'GOST R 34.10-2001';
            case '13':
                return 'ECDSA Curve P-256 with SHA-256';
            case '14':
                return 'ECDSA Curve P-384 with SHA-384';
            case '15':
                return 'Ed25519';
            case '16':
                return 'Ed448';
        }
    }

    public function digestDesc($id) { // 获取摘要类型
        switch ($id) {
            case '1':
                return 'SHA-1';
            case '2':
                return 'SHA-256';
            case '3':
                return 'GOST R 34.11-94';
            case '4':
                return 'SHA-384';
        }
    }
}

?>
