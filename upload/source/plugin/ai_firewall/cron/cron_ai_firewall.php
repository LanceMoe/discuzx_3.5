<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/queue.php';
ai_firewall_queue::run(20);

/*
cronname:ai_firewall_async_review
week:
day:
hour:
minute:0,5,10,15,20,25,30,35,40,45,50,55
*/