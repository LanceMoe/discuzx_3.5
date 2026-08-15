<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
class table_ai_firewall_log extends discuz_table {

	public function __construct() {
		$this->_table = 'ai_firewall_log';
		$this->_pk = 'id';
		parent::__construct();
	}

	public function count_by_filter($decision = '', $contentType = '', $startTime = 0) {
		list($where, $params) = $this->build_filter($decision, $contentType, $startTime);
		array_unshift($params, $this->_table);
		return DB::result_first('SELECT COUNT(*) FROM %t'.$where, $params);
	}

	public function fetch_all_by_filter($decision, $contentType, $startTime, $start, $limit) {
		list($where, $params) = $this->build_filter($decision, $contentType, $startTime);
		array_unshift($params, $this->_table);
		$params[] = intval($start);
		$params[] = intval($limit);
		return DB::fetch_all('SELECT * FROM %t'.$where.' ORDER BY id DESC LIMIT %d, %d', $params);
	}

	public function delete_before($timestamp) {
		return DB::delete($this->_table, DB::field('created_at', intval($timestamp), '<'));
	}

	public function delete_all() {
		return DB::delete($this->_table, '1');
	}

	private function build_filter($decision, $contentType, $startTime) {
		$where = array();
		$params = array();
		if($decision !== '') {
			$where[] = 'decision=%s';
			$params[] = $decision;
		}
		if($contentType !== '') {
			$where[] = 'content_type=%s';
			$params[] = $contentType;
		}
		if($startTime > 0) {
			$where[] = 'created_at>=%d';
			$params[] = intval($startTime);
		}
		return array($where ? ' WHERE '.implode(' AND ', $where) : '', $params);
	}
}
