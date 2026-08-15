<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

$sql = <<<EOF
DROP TABLE IF EXISTS cdb_ai_firewall_log;
DROP TABLE IF EXISTS cdb_ai_firewall_config;
EOF;

runquery($sql);
$finish = TRUE;
