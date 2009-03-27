<?php

// Copyright 2009 Tim 'Scytale' Weber <http://scytale.name/>
// Licensed under the X11 license, see the LICENSE file.

define('DRE_AGENT', 'dretweet 0.3.2');
define('DRE_APIBASE', 'https://twitter.com/');
define('DRE_DMURL', DRE_APIBASE . 'direct_messages.xml');
define('DRE_UPDURL', DRE_APIBASE . 'statuses/update.xml');
define('DRE_CONFSUF', '.conf.php');

if (!function_exists('curl_init'))
	die("This PHP does not support cURL.\n");
if (!function_exists('simplexml_load_string'))
	die("This PHP does not support SimpleXML.\n");

function getCURL($url) {
	global $DRE_USER, $DRE_PASS;
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_MUTE, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERAGENT, DRE_AGENT);
	curl_setopt($curl, CURLOPT_USERPWD, $DRE_USER . ':' . $DRE_PASS);
	curl_setopt($curl, CURLOPT_TIMEOUT, 20);
	return ($curl);
}

function handleFailure($curl, $ret, $more = '') {
	$more = (($more) ? (" ($more)") : (''));
	if ($ret === false)
		throw new Exception("cURL failed miserably. Error " . curl_errno($curl) . ": " . curl_error($curl) . $more);
	$rc = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($rc >= 400)
		throw new Exception("HTTP $rc$more:\n$ret");
}

function getDMs($sinceid = null) {
	$curl = getCURL(DRE_DMURL . (($sinceid == null) ? ('') : ('?since_id=' . (int)$sinceid)));
	$ret = curl_exec($curl);
	handleFailure($curl, $ret);
	$xml = simplexml_load_string($ret);
	curl_close($curl);
	if ($xml === false)
		throw new Exception("XML could not be loaded:\n$ret");
	return ($xml);
}

function postDMs($xml, $sinceid = null) {
	global $DRE_USER, $DRE_ALLOW;
	if ($sinceid === null)
		$sinceid = 0;
	$dms = array();
	foreach ($xml->direct_message as $dm) {
		if ($dm->id <= $sinceid)
			continue;
		$dms[(int)($dm->id)] = array(
			'text' => (string)($dm->text),
			'from' => (string)($dm->sender_screen_name),
			);
	}
	ksort($dms, SORT_NUMERIC);
	foreach ($dms as $id => $val) {
		if (preg_match($DRE_ALLOW, $val['from'])) {
			$curl = getCURL(DRE_UPDURL);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS,
				'status=' . rawurlencode($val['text']) . '&' .
				'source=dretweet'
			);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));
			$ret = curl_exec($curl);
			handleFailure($curl, $ret, 'while posting: ' . $val['text']);
			echo('Posted: ' . $val['text'] . "\n");
		}
		file_put_contents($DRE_USER . '.lastid', $id);
	}
}

$files = scandir('.');
$csl = -1 * strlen(DRE_CONFSUF);
foreach ($files as $file) {
	if (substr($file, $csl) === DRE_CONFSUF) {
		$DRE_USER = $DRE_PASS = '';
		$DRE_ALLOW = '/./';
		include($file);
		if ($DRE_USER === '') {
			echo("warning: $file does not specify \$DRE_USER, skipping.\n");
			continue;
		}
		$lastid = (int)@file_get_contents($DRE_USER . '.lastid');
		postDMs(getDMs($lastid), $lastid);
	}
}

?>
