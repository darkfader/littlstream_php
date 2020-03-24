<?php

class Config
{
    // $base_url = 'http://TEST-NET-3-1.darkfader.net/littlstream_php/';    // automatically determined if not set
    const feed_ttl = 0;
    const media_subdirs = ['media', 'transcoded'];    // you can symlink it to your video directory
    const thumb_subdir = '.@__thumb';    // Media Station already create this directory
    const transcoded_subdir = 'transcoded';
    // const thumb_k1 = 0.22;   # dunno. someone found these. just here for reference
    // const thumb_k2 = 0.24;
    const thumb_inv_k1 = -0.15;  # I'm just guessing these. Only tried 180 degrees. Are these inverse radial distortion parameters??
    const thumb_inv_k2 = -0.15;

    # transcoding is better be done on another system. put here mounted paths to NAS.
    const transcode_preview = false;
    const transcode_overwrite = false;
    const transcode_media_path = '/Volumes/R18/VR/180_sbs';
    //const transcode_destination_path = '/tmp';
    const transcode_destination_path = '/Volumes/Web/littlstream_php/transcoded';
    const transcode_ffmpeg_path = '';        // it should be in the PATH
    #const transcode_crf = 23; // 0(lossless)..23(default)..51(unwatchable); add 6 = halfs bitrate, subtract 6 = doubles bitrate
    const max_video_bit_rate = 20000000;     // this is the most important factor
    const min_video_fps = 58;    // interpolate when below this
    const max_video_fps = 60;
    // for SBS:
    // 4080*2040=8323200, somewhere on the net...
    // 4096*2048=8388608, not even 1% more!
    const max_video_width = 4096;  //4080;  // 2560;
    const max_video_height = 2048;  //2040;   // 2560;
    const max_audio_bit_rate = 320000;
    const max_audio_channels = 2;

    const ffmpeg_bin_path = '/mnt/ext/opt/MultimediaConsole/medialibrary/bin/';

    // cache ffprobe information
    const cache_duration = 604800;
    const cache_duration_failed = 3600;

    const default_content_type = '180';  // 360, 180, ff, ar
    const default_content_layout = 'sbs';    // 2d, ou, sbs, ou_fr, sbs_fr, hcap
}
