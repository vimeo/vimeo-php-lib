<?php
include 'vimeo.php';

$vimeo = new phpVimeo('CONSUMER_KEY', 'CONSUMER_SECRET', 'ACCESS_TOKEN', 'ACCESS_TOKEN_SECRET');

try {
    $video_id = $vimeo->upload('PATH_TO_VIDEO_FILE');

    if ($video_id) {
        echo '<a href="http://vimeo.com/' . $video_id . '">Upload successful!</a>';

        //$vimeo->call('vimeo.videos.setPrivacy', array('privacy' => 'nobody', 'video_id' => $video_id));
        $vimeo->call('vimeo.videos.setTitle', array('title' => 'YOUR TITLE', 'video_id' => $video_id));
        $vimeo->call('vimeo.videos.setDescription', array('description' => 'YOUR_DESCRIPTION', 'video_id' => $video_id));
    }
    else {
        echo "Video file did not exist!";
    }
}
catch (VimeoAPIException $e) {
    echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
}