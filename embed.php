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
$code = $vimeo->call('https://vimeo.com/35514005', $settings);

?>
<!doctype html>
<html>
<head>
<title>TEST for Vimeo's oEmbed</title>
<style rel="stylesheet">
body>p {font-weight: strong;}
</style>
</head>
<body>
<h1>Testing API for Vimeo oEmbed Library</h1>

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
