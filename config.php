<?php

// $base_url = 'http://TEST-NET-3-1.darkfader.net/littlstream_php/';    // automatically determined if not set
$feed_ttl = 0;
$media_subdir = 'media';    // you can symlink it to your video directory
$thumb_subdir = '.@__thumb';    // Media Station already create this directory
// $transcoded_subdir = 'transcoded';
// $thumb_k1 = 0.22;   # dunno. someone found these. just here for reference
// $thumb_k2 = 0.24;
$thumb_inv_k1 = -0.15;  # I'm just guessing these. Only tried 180 degrees. Are these inverse radial distortion parameters??
$thumb_inv_k2 = -0.15;

# transcoding is better be done on another system. put here mounted paths to NAS.
$transcode_media_path = '/Volumes/R18/VR/180_sbs';
$transcode_destination_path = '/tmp';
#$transcode_destination_path = '/Volumes/Web/transcoded';
$transcode_ffmpeg_path = '';        // it should be in the PATH
$transcode_crf = 23; // 0(lossless)..23(default)..51(unwatchable); add 6 = halfs bitrate, subtract 6 = doubles bitrate

$max_video_bit_rate = 20000000;
$min_video_fps = 58;
$max_video_fps = 60;
$max_video_width = 2560;
$max_video_height = 2560;
$max_audio_bit_rate = 320000;
$max_audio_channels = 2;

$ffmpeg_bin_path = '/mnt/ext/opt/MultimediaConsole/medialibrary/bin/';

// cache ffprobe information
$cache_duration = 604800;
$cache_duration_failed = 3600;

$default_content_type = '180';  // 360, 180, ff, ar
$default_content_layout = 'sbs';    // 2d, ou, sbs, ou_fr, sbs_fr, hcap
