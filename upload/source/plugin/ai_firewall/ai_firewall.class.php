<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/config.php';
require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/queue.php';

class plugin_ai_firewall {
	private static $eligibleContentType = '';

	public function post() {
		global $_G;
		if(!$this->is_submission()) {
			return;
		}

		$config = ai_firewall_config::get();
		$action = isset($_GET['action']) ? $_GET['action'] : '';
		$contentType = in_array($action, array('newthread', 'newtrade'), true) ? 'thread' : 'reply';
		if(!$config['enabled'] || ($contentType === 'thread' && !$config['check_threads']) || ($contentType === 'reply' && !$config['check_replies'])) {
			return;
		}
		if($config['forum_ids'] && !in_array(intval($_G['fid']), $config['forum_ids'], true)) {
			return;
		}
		if($config['exempt_staff'] && (intval($_G['adminid']) > 0 || !empty($_G['forum']['ismoderator']))) {
			return;
		}

		self::$eligibleContentType = $contentType;
	}

	public function post_message($param) {
		global $_G;
		if(self::$eligibleContentType === '' || empty($param['param']) || !is_array($param['param'])) {
			return;
		}
		list($message, $url, $values) = array_pad($param['param'], 3, array());
		if(!in_array($message, array('post_newthread_succeed', 'post_newthread_mod_succeed', 'post_reply_succeed', 'post_reply_mod_succeed'), true)) {
			return;
		}
		$values = is_array($values) ? $values : array();
		$tid = isset($values['tid']) ? intval($values['tid']) : 0;
		$pid = isset($values['pid']) ? intval($values['pid']) : 0;
		if(!$tid && preg_match('/(?:[?&]|&amp;)tid=(\d+)/', (string)$url, $match)) {
			$tid = intval($match[1]);
		}
		if(!$pid && preg_match('/(?:[?&]|&amp;)pid=(\d+)/', (string)$url, $match)) {
			$pid = intval($match[1]);
		}
		if($tid > 0 && $pid === 0 && self::$eligibleContentType === 'thread') {
			$firstPost = C::t('forum_post')->fetch_threadpost_by_tid_invisible($tid);
			$pid = $firstPost ? intval($firstPost['pid']) : 0;
		}
		if(ai_firewall_queue::enqueue($_G['uid'], $_G['fid'], $tid, $pid, self::$eligibleContentType)) {
			register_shutdown_function(array('ai_firewall_queue', 'process_after_response'));
		}
		self::$eligibleContentType = '';
	}

	private function is_submission() {
		if($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return false;
		}
		$action = isset($_GET['action']) ? $_GET['action'] : '';
		if(in_array($action, array('newthread', 'newtrade'), true)) {
			$submitKey = 'topicsubmit';
		} elseif($action === 'reply') {
			$submitKey = 'replysubmit';
		} else {
			return false;
		}
		if(empty($_GET[$submitKey]) || empty($_GET['formhash']) || !hash_equals((string)formhash(), (string)$_GET['formhash'])) {
			return false;
		}
		if(!empty($_SERVER['HTTP_X_FLASH_VERSION']) || empty($_SERVER['HTTP_REFERER'])) {
			return empty($_SERVER['HTTP_X_FLASH_VERSION']);
		}
		$refererHost = preg_replace('/https?:\/\/([^:\/]+).*/i', '$1', $_SERVER['HTTP_REFERER']);
		$currentHost = preg_replace('/([^:]+).*/', '$1', $_SERVER['HTTP_HOST']);
		return strncasecmp($_SERVER['HTTP_REFERER'], 'http://wsq.discuz.com/', 22) === 0 || $refererHost === $currentHost;
	}

}

class plugin_ai_firewall_forum extends plugin_ai_firewall {
}

class mobileplugin_ai_firewall_forum extends plugin_ai_firewall {
}