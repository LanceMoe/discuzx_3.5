<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
class table_ai_firewall_queue extends discuz_table {

	public function __construct() {
		$this->_table = 'ai_firewall_queue';
		$this->_pk = 'id';
		parent::__construct();
	}

	public function add($uid, $fid, $tid, $pid, $contentType) {
		return $this->insert(array(
			'uid' => intval($uid),
			'fid' => intval($fid),
			'tid' => intval($tid),
			'pid' => intval($pid),
			'content_type' => $contentType === 'thread' ? 'thread' : 'reply',
			'status' => 'pending',
			'attempts' => 0,
			'result' => '',
			'error_code' => '',
			'claim_token' => '',
			'created_at' => TIMESTAMP,
			'claimed_at' => 0,
			'processed_at' => 0,
		), false, false, true);
	}

	public function claim_batch($limit, $claimToken) {
		$limit = max(1, min(50, intval($limit)));
		DB::query('UPDATE %t SET status=%s, attempts=attempts+1, claim_token=%s, claimed_at=%d WHERE status=%s ORDER BY id ASC LIMIT %d', array(
			$this->_table,
			'processing',
			(string)$claimToken,
			TIMESTAMP,
			'pending',
			$limit,
		));
		return DB::fetch_all('SELECT * FROM %t WHERE claim_token=%s AND status=%s ORDER BY id ASC', array(
			$this->_table,
			(string)$claimToken,
			'processing',
		));
	}

	public function reset_stale($lifetime, $maxAttempts = 3) {
		$expired = TIMESTAMP - max(60, intval($lifetime));
		DB::query('UPDATE %t SET status=%s, claim_token=%s WHERE status=%s AND claimed_at>0 AND claimed_at<%d AND attempts<%d', array(
			$this->_table,
			'pending',
			'',
			'processing',
			$expired,
			intval($maxAttempts),
		));
		DB::query('UPDATE %t SET status=%s, result=%s, error_code=%s, processed_at=%d WHERE status=%s AND claimed_at>0 AND claimed_at<%d AND attempts>=%d', array(
			$this->_table,
			'done',
			'error',
			'worker_timeout',
			TIMESTAMP,
			'processing',
			$expired,
			intval($maxAttempts),
		));
	}

	public function complete($id, $result, $errorCode = '') {
		return DB::query('UPDATE %t SET status=%s, result=%s, error_code=%s, claim_token=%s, processed_at=%d WHERE id=%d AND status=%s', array(
			$this->_table,
			'done',
			$result === 'review' ? 'review' : ($result === 'error' ? 'error' : 'pass'),
			(string)$errorCode,
			'',
			TIMESTAMP,
			intval($id),
			'processing',
		));
	}

	public function cleanup_done($days) {
		$expired = TIMESTAMP - max(1, intval($days)) * 86400;
		return DB::delete($this->_table, DB::field('status', 'done').' AND '.DB::field('processed_at', $expired, '<'));
	}
}