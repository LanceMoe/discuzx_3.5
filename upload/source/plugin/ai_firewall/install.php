<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

$sql = <<<EOF
CREATE TABLE IF NOT EXISTS cdb_ai_firewall_config (
  `config_key` varchar(64) NOT NULL,
  `config_value` mediumtext NOT NULL,
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cdb_ai_firewall_log (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` char(16) NOT NULL DEFAULT '',
  `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `fid` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `content_type` varchar(16) NOT NULL DEFAULT '',
  `decision` varchar(16) NOT NULL DEFAULT '',
  `reason` varchar(500) NOT NULL DEFAULT '',
  `categories` varchar(500) NOT NULL DEFAULT '',
  `confidence` decimal(6,5) unsigned NOT NULL DEFAULT '0.00000',
  `http_status` smallint(5) unsigned NOT NULL DEFAULT '0',
  `latency_ms` int(10) unsigned NOT NULL DEFAULT '0',
  `content_hash` char(64) NOT NULL DEFAULT '',
  `error_code` varchar(64) NOT NULL DEFAULT '',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `decision_created` (`decision`,`created_at`),
  KEY `uid_created` (`uid`,`created_at`)
) ENGINE=InnoDB;
EOF;

runquery($sql);

require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/config.php';
ai_firewall_config::install_defaults();

$finish = TRUE;
