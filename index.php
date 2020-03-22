<?php
// ob_end_clean();

require_once 'config.php';
require_once 'TempCache.php';  // or APC, memcached, ...

use Cajogos\TempCache as TempCache;


// limit exec to single request
$fp_lock = fopen('/tmp/ffmpeg_lock.txt', 'a');
$got_lock = flock($fp_lock, LOCK_EX | LOCK_NB);


// $txt = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
// $myfile = file_put_contents('logs.txt', $txt . PHP_EOL, FILE_APPEND | LOCK_EX);

header('Cache-Control: no-cache');
header('Cache-Control: max-age=0');
header('Content-Type: application/rss+xml; charset=utf-8');


$rssdoc = new DOMDocument('1.0', 'UTF-8');
$rss = $rssdoc->createElement("rss");
$rss = $rssdoc->appendChild($rss);
$rss->setAttribute("xmlns:dc", "http://purl.org/dc/elements/1.1/");
$rss->setAttribute("xmlns:content", "http://purl.org/rss/1.0/modules/content/");
$rss->setAttribute("xmlns:atom", "http://www.w3.org/2005/Atom");
$rss->setAttribute("version", "2.0");
$rss->setAttribute("xmlns:ls", "https://www.littlstar.com");

$channels = array();

function getChannelForTitle($title)
{
    global $rssdoc, $rss, $channels, $feed_url, $feed_ttl;

    $e = preg_split('/[0-9-.]/', $title, 2);

    $key = $e[0];

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
    $image->appendChild($rssdoc->createElement('url', $feed_url . '/vr.jpg'));
    $image->appendChild($rssdoc->createElement('title', 'VR'));
    $image->appendChild($rssdoc->createElement('link', ''));

    return $channel;
}


$media_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . $media_subdir;
$dir = new DirectoryIterator($media_path);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $t = $fileinfo->getFilename();
        if (!preg_match('/(\\.mp4|\\.mov|\\.m4v|\\.m3u8|\\.mpd)$/', $t)) {
            continue;
        }
        $t = pathinfo($t, PATHINFO_FILENAME);

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


        $media_file = $media_path . DIRECTORY_SEPARATOR . $fileinfo->getFilename();
        $thumbnail_file = $media_path . DIRECTORY_SEPARATOR . $thumb_subdir . DIRECTORY_SEPARATOR . $t . '.jpg';

        $filter_v = '';
        if ($content_layout == 'sbs' || $content_layout == 'sbs_fr') $filter_v .= 'crop=in_w/2:in_h:0:0';
        if ($content_layout == 'ou' || $content_layout == 'ou_fr') $filter_v .= 'crop=in_w:in_h/2:0:0';
        if ($content_type == '180') $filter_v .= ', lenscorrection=cx=0.5:cy=0.5:k1=-.15:k2=-.15';
        if ($content_type == '360') $filter_v .= ', lenscorrection=cx=0.5:cy=0.5:k1=-.15:k2=-.15';  // TODO: check...
        $command = '/mnt/ext/opt/MultimediaConsole/medialibrary/bin/ffmpeg -discard nokey -hide_banner -noaccurate_seek -ss "00:10:00" -i ' . escapeshellarg($media_file) . ' -an -r 1 -frames:v 1 -codec:v mjpeg -filter:v ' . escapeshellarg($filter_v) . ' -f image2 -y ' . escapeshellarg($thumbnail_file);

        $json = TempCache::get($t . '_ffprobe');
        $ffprobe = $json !== null ? json_decode($json) : null;


        $channel = getChannelForTitle($title);
        $item = $rssdoc->createElement('item');
        $channel->appendChild($item);

        if ($got_lock) {    // allow exec?
            // take thumbnail
            if (!file_exists($thumbnail_file) && $ffprobe === null) {
                $item->appendChild($rssdoc->createComment($thumbnail_file));
                $item->appendChild($rssdoc->createComment($command));
                exec($command);
            }

            // ffprobe
            if ($ffprobe === null) {
                unset($output);
                exec('/mnt/ext/opt/MultimediaConsole/medialibrary/bin/ffprobe ' . escapeshellarg($media_file) . ' -show_entries streams:format -v quiet -of json', $output);
                $json = implode($output);
                $ffprobe = json_decode($json);
                if ($ffprobe == null) {
                    TempCache::put($t . '_ffprobe', $json, $cache_duration);
                } else {
                    $json = '{}';
                    TempCache::put($t . '_ffprobe', $json, $cache_duration_failed);
                }
            }
        }

        $codec = null;
        $bit_rate = 0;
        $pubDate = null;
        $duration = 0;
        if ($ffprobe !== null) {
            $pubDate = $ffprobe->format->tags->creation_time ?? date('c', filemtime($media_file));
            $duration = $ffprobe->format->duration ?? 0;
            if ($ffprobe->streams !== null) {
                foreach ($ffprobe->streams as $stream) {
                    if ($stream->codec_type == 'video') {
                        $codec = $stream->codec_name;
                        $bit_rate = $stream->bit_rate;
                    }
                }
            }
        } else {
            $title = "#" + $title;
        }

        $item->appendChild($rssdoc->createElement('title', $title . ($codec !== null ? ' (' . $codec . ', ' . round($bit_rate / 1000000.0, 3) . ' MiB/s)' : '')));
        $item->appendChild($rssdoc->createElement('description', ''));
        $item->appendChild($rssdoc->createElement('link', $feed_url . str_replace($media_path, $media_subdir, $media_file)));
        $item->appendChild($rssdoc->createElement('category', 'Adult'));
        $item->appendChild($rssdoc->createElement('category', 'VR'));

        if ($pubDate !== null) $item->appendChild($rssdoc->createElement('pubDate', $pubDate));
        if ($duration !== null) $item->appendChild($rssdoc->createElement('ls:duration', round($duration)));
        $item->appendChild($rssdoc->createElement('ls:image', $feed_url . str_replace($media_path, $media_subdir, $thumbnail_file)));
        $item->appendChild($rssdoc->createElement('ls:content-type', $content_type));
        $item->appendChild($rssdoc->createElement('ls:content-layout', $content_layout));
    }
}

$rssdoc->formatOutput = true;

echo $rssdoc->saveXML();

if ($got_lock) {
    fclose($fp_lock);
}
