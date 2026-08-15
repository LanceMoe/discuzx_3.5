<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/client.php';
require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/logger.php';

class ai_firewall_moderator {

	private $config;

	public function __construct($config) {
		$this->config = $config;
	}

	public function review($contentType, $subject, $message, $forum, $uid = null) {
		global $_G;
		$source = (string)$subject."\n".(string)$message;
		$contentHash = hash('sha256', $source);
		$subject = $this->truncate_utf8((string)$subject, min(500, $this->config['max_chars']));
		$remaining = max(0, $this->config['max_chars'] - $this->length_utf8($subject));
		$message = $this->truncate_utf8((string)$message, $remaining);
		$requestId = substr(md5(uniqid('', true).random(8)), 0, 16);

		$client = new ai_firewall_client($this->config);
		$response = $client->moderate(array(
			'content_type' => $contentType,
			'forum' => array(
				'fid' => intval($forum['fid']),
				'name' => isset($forum['name']) ? (string)$forum['name'] : '',
			),
			'subject' => $subject,
			'message' => $message,
		));

		$decision = 'error';
		$reason = '';
		$categories = array();
		$confidence = 0;
		$errorCode = $response['error_code'];

		if($response['ok']) {
			$parsed = $this->parse_model_result($response['raw_content']);
			if($parsed['valid']) {
				$decision = $parsed['decision'];
				$reason = $parsed['reason'];
				$categories = $parsed['categories'];
				$confidence = $parsed['confidence'];
				$errorCode = '';
			} else {
				$errorCode = 'invalid_model_result';
			}
		}

		$effectiveDecision = $decision === 'error' ? $this->config['failure_mode'] : $decision;
		$shouldLog = !($contentType === 'thread' && $decision === 'pass' && !empty($this->config['skip_passed_thread_logs']));
		if($shouldLog) {
			ai_firewall_logger::write(array(
				'request_id' => $requestId,
				'uid' => $uid === null ? intval($_G['uid']) : intval($uid),
				'fid' => $forum['fid'],
				'content_type' => $contentType,
				'decision' => $decision,
				'reason' => $reason,
				'categories' => implode(',', $categories),
				'confidence' => $confidence,
				'http_status' => $response['http_status'],
				'latency_ms' => $response['latency_ms'],
				'content_hash' => $contentHash,
				'error_code' => $errorCode,
			), $this->config['log_days']);
		}

		return array(
			'decision' => $effectiveDecision,
			'original_decision' => $decision,
			'error_code' => $errorCode,
			'request_id' => $shouldLog ? $requestId : '',
		);
	}

	public function parse_model_result($content) {
		$content = trim((string)$content);
		$fence = str_repeat(chr(96), 3);
		if(substr($content, 0, 3) === $fence && substr($content, -3) === $fence) {
			$content = preg_replace('/^'.preg_quote($fence, '/').'(?:json)?\s*/i', '', $content);
			$content = preg_replace('/\s*'.preg_quote($fence, '/').'$/', '', $content);
			$content = trim($content);
		}
		$data = json_decode($content, true);
		$requiredKeys = array('reason', 'categories', 'confidence', 'decision');
		if(!is_array($data) || count($data) !== count($requiredKeys) || array_diff($requiredKeys, array_keys($data))) {
			return array('valid' => false);
		}
		if(!is_string($data['reason']) || trim($data['reason']) === '' || !is_array($data['categories']) || count($data['categories']) > 10 || !is_numeric($data['confidence']) || floatval($data['confidence']) < 0 || floatval($data['confidence']) > 1 || !is_string($data['decision']) || !in_array($data['decision'], array('pass', 'review'), true)) {
			return array('valid' => false);
		}
		$categories = array();
		foreach($data['categories'] as $category) {
			if(!is_string($category)) {
				return array('valid' => false);
			}
			if(trim($category) !== '') {
				$categories[] = cutstr(trim($category), 50, '');
			}
		}
		return array(
			'valid' => true,
			'decision' => $data['decision'],
			'reason' => cutstr(trim($data['reason']), 500, ''),
			'categories' => array_slice(array_unique($categories), 0, 10),
			'confidence' => floatval($data['confidence']),
		);
	}

	private function length_utf8($value) {
		if(function_exists('mb_strlen')) {
			return mb_strlen($value, 'UTF-8');
		}
		if(function_exists('iconv_strlen')) {
			$length = iconv_strlen($value, 'UTF-8');
			if($length !== false) return $length;
		}
		return preg_match_all('/./us', $value, $matches) ? count($matches[0]) : strlen($value);
	}

	private function truncate_utf8($value, $limit) {
		$limit = max(0, intval($limit));
		if($this->length_utf8($value) <= $limit) {
			return $value;
		}
		if(function_exists('mb_substr')) {
			return mb_substr($value, 0, $limit, 'UTF-8');
		}
		if(function_exists('iconv_substr')) {
			$cut = iconv_substr($value, 0, $limit, 'UTF-8');
			if($cut !== false) return $cut;
		}
		if(preg_match_all('/./us', $value, $matches)) {
			return implode('', array_slice($matches[0], 0, $limit));
		}
		return substr($value, 0, $limit);
	}
}