<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
class ai_firewall_logger {

	public static function write($entry, $logDays) {
		$data = array(
			'request_id' => substr((string)$entry['request_id'], 0, 16),
			'uid' => intval($entry['uid']),
			'fid' => intval($entry['fid']),
			'content_type' => substr((string)$entry['content_type'], 0, 16),
			'decision' => substr((string)$entry['decision'], 0, 16),
			'reason' => cutstr(strip_tags((string)$entry['reason']), 500, ''),
			'categories' => cutstr(strip_tags((string)$entry['categories']), 500, ''),
			'confidence' => max(0, min(1, floatval($entry['confidence']))),
			'http_status' => max(0, intval($entry['http_status'])),
			'latency_ms' => max(0, intval($entry['latency_ms'])),
			'content_hash' => preg_match('/^[a-f0-9]{64}$/', (string)$entry['content_hash']) ? $entry['content_hash'] : '',
			'error_code' => substr((string)$entry['error_code'], 0, 64),
			'created_at' => TIMESTAMP,
		);
		C::t('#ai_firewall#ai_firewall_log')->insert($data);

		if(mt_rand(1, 100) === 1) {
			$days = max(1, intval($logDays));
			C::t('#ai_firewall#ai_firewall_log')->delete_before(TIMESTAMP - $days * 86400);
		}
	}
}
