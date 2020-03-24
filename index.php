<?php
// ob_end_clean();
error_reporting(E_STRICT);

require_once 'config.php';
require_once 'TempCache.php';  // or APC, memcached, ...

use Cajogos\TempCache as TempCache;

Config::min_video_fps;


// limit exec to single request
$fp_lock = fopen('/tmp/ffmpeg_lock.txt', 'a');
$got_lock = flock($fp_lock, LOCK_EX | LOCK_NB);


// $txt = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
// $myfile = file_put_contents('logs.txt', $txt . PHP_EOL, FILE_APPEND | LOCK_EX);

header('Cache-Control: no-cache');
header('Cache-Control: max-age=0');
header('Content-Type: application/rss+xml; charset=utf-8');



function url_origin($s, $use_forwarded_host = false)
{
    $ssl      = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on');
    $sp       = strtolower($s['SERVER_PROTOCOL']);
    $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
    $port     = $s['SERVER_PORT'];
    $port     = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
    $host     = ($use_forwarded_host && isset($s['HTTP_X_FORWARDED_HOST'])) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
    $host     = isset($host) ? $host : $s['SERVER_NAME'] . $port;
    return $protocol . '://' . $host;
}

function full_url($s, $use_forwarded_host = false)
{
    return url_origin($s, $use_forwarded_host) . $s['REQUEST_URI'];
}

if (!isset($base_url)) {
    $base_url = str_replace('index.php', '', full_url($_SERVER));
}

$rssdoc = new DOMDocument('1.0', 'UTF-8');
$rss = $rssdoc->createElement("rss");
$rss = $rssdoc->appendChild($rss);
$rss->setAttribute("xmlns:dc", "http://purl.org/dc/elements/1.1/");
$rss->setAttribute("xmlns:content", "http://purl.org/rss/1.0/modules/content/");
$rss->setAttribute("xmlns:atom", "http://www.w3.org/2005/Atom");
$rss->setAttribute("version", "2.0");
$rss->setAttribute("xmlns:ls", "https://www.littlstar.com");

$channels = array();

function getChannelForTitle($title, $key = null)
{
    global $rssdoc, $rss, $channels, $base_url, $feed_ttl;

    if ($key === null) {
        $e = preg_split('/[0-9-.]/', $title, 2);
        $key = $e[0];
    }
    $key = 'video';     # only 1 channel per feed allowed?? meh.

    $channel = $channels[$key];
    if ($channel !== null) {
        return $channel;
    }

    $channel = $rssdoc->createElement("channel");
    $channels[$key] = $channel;
    $channel = $rss->appendChild($channel);

    $channel->appendChild($rssdoc->createElement('ttl', strval($feed_ttl)));
    $channel->appendChild($rssdoc->createElement('title', $key));
    $channel->appendChild($rssdoc->createElement('description', $key));
    $channel->appendChild($rssdoc->createElement('category', 'Videos'));
    $channel->appendChild($rssdoc->createElement('generator', 'Littlstream_php'));
    $channel->appendChild($rssdoc->createElement('lastBuildDate', date("c")));
    $channel->appendChild($rssdoc->createElement('link', 'http://github.com/dylang/node-rss'));

    $image = $rssdoc->createElement('image');
    $channel->appendChild($image);
    $image->appendChild($rssdoc->createElement('url', $base_url . 'vr.jpg'));
    $image->appendChild($rssdoc->createElement('title', 'VR'));
    $image->appendChild($rssdoc->createElement('link', ''));

    return $channel;
}

$total_gb = 0;
$total_duration = 0;
$transcode_total_gb = 0;
$transcode_total_duration = 0;
$transcode_commands = array();
array_push($transcode_commands, "\n", "#!/bin/sh");


function processFile($media_abspath, $media_subdir, $fileinfo)
{
    global $got_lock, $rssdoc, $base_url;
    global $total_gb, $total_duration, $transcode_total_gb, $transcode_total_duration, $transcode_commands;

    if (!$fileinfo->isDot()) {
        $t = $fileinfo->getFilename();
        if (!preg_match('/(\\.mp4|\\.mov|\\.m4v|\\.m3u8|\\.mpd)$/', $t)) {
            return;
        }
        $t = pathinfo($t, PATHINFO_FILENAME);   // remove file extension
        $file_abspath = $media_abspath . DIRECTORY_SEPARATOR . $fileinfo->getFilename();
        $file_url = $base_url . $media_subdir . '/' . $fileinfo->getFilename();
        $thumbnail_abspath = $media_abspath . DIRECTORY_SEPARATOR . Config::thumb_subdir . DIRECTORY_SEPARATOR . $t . '.jpg';
        $thumbnail_url = $base_url . $media_subdir . DIRECTORY_SEPARATOR . Config::thumb_subdir . '/' . $t . '.jpg';

        if (!is_dir($media_abspath . DIRECTORY_SEPARATOR . Config::thumb_subdir)) {
            mkdir($media_abspath . DIRECTORY_SEPARATOR . Config::thumb_subdir);
        }

        $content_type = Config::default_content_type;
        $content_layout = Config::default_content_layout;

        $title = $t;
        $title = str_replace('_180', '', $title, $_180_count);
        if ($_180_count) $content_type = '180';
        $title = str_replace('_360', '', $title, $_360_count);
        if ($_360_count) $content_type = '360';
        $title = str_replace('_sbs', '', $title, $_sbs_count);
        if ($_sbs_count) $content_layout = 'sbs';
        $title = str_replace('_ou', '', $title, $_ou_count);
        if ($_ou_count) $content_layout = 'sbs';
        $title = str_replace('_sbs-fr', '', $title, $_sbs_fr_count);
        if ($_sbs_fr_count) $content_layout = 'sbs_fr';
        $title = str_replace('_ou-fr', '', $title, $_ou_fr_count);
        if ($_ou_fr_count) $content_layout = 'ou_fr';

        $filter_v = '';
        if ($content_layout == 'sbs' || $content_layout == 'sbs_fr') $filter_v .= 'crop=in_w/2:in_h:0:0';
        if ($content_layout == 'ou' || $content_layout == 'ou_fr') $filter_v .= 'crop=in_w:in_h/2:0:0';
        # http://www.ffmpeg.org/ffmpeg-all.html#lenscorrection
        if ($content_type == '180') $filter_v .= ', lenscorrection=cx=0.5:cy=0.5:k1=' . strval(Config::thumb_inv_k1) . ':k2=' . strval(Config::thumb_inv_k2);
        if ($content_type == '360') $filter_v .= ', lenscorrection=cx=0.5:cy=0.5:k1=' . strval(Config::thumb_inv_k1) . ':k2=' . strval(Config::thumb_inv_k2);  // TODO: check...
        $command = Config::ffmpeg_bin_path . 'ffmpeg' .
            ' -discard nokey -hide_banner -noaccurate_seek -ss "00:10:00" -i ' . escapeshellarg($file_abspath) . ' -an -r 1 -frames:v 1 -codec:v mjpeg' .
            ($filter_v != "" ? ' -filter:v ' . escapeshellarg($filter_v) : '') .
            ' -f image2 -y ' . escapeshellarg($thumbnail_abspath);

        $ffprobe_cache_key = $t . '_ffprobe' . ($media_subdir == Config::transcoded_subdir ? '_tc' : '');
        $json = TempCache::get($ffprobe_cache_key);
        $ffprobe = $json !== null ? json_decode($json) : null;
        $channel = getChannelForTitle($media_subdir == Config::transcoded_subdir ? $title . '*' : $title, $media_subdir == Config::transcoded_subdir ? 'transcoded' : null);

        $item = $rssdoc->createElement('item');
        $channel->appendChild($item);

        $item->appendChild($rssdoc->createComment($t));

        if ($got_lock) {    // allow exec?
            // take thumbnail
            if (!file_exists($thumbnail_abspath)) {
                # $item->appendChild($rssdoc->createComment($thumbnail_abspath));
                $item->appendChild($rssdoc->createComment('Create thumbnail: ' . $command));
                if ($ffprobe === null) {
                    exec($command);
                }
            }

            // ffprobe
            if ($ffprobe === null || ($json == '{}' && Config::cache_duration_failed == 0)) {
                unset($output);
                exec(Config::ffmpeg_bin_path . 'ffprobe ' . escapeshellarg($file_abspath) . ' -show_entries streams:format -v quiet -of json', $output);
                $json = implode($output);
                $ffprobe = json_decode($json);
                if ($ffprobe == null) {
                    TempCache::put($ffprobe_cache_key, $json, Config::cache_duration);
                } else {
                    $json = '{}';
                    TempCache::put($ffprobe_cache_key, $json, Config::cache_duration_failed);
                }
            }
        }

        $video_codec = null;
        $video_bit_rate = 0;
        $video_width = 0;
        $video_height = 0;
        $video_fps = 0;
        $audio_codec = null;
        $audio_bit_rate = 0;
        $audio_channels = 0;
        $pubDate = null;
        $duration = 0;
        if ($ffprobe !== null) {
            $pubDate = $ffprobe->format->tags->creation_time ?? date('c', filemtime($file_abspath));
            $duration = $ffprobe->format->duration ?? 0;
            if ($ffprobe->streams !== null) {
                foreach ($ffprobe->streams as $stream) {
                    if ($stream->codec_type == 'video') {
                        $video_codec = $stream->codec_name;
                        $video_bit_rate = $stream->bit_rate;
                        $video_width = $stream->width;
                        $video_height = $stream->height;
                        $afr = explode('/', $stream->avg_frame_rate);
                        if (count($afr) == 2) {
                            $video_fps = round(intval($afr[0]) / intval($afr[1]), 0);
                        }
                    }
                    if ($stream->codec_type == 'audio') {
                        $audio_codec = $stream->codec_name;
                        $audio_bit_rate = $stream->bit_rate;
                        $audio_channels = $stream->channels;
                    }
                }
            }
        } else {
            $title = $title + "!";
        }

        // video info
        $item->appendChild($rssdoc->createComment("${video_codec}=${video_width}x${video_height}x${video_fps}:${video_bit_rate}, ${audio_codec}=${audio_channels}:${audio_bit_rate}"));

        // $filesize_mb = round($ffprobe->format->size / 1024 / 1024);
        // $calculated_filesize_mb = round((($audio_bit_rate + $video_bit_rate) * $duration) / 8 / 1024 / 1024);
        // $item->appendChild($rssdoc->createComment("${filesize_mb}MB ${calculated_filesize_mb}MB"));
        $total_gb += $ffprobe->format->size / 1024 / 1024 / 1024;
        $total_duration += $duration;

        $item->appendChild($rssdoc->createElement('title', $title . ($video_codec !== null ? ' (' . $video_codec . ', ' . round($video_bit_rate / 1000000.0, 3) . ' MiB/s)' : '')));
        $item->appendChild($rssdoc->createElement('description', ''));
        $item->appendChild($rssdoc->createElement('link', $file_url));
        $item->appendChild($rssdoc->createElement('category', 'Adult'));
        $item->appendChild($rssdoc->createElement('category', 'VR'));

        if ($pubDate !== null) $item->appendChild($rssdoc->createElement('pubDate', $pubDate));
        if ($duration !== null) $item->appendChild($rssdoc->createElement('ls:duration', round($duration)));
        $item->appendChild($rssdoc->createElement('ls:image', $thumbnail_url));
        $item->appendChild($rssdoc->createElement('ls:content-type', $content_type));
        $item->appendChild($rssdoc->createElement('ls:content-layout', $content_layout));


        if ($media_subdir === Config::transcoded_subdir) {
            return;
        }

        // check against LittlStar PSVR recommendations
        $audio_ok = ($audio_codec == 'aac' && $audio_bit_rate <= Config::max_audio_bit_rate + 1000 && $audio_channels <= 2);
        $video_ok = ($video_codec == 'h264' && $video_bit_rate <= Config::max_video_bit_rate && $video_width <= Config::max_video_width && $video_height <= Config::max_video_height && $video_fps >= Config::min_video_fps && $video_fps <= Config::max_video_fps);

        $transcode_media_file = Config::transcode_media_path . DIRECTORY_SEPARATOR . $fileinfo->getFilename();
        # $transcoded_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . $transcoded_subdir;
        $transcoded_path = Config::transcode_destination_path;
        $transcoded_file = $transcoded_path . DIRECTORY_SEPARATOR . $t . '.mp4';
        if ((!$video_ok || !$audio_ok) && !file_exists($transcoded_file)) {

            if ($video_bit_rate > Config::max_video_bit_rate) $video_bit_rate = Config::max_video_bit_rate;
            if ($video_width > Config::max_video_width) $video_width = Config::max_video_width;
            if ($video_height > Config::max_video_height) $video_height = Config::max_video_height;
            if ($audio_bit_rate > Config::max_audio_bit_rate) $audio_bit_rate = Config::max_audio_bit_rate;
            if ($audio_channels > Config::max_audio_channels) $audio_channels = Config::max_audio_channels;

            # $filter_v = "scale='min(2560,iw)':min'(2560,ih)':force_original_aspect_ratio=decrease";
            # $filter_complex = "scale=iw*min(1\,min(${max_video_width}/iw\,${max_video_height}/ih)):-1";
            $filter_complex = "scale=${video_width}:${video_height}";
            if ($video_fps < Config::min_video_fps) {
                $filter_complex .= ',minterpolate=fps=' . strval(Config::max_video_fps) . ':mi_mode=blend';    // other modes are very slow!
            }
            $filter_complex .= ",format=yuv420p";

            $transcode_command = Config::transcode_ffmpeg_path . 'ffmpeg' .
                ' -threads 4' .
                ' -hide_banner' .
                (Config::transcode_preview ? ' -discard nokey -noaccurate_seek -ss "00:10:00"' : '') .
                ' -i ' . escapeshellarg($transcode_media_file) .
                (Config::transcode_preview ? ' -frames:v 1000' : '') .
                ($video_ok ? ' -codec:v copy' : (
                    #' -filter:v "scale='min(1280,iw)':min'(720,ih)':force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2"' .
                    #' -filter:v ' . escapeshellarg($filter_v) .
                    #' -vf fps=60' .
                    # -vsync vfr -r 60
                    ' -filter_complex ' . escapeshellarg($filter_complex) .
                    ' -codec:v libx264' .
                    (Config::transcode_preview ? ' -preset veryfast' : ' -preset veryfast') .   // see graph at https://trac.ffmpeg.org/wiki/Encode/H.264
                    #(isset(Config::transcode_crf) ? ' -crf ' . strval(Config::transcode_crf) : '') .
                    #' -vf format=yuv420p' .
                    #' -filter:v minterpolate -r 60' .
                    ' -b:v ' . strval($video_bit_rate) .
                    #' -minrate ' . strval($video_bit_rate) .
                    ' -maxrate ' . strval($video_bit_rate) .
                    ' -bufsize ' . strval(round($video_bit_rate * 1.0)))) .      // ????
                ($audio_ok ? ' -codec:a copy' : ' -ac ' . strval($audio_channels) . ' -c:a aac -b:a ' . strval($audio_bit_rate)) .
                (Config::transcode_overwrite ? ' -y' : ' -n') .
                ' ' . escapeshellarg($transcoded_file) .
                ''; #' || rm ' . escapeshellarg($transcoded_file);

            $item->appendChild($rssdoc->createComment('######## ' . $transcode_command . ' ########'));

            $calculated_filesize_gb = (($audio_bit_rate + $video_bit_rate) * $duration / 8) / 1024 / 1024 / 1024;
            $transcode_total_gb += $calculated_filesize_gb;
            $transcode_total_duration += $duration;
            array_push($transcode_commands, $transcode_command);
        }
    }
}

foreach (Config::media_subdirs as $media_subdir) {
    $media_abspath = dirname(__FILE__) . DIRECTORY_SEPARATOR . $media_subdir;
    $fileinfos = new DirectoryIterator($media_abspath);
    foreach ($fileinfos as $fileinfo) {
        processFile($media_abspath, $media_subdir, $fileinfo);
    }
}

$rssdoc->appendChild($rssdoc->createComment(implode("\n", $transcode_commands)));
$rssdoc->appendChild($rssdoc->createComment("video GB: ${total_gb}"));
$rssdoc->appendChild($rssdoc->createComment("video hours: " . strval($total_duration / 3600)));
$rssdoc->appendChild($rssdoc->createComment("transcode GB required: ${transcode_total_gb}"));
$rssdoc->appendChild($rssdoc->createComment("transcode hours: " . strval($transcode_total_duration / 3600)));
$rssdoc->appendChild($rssdoc->createComment("transcode days (1x): " . strval($transcode_total_duration / 3600 / 24 / 1)));

$rssdoc->formatOutput = true;

echo $rssdoc->saveXML();

if ($got_lock) {
    fclose($fp_lock);
}

try {
    $fp = fopen(dirname(__FILE__) . DIRECTORY_SEPARATOR . Config::transcoded_subdir . DIRECTORY_SEPARATOR . 'transcode.cmd', 'wt');
    fwrite($fp, implode("\n", $transcode_commands));
    fclose($fp);
} catch (Exception $e) {
}
