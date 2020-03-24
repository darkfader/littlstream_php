# What is this?
- Serve media files for use with LittlStar on PS4.
- Use built-in webserver with PHP.
- Tested on QTS (QNAP NAS).
- Use ffprobe to get media information. TempCache used to save it.
- Use ffmpeg to create corrected thumbnails.
- Not sure if this is really useful as there is a DLNA option too. (transcoding?, RTSP?)

# Configuration
You need to SSH/SCP the files to your NAS. No neat package here yet. QTS packages can have a symlink created to their web subfolder though.
See config.php for some parameters.

## Set up Web folder
Enable web server on QTS. It will create a Web share.
Set up the repo folder in the Web directory. Check `http://192.168.x.x/littlstream_php/`.
Visit https://my.littlstar.com/feeds to add this stream.
You can filter using query parameters: (negative filter using underscore `key_=value`)
- `?l=ou` to only show over/under.
- `?c=180` to only show 180 degrees VR.
- `?p=/^a/i` to only show titles starting with a or A. Remember to URL-encode this pattern as `%2F%5Ea%2Fi`.
- `?p=good` to use a regexp preset.
- `?t=1` to only show transcoded videos. By default show transcoded but hide original.
- `?x=0` to hide videos that needs transcoding.
Combined examples:
- `http://test-net-3-1.darkfader.net/littlstream_php/?c=180&l=sbs&x=0&p_=bad`  (query is hidden in LittlStar...)
- `http://test-net-3-1.darkfader.net/littlstream_php/c=180/l=sbs/x=0/p_=bad`  (provided you use .htaccess rewrite rule)

## Titles
- Title shows removed _ou _360 etc. from filenames.
- Suffix * for transcoded files.
- Suffix & to show transcoding required.
- It also appends video codec name and bitrate.

## Link media folder for serving thumbnails and media files
Example:
```
ln -s /share/CACHEDEV2_DATA/R18/VR/180_sbs /share/CACHEDEV2_DATA/Web/littlstream_php/media
```

## Transcoding
Work-in-progress. Some checks are in place for PSVR and some comment in XML output shows command for conversion. My NAS is pretty slow. (also why I don't have a better VR headset)
```
sudo chown httpdusr transcoded
sudo chmod 775 transcoded
```

# Development

## Public IP, testing, ...
For testing purposes, I hooked up my PS4 to my second NAS ethernet port. Enable DHCP on public IP range 203.0.113.x (TEST-NET-1 range).
Also, I mapped test-net-3-1.darkfader.net to 203.0.113.1 so that my PS4 can get the feed from `http://test-net-3-1.darkfader.net/littlstream_php/`.
Visit https://my.littlstar.com/feeds to add this stream.
Also had to enable NAT, so internet traffic returned to PS4 goes via my NAS.
Create/edit `/etc/config/autorun.sh`:
```
iptables -t nat -A POSTROUTING -o eth0 -j SNAT --to 192.168.2.247
```
I don't know how the the official channels work, but I may simulate it later using some magic.

## PHP reload, testing
/etc/default_config/php.ini
/mnt/HDA_ROOT/.config/php.ini
Lower `opcache.revalidate_freq = 60` value to 1 or so? Or totale disable it with `opcache.enable` / `opcache.validate_timestamps`.
`/etc/init.d/Qthttpd.sh restart` to apply settings?

## Mount this folder
Use SMB (check folder permissions!) or enable SFTP (not recommended) on NAS so the Web folder can be mounted for development.
```
mkdir /tmp/littlstream_php
diskutil unmount force /tmp/littlstream_php
sshfs -o cache=no admin@chii.local:/share/CACHEDEV2_DATA/Web/littlstream_php /tmp/littlstream_php
```

## Test RSS from development machine
```
curl "http://192.168.2.247/littlstream_php/"
```

## Unmount development folder
```
umount /tmp/littlstream_php
```

## References
- https://www.w3schools.com/Xml/xml_rss.asp#rssref
- https://littlstar.zendesk.com/hc/en-us/articles/360028563112-RSS-Feeds-EN-
- https://littlstar.zendesk.com/hc/en-us/articles/360030455112-Media-Encoding-Guidelines
- https://ffmpeg.org/documentation.html
- https://ffmpeg.org/ffmpeg-codecs.html#libx264_002c-libx264rgb
- https://trac.ffmpeg.org/wiki/Encode/AAC

## TODO
- Multiple/recursive directories. One per format?
- Remember failed conversions
- Get HEVC to work?
- Convert videos to lower bitrate (in background)
- Correct various VR camera for PSVR. How to detect?
- Simulate official channels. Custom feed is subscription only?
- Add some more filters. Multiple queries of same type.