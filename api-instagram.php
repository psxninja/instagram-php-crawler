<?php
/*
InstaMedia v1.0.0
Made with ♥ by https://github.com/psxninja

Cache txt storage usage:
5k  account  = 80mb ~ 100mb
*/
header('Content-Type: application/json');

date_default_timezone_set('America/Sao_Paulo');
define('USERAGENT', "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36");
define('ROOT_FOLDER', dirname(__FILE__));
define('CACHE_FOLDER', "/instaMediaCache/");
define('COOKIE_FOLDER', "/instaMediaCookies/");
define('COOKIE_FILE', "cookieInstaMedia.txt");

if (!is_dir(ROOT_FOLDER.COOKIE_FOLDER)) {
	mkdir(ROOT_FOLDER.COOKIE_FOLDER, 0777, true);
}
if (!is_dir(ROOT_FOLDER.CACHE_FOLDER)) {
	mkdir(ROOT_FOLDER.CACHE_FOLDER, 0777, true);
}

function fixCookies() {
	if (file_exists(ROOT_FOLDER.COOKIE_FOLDER.COOKIE_FILE)) {
		$oldCookies = file_get_contents(ROOT_FOLDER.COOKIE_FOLDER.COOKIE_FILE);
		$oldCooArr = explode("\n", $oldCookies);

		if (count($oldCooArr)) {
			foreach ($oldCooArr as $k => $line) {
				if (strstr($line, '# ')) {
					unset($oldCooArr[$k]);
				}
			}

			$newCookies = implode("\n", $oldCooArr);
			$newCookies = trim($newCookies, "\n");

			file_put_contents(
				ROOT_FOLDER.COOKIE_FOLDER.COOKIE_FILE,
				$newCookies
			);
		}
	}
}

function fromHttp($url) {
	$arrSetHeaders = array(
		'origin: https://www.instagram.com',
		'authority: www.instagram.com',
		'method: GET',
		'upgrade-insecure-requests: 1',
		'Host: www.instagram.com',
		"User-Agent: USERAGENT",
		'content-type: application/x-www-form-urlencoded',
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.9',
		'accept-language:en-US;q=0.9,en;q=0.8',
		'accept-encoding: deflate, br',
		"Referer: https://www.instagram.com",
		'Connection: keep-alive',
		'cache-control: max-age=0',
	);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEJAR, ROOT_FOLDER.COOKIE_FOLDER.COOKIE_FILE);
	curl_setopt($ch, CURLOPT_COOKIEFILE, ROOT_FOLDER.COOKIE_FOLDER.COOKIE_FILE);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);/* connection timeout in seconds  */
	curl_setopt($ch, CURLOPT_USERAGENT, USERAGENT);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $arrSetHeaders);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$result = curl_exec($ch);

	if (curl_errno($ch)) {
		echo 'Error:' . curl_error($ch);
	}

	curl_close($ch);

	unset($oldCookies);
	unset($oldCooArr);
	unset($arrSetHeaders);
	unset($ch);

	return $result;
}

function saveFile($file, $content) {
	$saved = file_put_contents($file, $content);
	return $saved;
}

function getFile($file, $username) {
	if (file_exists($file)) {
		$fromFile = file_get_contents($file);
		return $fromFile;
	}
	array_map('unlink', glob(ROOT_FOLDER.CACHE_FOLDER.$username."_*"));
	return false;
}

$username =& $_GET["username"];
$fields =& $_GET["fields"]; 
$imgSize =& $_GET["size"];
$username = htmlspecialchars($username);
$fields = explode(',', htmlspecialchars($fields));
$imgSize = htmlspecialchars($imgSize);

if (empty($fields[0]) || empty($imgSize) || empty($username)) {
	header('Content-Type: text/html');
	exit("
	<p style=\"font-family:-apple-system,
	BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,
	'Helvetica Neue',sans-serif;font-size:17px;\">
	username = instagram_url_account<br>
	fields = media_url, permalink, caption, liked_by, comments<br>
	size = full, small<br><br>
	?username=usa&fields=media_url&size=full<br><br><br>
	This works only with public profile<br>
	InstaMedia v1.0.0");
}

$fields[] = "error";
$date = date("m_d_Y");
$filename = ROOT_FOLDER.CACHE_FOLDER.$username."_".$date.".txt";
$igJsonFile = getFile($filename, $username);
$igJson = [];
$igApi = [];
$igJson[0] = [
	"media_url" => "",
	"permalink" => "",
	"caption" => "",
	"liked_by" => "",
	"comments" => ""
];

if ($igJsonFile === false) {
	/* $igJsonApi = fromHttp("https://www.instagram.com/".$username."/"); */
	$igJsonApi = fromHttp("https://www.instagram.com/".$username."/?__a=1");
	fixCookies();

	if (strlen((string)$igJsonApi) === 2) {
		$igJson = json_encode([[
			"error" => "Account not found"
		]]);
		saveFile($filename, $igJson);
		exit($igJson);
	}

	if ($igJsonApi) {/* need review this - check data on other url above */
		$startText = "window._sharedData = ";
		$endText = "\"prod\"}";
		$indexStart = strrpos($igJsonApi, $startText);

		if ($indexStart) {
			$indexEnd = strlen($startText);
			$igJsonApi = substr($igJsonApi, ($indexStart + $indexEnd));
			$indexStart2 = strrpos($igJsonApi, $endText);
			$indexEnd2 = strlen($endText);
			$igJsonApi = substr($igJsonApi, 0, ($indexStart2 + $indexEnd2));
		}

		$jsonFromIg = json_decode($igJsonApi);

		/* ?><pre><?php
		print_r($jsonFromIg);
		?></pre><?php
		exit(); */

		if (!empty($jsonFromIg->entry_data)) {
			$jsonFromIg = $jsonFromIg->entry_data->ProfilePage[0];
		}

		if ($jsonFromIg->graphql->user->is_private) {
			$igJson = json_encode([[
				"error" => "Private account"
			]]);
			saveFile($filename, $igJson);
			exit($igJson);
		}

		$jsonFromIg = $jsonFromIg->graphql->user->edge_owner_to_timeline_media->edges;

		foreach ($jsonFromIg as $ind => $item) {
			$igJson[$ind] = (object) [
				"media_url" => $item->node->display_url,
				"permalink" => $item->node->shortcode,
				"caption" => $item->node->edge_media_to_caption->edges[0]->node->text,
				"liked_by" => $item->node->edge_liked_by->count,
				"comments" => $item->node->edge_media_to_comment->count,
				"thumbnail" => $item->node->thumbnail_resources[4]->src
			];
			saveFile($filename, json_encode($igJson));
		}
		unset($jsonFromIg);
	} else {
		$igJson = json_encode([[
			"error" => "Error to get content from instragram."
		]]);
		exit($igJson);
	}
}

$igJson = json_decode($igJsonFile);

/* ?><pre><?php
print_r($igJson);
?></pre><?php
exit(); */

foreach ($igJson as $key => $item) {
	foreach ($igJson[$key] as $keyItem => $itemJson) {
		if (in_array($keyItem, $fields)) {
			$igApi[$key][$keyItem] = $itemJson;

			if ($keyItem === "permalink") {
				$igApi[$key][$keyItem] = "https://www.instagram.com/p/".$igJson[$key]->{"permalink"}."/";
			}
			if ($keyItem === "media_url" && $imgSize === "small") {
				$igApi[$key][$keyItem] = $igJson[$key]->{"thumbnail"};
			}
		}
	}
}

/* ?><pre><?php
print_r($igApi);
?></pre><?php
exit(); */

echo json_encode($igApi);

unset($date);
unset($filename);
unset($igJsonFile);
unset($igJson);
unset($igApi);
unset($username);
unset($fields);
unset($imgSize);
