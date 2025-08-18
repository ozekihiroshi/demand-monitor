<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 直接 CREATE TABLE（パーティション前提のため raw）
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS `demand` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `data` INT(11) DEFAULT NULL,
  `date` INT(11) NOT NULL DEFAULT 0,         -- UNIX秒（分丸め）
  `demand_ip` VARCHAR(15) DEFAULT NULL,
  `flag` TINYINT(4) DEFAULT 0,
  `stamp` INT(11) DEFAULT NULL,
  `delete_flag` TINYINT(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`,`date`),
  UNIQUE KEY `uniq_minute` (`demand_ip`,`date`),          -- 1分1行を保証（重複防止）
  KEY `idx_ip_date_stamp` (`demand_ip`,`date`,`stamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
PARTITION BY RANGE (`date`) (
  PARTITION p2021 VALUES LESS THAN (1609459200) COMMENT = '2021',
  PARTITION p2022 VALUES LESS THAN (1640995200) COMMENT = '2022',
  PARTITION p2023 VALUES LESS THAN (1672531200) COMMENT = '2023',
  PARTITION p2024 VALUES LESS THAN (1704067200) COMMENT = '2024',
  PARTITION p2025 VALUES LESS THAN (1735689600) COMMENT = '2025',
  PARTITION p2026 VALUES LESS THAN (1767225600) COMMENT = '2026',
  PARTITION p2027 VALUES LESS THAN (1798761600) COMMENT = '2027',
  PARTITION pMax  VALUES LESS THAN MAXVALUE
);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `demand`');
    }
};
