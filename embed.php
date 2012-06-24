<?php

require_once('vimeoEmbed.php');

$settings = array(
	'xhtml' => 1,
	'byline' => 0,
	'title' => 0,
	'maxwidth' => '300px',
	'portrait' => 0
);

$vimeo = new VimeoEmbed;
if($_REQUEST['clear']) $vimeo->clearCache();
if($_REQUEST['nocache']) $vimeo->disableCache();
$code = $vimeo->call('https://vimeo.com/35514005', $settings);

?>
<!doctype html>
<html>
<head>
<title>TEST for Vimeo's oEmbed</title>
<style rel="stylesheet">
body>p {font-weight: bold;}
pre {white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -o-pre-wrap; white-space: -pre-wrap; word-wrap: break-word;}
</style>
</head>
<body>
<h1>Testing API for Vimeo oEmbed Library</h1>

<form action="embed.php" method="get" name="FoobearWinnyTheFoobear">
<table border="0">
<tr>
<td><input type="checkbox" name="nocache" value="1" /></td><td>No Cache</td>
</tr>
<tr>
<td><input type="checkbox" name="clear" value="1" /></td><td>Clear Cache?</td>
</tr>
<tr>
<td colspan="2" style="text-align: right;"><input type="submit" value="Reload this page" /></td>
</tr>
</table>
</form>

<p>Parameters</p>
<pre><?php

$settings['url'] = 'https://vimeo.com/35514005'; // automatically merged in the library
print_r($settings);

?></pre>

<p>Response code</p>
<pre><?php

echo htmlentities(print_r($code, true));

?></pre>

<p>Raw embed code:</p>
<pre><?php echo htmlentities($code->html); ?></pre>

<p>Live embed code</p>
<div><?php print $code->html; ?></div>
</body>
</html>
