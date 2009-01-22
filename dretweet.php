<?php

require_once('config.php');

@define('DRE_AGENT', 'dretweet 0.1');
@define('DRE_APIBASE', 'https://twitter.com/');
@define('DRE_DMURL', DRE_APIBASE . 'direct_messages.xml');
@define('DRE_UPDURL', DRE_APIBASE . 'statuses/update.xml');

if (!function_exists('curl_init'))
	die("This PHP does not support cURL.\n");
if (!function_exists('simplexml_load_string'))
	die("This PHP does not support SimpleXML.\n");

$lastid = (int)@file_get_contents(DRE_USER . '.lastid');

function getCURL($url) {
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_MUTE, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERAGENT, 'dretweet 0.1');
	curl_setopt($curl, CURLOPT_USERPWD, DRE_USER . ':' . DRE_PASS);
	curl_setopt($curl, CURLOPT_TIMEOUT, 20);
	return ($curl);
}

function handleFailure($curl, $ret) {
	if ($ret === false)
		throw new Exception("cURL failed miserably. Error " . curl_errno($curl) . ": " . curl_error($curl));
	$rc = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ($rc >= 400)
		throw new Exception("HTTP $rc:\n$ret");
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

function postDMs($xml) {
	$dms = array();
	foreach ($xml->direct_message as $dm) {
		$dms[(int)($dm->id)] = (string)($dm->text);
	}
	ksort($dms, SORT_NUMERIC);
	foreach ($dms as $id => $text) {
		$curl = getCURL(DRE_UPDURL);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, array('status' => $text));
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));
		$ret = curl_exec($curl);
		handleFailure($curl, $ret);
		echo('Posted: ' . $text . "\n");
		file_put_contents(DRE_USER . '.lastid', $id);
	}
}

postDMs(getDMs($lastid));

?>
