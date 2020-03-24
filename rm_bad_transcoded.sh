#!/bin/sh
ffprobe -h >/dev/null 2>/dev/null || exit
for f in transcoded/*.mp4; do ffprobe "$f" 2>/dev/null || rm -v "$f"; done

