<?php

$feed_url = 'http://TEST-NET-3-1.darkfader.net/littlstream_php/';
$feed_ttl = 0;
$media_subdir = 'media';    // you can symlink it to your video directory
$thumb_subdir = '.@__thumb';    // Media Station already create this directory

$cache_duration = 604800;
$cache_duration_failed = 3600;

$default_content_type = '180';  // 360, 180, ff, ar
$default_content_layout = 'sbs';    // 2d, ou, sbs, ou_fr, sbs_fr, hcap
