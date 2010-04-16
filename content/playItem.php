<?php

/*
 * Question Bank
 */

/*------------------------------------------------------------------------------
(c) 2010 JISC-funded EASiHE project, University of Southampton
Licensed under the Creative Commons 'Attribution non-commercial share alike' 
licence -- see the LICENCE file for more details
------------------------------------------------------------------------------*/

// URL to embed in QTIEngine XML
$actionurl = SITEROOT_WEB . "?page=playItem";

// if QTIEngine form submitted post onwards to QTIEngine and display its output
if (isset($_POST["submit"])) {
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL				=>	"http://" . QTIENGINE_HOST . ":" . QTIENGINE_PORT . QTIENGINE_PATH . "rest/playItem/0;jsessionid=" . $_SESSION["qtiengine_session"],
		CURLOPT_POST			=>	true,
		CURLOPT_RETURNTRANSFER	=>	true,
		CURLOPT_POSTFIELDS		=>	array_merge(array("actionUrl" => $actionurl), $_POST),
	));
	$response = curl_exec($curl);

	$xml = new SimpleXMLElement($response) or servererror("couldn't parse XML response");

	// TODO: get scripts and stylesheets from HTML header -- find some way to make 
	// Jquery and Prototype play together

	echo simplexml_indented_string($xml->page->html);
	exit;
}

// decide what to do
$action = isset($_GET["action"]) ? $_GET["action"] : null;
switch ($action) {
	case "results":
		// set item queue to current search results
		if (!isset($_SESSION["items"]) || empty($_SESSION["items"]))
			badrequest("no search results");
		$_SESSION["itemqueue"] = $_SESSION["items"];
		$_SESSION["itemqueuepos"] = 0;
		break;
	case "single":
		// set item queue to the single specified item
		if (!isset($_GET["qtiid"]))
			badrequest("no QTI ID specified");
		$_SESSION["itemqueue"] = array($_GET["qtiid"]);
		$_SESSION["itemqueuepos"] = 0;
		break;
	case "next":
		// move the item pointer on and check if we're finished
		if (++$_SESSION["itemqueuepos"] >= count($_SESSION["itemqueue"])) {
			$title = "Finished";
			include "htmlheader.php";
			?>
			<h1><?php echo htmlspecialchars($title); ?></h1>
			<?php if (count($_SESSION["itemqueue"]) == 1) { ?>
				<p>You've finished the only item in the queue.</p>
			<?php } else { ?>
				<p>You've got to the end of the <?php echo count($_SESSION["itemqueue"]); ?> items in the queue.</p>
			<?php } ?>
			<p>What do you want to do now?</p>
			<ul>
				<li><a href="<?php echo SITEROOT_WEB; ?>">Go back to the main menu</a></li>
				<li><a href="<?php echo SITEROOT_WEB; ?>?page=playItem&amp;action=startover">Take <?php echo plural($_SESSION["itemqueue"], "these items", "this item"); ?> again</a></li>
			</ul>
			<?php
			include "htmlfooter.php";
			exit;
		}
		break;
	case "startover":
		// reset the item pointer
		$_SESSION["itemqueuepos"] = 0;
		break;
	default:
		badrequest("no action given");
}

// display a new item

// get the current item
$item = getitem(array_shift($_SESSION["itemqueue"]));
if (!$item)
	badrequest("no item with the given QTI ID exists in the database");

// upload the QTI to QTIEngine
// Doing this manually rather than using curl because until PHP 5.2.7 (SVN 
// r269951 to be specific) there is a bug (http://bugs.php.net/bug.php?id=46696) 
// which breaks the feature needed to submit the uploaded file's mimetype. PHP 
// 5.2.4 is still common at the time of writing (it's in Ubuntu 8.04 LTS) so we 
// can't use curl here.

// boundary -- see http://www.w3.org/Protocols/rfc1341/7_2_Multipart.html
while (true) {
	$boundary = "----------------------------" . uniqid();
	if (strpos($item["xml"], $boundary) === false)
		break;
}

// request
$request =	"--$boundary\r\n";
$request .=	"Content-Disposition: form-data; name=\"actionUrl\"\r\n";
$request .=	"\r\n";
$request .=	"$actionurl\r\n";
$request .=	"--$boundary\r\n";
$request .=	"Content-Disposition: form-data; name=\"uploadedContent\"; filename=\"qb_" . $item["identifier"] . ".xml\"\r\n";
$request .=	"Content-Type: application/xml\r\n";
$request .=	"\r\n";
$request .=	$item["xml"];
$request .=	"\r\n--$boundary--\r\n\r\n";

// headers
$reqheader = array(
	"Host"				=>	QTIENGINE_HOST,
	"Accept"			=>	"*/*",
	"Content-Length"	=>	strlen($request),
	"Content-Type"		=>	"multipart/form-data; boundary=$boundary",
	"User-Agent"		=>	PROGRAMNAME . "/" . VERSION,
);
$url = QTIENGINE_PATH . "rest/upload";
$reqaction = "POST $url HTTP/1.1";

// make requests and follow location headers
$error = null;
while (true) {
	// open socket
	$sock = fsockopen(QTIENGINE_HOST, QTIENGINE_PORT, $errno, $errstr, 30);
	if (!$sock)
		servererror("Couldn't connect to QTIEngine (" . QTIENGINE_HOST . ":" . QTIENGINE_PORT . ")");

	// send data
	$reqheaderstrings = array();
	foreach ($reqheader as $key => $value)
		$reqheaderstrings[] = "$key: $value";
	fputs($sock, $reqaction . "\r\n" . implode("\r\n", $reqheaderstrings) . "\r\n\r\n" . $request);
	fflush($sock);

	// receive headers
	$header = array();
	$httpcode = null;
	while (!feof($sock) && ($line = fgets($sock)) != "\r\n") {
		if (is_null($httpcode) && preg_match('%^HTTP/[^\s]*\s+\d+%', $line))
			$httpcode = intval(preg_replace('%^HTTP/[^\s]*\s+(\d+).*$%', '\1', $line));
		else {
			$parts = explode(":", $line, 2);
			$header[trim($parts[0])] = trim($parts[1]);
		}
	}

	// get the session id from the Set-Cookie header
	if (isset($header["Set-Cookie"])) {
		$cookieparts = explode(";", $header["Set-Cookie"]);
		list($name, $value) = explode("=", $cookieparts[0]);
		if ($name == "JSESSIONID")
			$_SESSION["qtiengine_session"] = $value;
	}

	$response = "";

	// get rest of response and stop if HTTP response code is not a redirection
	if ($httpcode != 301 && $httpcode != 302 || !array_key_exists("Location", $header)) {
		if (isset($header["Transfer-Encoding"]) && $header["Transfer-Encoding"] == "chunked") {
			while (!feof($sock)) {
				// get number of bytes in next chunk
				$bytes = hexdec(preg_replace('%^([0-9a-fA-F]+).*?$%', '\\1', fgets($sock)));
				if ($bytes == 0) // zero-length chunk means it's the end
					break;

				// get data until we have enough bytes
				$chunk = "";
				while (strlen($chunk) < $bytes)
					$chunk .= fgets($sock, $bytes);

				// add data to response
				$response .= $chunk;
			}
		} else
			while (!feof($sock))
				$response .= fgets($sock);
		fclose($sock);
		break;
	}

	// it was a redirection

	// close the socket
	fclose($sock);

	// check its URL is valid
	$urlparts = parse_url($header["Location"]);
	if (!isset($urlparts)) {
		$error = "Hit a malformed Location header pointing to '" . $header["Location"] . "'";
		break;
	}

	// redirect
	$url = preg_replace('%;jsessionid=.*$%', ";jsessionid=" . $_SESSION["qtiengine_session"], $urlparts["path"]) . "?" . $urlparts["query"];
	$reqaction = "GET $url HTTP/1.1";

	// delete POST related headers
	if (isset($reqheader["Content-Length"])) {
		unset($reqheader["Content-Length"]);
		unset($reqheader["Content-Type"]);
	}

	// clear the request data
	$request = "";

	// loop...
}
if (!is_null($error))
	servererror($error);

$xml = new SimpleXMLElement($response) or servererror("couldn't parse XML response");

// TODO: get scripts and stylesheets from HTML header -- find some way to make 
// Jquery and Prototype play together

echo simplexml_indented_string($xml->page->html);

?>