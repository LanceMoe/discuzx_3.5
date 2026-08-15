<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/config.php';
require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/moderator.php';

class ai_firewall_queue {

	const LOCK_NAME = 'ai_firewall_queue';

	public static function enqueue($uid, $fid, $tid, $pid, $contentType) {
		$tid = intval($tid);
		$pid = intval($pid);
		if($tid <= 0 || $pid <= 0 || !in_array($contentType, array('thread', 'reply'), true)) {
			return false;
		}
		return C::t('#ai_firewall#ai_firewall_queue')->add($uid, $fid, $tid, $pid, $contentType);
	}

	public static function run($limit = 20, $staleLifetime = 600) {
		$config = ai_firewall_config::get();
		if(!$config['enabled']) {
			return 0;
		}
		$ttl = max(300, intval($config['timeout']) * max(1, intval($limit)) + 60);
		if(discuz_process::islocked(self::LOCK_NAME, $ttl)) {
			return 0;
		}
		$queueTable = C::t('#ai_firewall#ai_firewall_queue');
		$queueTable->reset_stale($staleLifetime);
		$claimToken = substr(md5(uniqid('', true).random(8)), 0, 32);
		$rows = $queueTable->claim_batch($limit, $claimToken);
		foreach($rows as $row) {
			self::process_row($row, $config);
		}
		$queueTable->cleanup_done($config['log_days']);
		discuz_process::unlock(self::LOCK_NAME);
		return count($rows);
	}

	public static function process_after_response() {
		@ignore_user_abort(true);
		@set_time_limit(0);
		if(function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}
		self::run(3);
	}

	private static function process_row($row, $config) {
		$tid = intval($row['tid']);
		$pid = intval($row['pid']);
		$thread = C::t('forum_thread')->fetch($tid);
		if(!$thread) {
			C::t('#ai_firewall#ai_firewall_queue')->complete($row['id'], 'error', 'post_missing');
			return;
		}
		$post = $row['content_type'] === 'thread'
			? C::t('forum_post')->fetch_threadpost_by_tid_invisible($tid)
			: C::t('forum_post')->fetch_post('tid:'.$tid, $pid);
		if(!$post || ($row['content_type'] === 'reply' && intval($post['pid']) !== $pid)) {
			C::t('#ai_firewall#ai_firewall_queue')->complete($row['id'], 'error', 'post_missing');
			return;
		}
		$forum = C::t('forum_forum')->fetch($row['fid'] ? $row['fid'] : $thread['fid']);
		if(!$forum) {
			C::t('#ai_firewall#ai_firewall_queue')->complete($row['id'], 'error', 'forum_missing');
			return;
		}
		$subject = $row['content_type'] === 'thread' ? $thread['subject'] : $post['subject'];
		$moderator = new ai_firewall_moderator($config);
		$result = $moderator->review($row['content_type'], $subject, $post['message'], $forum, intval($row['uid']));
		if(!empty($result['request_id'])) {
			C::t('#ai_firewall#ai_firewall_log')->update_post_ids($result['request_id'], intval($row['uid']), $tid, $pid);
		}
		if($result['decision'] === 'review') {
			self::quarantine($row['content_type'], $thread, $post);
		}
		C::t('#ai_firewall#ai_firewall_queue')->complete($row['id'], $result['decision'], $result['error_code']);
	}

	private static function quarantine($contentType, $thread, $post) {
		if(intval($thread['displayorder']) === -2 || intval($post['invisible']) === -2) {
			return;
		}
		if($contentType === 'thread' || intval($post['first']) === 1) {
			self::quarantine_thread($thread, $post);
			return;
		}
		self::quarantine_reply($thread, $post);
	}

	private static function quarantine_reply($thread, $post) {
		$tid = intval($thread['tid']);
		$fid = intval($thread['fid']);
		if(intval($thread['displayorder']) < 0 || intval($post['invisible']) !== 0) {
			return;
		}
		$post['status'] = setstatus(3, 1, $post['status']);
		C::t('forum_post')->update_post('tid:'.$tid, intval($post['pid']), array(
			'invisible' => -2,
			'status' => $post['status'],
		), false, false, 0, 0);
		updatemoderate('pid', intval($post['pid']));
		DB::query('UPDATE %t SET replies=GREATEST(replies-1, 0) WHERE tid=%d', array('forum_thread', $tid));
		$todayPosts = intval($post['dateline']) >= strtotime(date('Y-m-d 00:00:00')) ? 1 : 0;
		DB::query('UPDATE %t SET posts=GREATEST(posts-1, 0), todayposts=GREATEST(todayposts-%d, 0) WHERE fid=%d', array('forum_forum', $todayPosts, $fid));
		self::update_thread_last_post($tid);
		self::update_forum_last_post($fid);
		deletethreadcaches($tid);
		manage_addnotify('verifypost');
	}

	private static function quarantine_thread($thread, $post) {
		$tid = intval($thread['tid']);
		$fid = intval($thread['fid']);
		if(intval($thread['displayorder']) < 0 || intval($post['invisible']) !== 0) {
			return;
		}
		$postTable = C::t('forum_post')->get_tablename('tid:'.$tid);
		$visiblePosts = C::t('forum_post')->count_visiblepost_by_tid($tid);
		$todayStart = strtotime(date('Y-m-d 00:00:00'));
		$todayPosts = DB::result_first('SELECT COUNT(*) FROM %t WHERE tid=%d AND invisible=0 AND dateline>=%d', array($postTable, $tid, $todayStart));
		$post['status'] = setstatus(3, 1, $post['status']);
		C::t('forum_post')->update_post('tid:'.$tid, intval($post['pid']), array(
			'invisible' => -2,
			'status' => $post['status'],
		), false, false, 1, 0);
		C::t('forum_thread')->update($tid, array('displayorder' => -2));
		updatemoderate('tid', $tid);
		DB::query('UPDATE %t SET threads=GREATEST(threads-1, 0), posts=GREATEST(posts-%d, 0), todayposts=GREATEST(todayposts-%d, 0) WHERE fid=%d', array(
			'forum_forum',
			intval($visiblePosts),
			intval($todayPosts),
			$fid,
		));
		self::update_forum_last_post($fid);
		deletethreadcaches($tid);
		manage_addnotify('verifythread');
	}

	private static function update_thread_last_post($tid) {
		$lastPost = C::t('forum_post')->fetch_visiblepost_by_tid('tid:'.intval($tid), intval($tid), 0, 1);
		if($lastPost) {
			C::t('forum_thread')->update(intval($tid), array(
				'lastpost' => intval($lastPost['dateline']),
				'lastposter' => $lastPost['anonymous'] ? '' : $lastPost['author'],
			));
		}
	}

	private static function update_forum_last_post($fid) {
		$thread = DB::fetch_first('SELECT tid, subject, lastpost, lastposter FROM %t WHERE fid=%d AND displayorder>=0 ORDER BY lastpost DESC LIMIT 1', array(
			'forum_thread',
			intval($fid),
		));
		$lastpost = $thread ? intval($thread['tid']).chr(9).$thread['subject'].chr(9).intval($thread['lastpost']).chr(9).$thread['lastposter'] : '';
		C::t('forum_forum')->update(intval($fid), array('lastpost' => $lastpost));
	}
}