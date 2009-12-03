<?
include 'vimeo.php';

$vimeo = new phpVimeo('CONSUMER_KEY', 'CONSUMER_SECRET', 'ACCESS_TOKEN', 'ACCESS_TOKEN_SECRET');

try {
	$video_id = $vimeo->upload('PATH_TO_VIDEO_FILE');
	$vimeo->call('vimeo.videos.setTitle', array('title' => 'YOUR TITLE', 'video_id' => $video_id));
	$vimeo->call('vimeo.videos.setDescription', array('description' => 'YOUR_DESCRIPTION', 'video_id' => $video_id));
}
catch (VimeoAPIException $e) {
	echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
}

?>