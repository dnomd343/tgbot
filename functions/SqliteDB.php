<?php

class SqliteDB extends SQLite3 { // Sqlite3数据库
    public function __construct($filename) {
        $this->open($filename);
    }
    public function __destruct() {
        $this->close();
    }
}

?>