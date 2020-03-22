# What is this?
- Serve media files for use with LittlStar on PS4.
- Use built-in webserver with PHP.
- Tested on QTS (QNAP NAS).
- Use ffprobe to get media information. TempCache used to save it.
- Use ffmpeg to create corrected thumbnails.

# Configuration
You need to SSH/SCP the files to your NAS. No neat package here.

## Public IP / NAS
Not sure if required but public IP may be required. What I did was to hook my PS4 to my second NAS ethernet port.
Enable DHCP on public IP range 203.0.113.x (TEST-NET-1 range).
Also, I mapped test-net-3-1.darkfader.net to 203.0.113.1 so that my PS4 can get the feed from `http://test-net-3-1.darkfader.net/littlstream_php/`.
Visit https://my.littlstar.com/feeds to add this stream.
Also had to enable NAT, so internet traffic returned to PS4 goes via my NAS.
Create/edit `/etc/config/autorun.sh`:
```
iptables -t nat -A POSTROUTING -o eth0 -j SNAT --to 192.168.2.247
```

## Webserver, PHP reload
Enable web server on QTS. It will create a Web share.
/etc/default_config/php.ini
/mnt/HDA_ROOT/.config/php.ini
Lower `opcache.revalidate_freq = 60` value to 1 or so? Or totale disable it with `opcache.enable` / `opcache.validate_timestamps`.
`/etc/init.d/Qthttpd.sh restart` to apply settings?

## Link media folder for serving thumbnails and media files
Example:
```
ln -s /share/CACHEDEV2_DATA/R18/VR/180_sbs /share/CACHEDEV2_DATA/Web/littlstream_php/media
```

# Development

## mount this folder
Enable SFTP on NAS so the Web folder can be mounted for development.
```
mkdir /tmp/littlstream_php
diskutil unmount force /tmp/littlstream_php
sshfs -o cache=no admin@chii.local:/share/CACHEDEV2_DATA/Web/littlstream_php /tmp/littlstream_php
```

## test RSS from development machine
```
curl "http://192.168.2.247/littlstream_php/"
```

## unmount development folder
```
umount /tmp/littlstream_php
```

## references
- https://www.w3schools.com/Xml/xml_rss.asp#rssref
- https://littlstar.zendesk.com/hc/en-us/articles/360028563112-RSS-Feeds-EN-

## TODO
- Multiple/recursive directories. One per format?
- Remember failed conversions
- Get HEVC to work
- Convert videos to lower bitrate (in background)
- Correct various VR camera for PSVR. How to detect?
