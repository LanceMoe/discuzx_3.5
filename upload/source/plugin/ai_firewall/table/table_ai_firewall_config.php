<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
class table_ai_firewall_config extends discuz_table {

	public function __construct() {
		$this->_table = 'ai_firewall_config';
		$this->_pk = 'config_key';
		parent::__construct();
	}

	public function fetch_all_settings() {
		return DB::fetch_all('SELECT config_key, config_value FROM %t', array($this->_table), 'config_key');
	}

	public function set_value($key, $value) {
		return DB::insert($this->_table, array(
			'config_key' => (string)$key,
			'config_value' => (string)$value,
			'updated_at' => TIMESTAMP,
		), false, true);
	}
}
