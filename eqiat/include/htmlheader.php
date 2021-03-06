<?php

/*
 * Eqiat
 * Easy QTI Item Authoring Tool
 */

/*------------------------------------------------------------------------------
(c) 2010 JISC-funded EASiHE project, University of Southampton
Licensed under the Creative Commons 'Attribution non-commercial share alike' 
licence -- see the LICENCE file for more details
------------------------------------------------------------------------------*/

header("Content-Type: text/html; charset=utf-8");
header("Content-Language: en");
header("Content-Style-Type: text/css");
header("Content-Script-Type: text/javascript");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title><?php echo SITE_TITLE; ?><?php if (isset($GLOBALS["title"])) { ?> &ndash; <?php echo $GLOBALS["title"]; ?><?php } ?></title>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/jquery.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/jquery.scrollTo-1.4.2-min.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/tiny_mce/jquery.tinymce.js"></script>
	<script type="text/javascript" src="<?php echo SITEROOT_WEB; ?>include/eqiat.js.php"></script>
	<?php if (isset($GLOBALS["headerjs"])) { ?>
		<script type="text/javascript">
			<?php echo $GLOBALS["headerjs"]; ?>
		</script>
	<?php } ?>
	<link rel="stylesheet" href="<?php echo SITEROOT_WEB; ?>include/styles.css">
	<!--[if IE 6]>
		<link rel="stylesheet" href="<?php echo SITEROOT_WEB; ?>include/styles.ie6.css">
	<![endif]-->
	<?php if (isset($GLOBALS["headercss"])) { ?>
		<style type="text/css">
			<?php echo $GLOBALS["headercss"]; ?>
		</style>
	<?php } ?>
</head>
<body>
<div id="header">
	<h1><?php echo SITE_TITLE; ?></h1>
	<?php
	$menuitems = array();
	$menuitems[] = "<a href=\"" . dirname(SITEROOT_WEB) . "\">Back to Question Bank</a>";
	if ($GLOBALS["page"] != "mainMenu")
		$menuitems[] = "<a href=\"" . SITEROOT_WEB . "\">Back to Eqiat main menu</a>";
	if ($GLOBALS["page"] != "help")
		$menuitems[] = "<a href=\"" . SITEROOT_WEB . "?page=help\">Help</a>";
	if (!empty($menuitems)) { ?>
		<ul id="headermenu">
			<?php foreach ($menuitems as $menuitem) { ?>
				<li><?php echo $menuitem; ?></li>
			<?php } ?>
		</ul>
	<?php } ?>
</div>
<div id="body">
