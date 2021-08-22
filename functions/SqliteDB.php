<?php

class SqliteDB extends SQLite3 { // Sqlite3数据库
    public function __construct($filename) {
        $this->open($filename); // 打开数据库连接
    }
    public function __destruct() {
        $this->close(); // 关闭数据库连接
    }
}

?>