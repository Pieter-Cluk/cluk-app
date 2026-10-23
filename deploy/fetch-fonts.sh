#!/usr/bin/env bash
# Downloads the website fonts (Anton, Barlow Condensed, Archivo, Plus Jakarta Sans,
# all SIL Open Font License) as woff2 into public/fonts and writes public/fonts/site-fonts.css.
# Runs during the deploy build, so visitors never load anything from Google.
set -euo pipefail
OUT=public/fonts
mkdir -p "$OUT"
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
URL='https://fonts.googleapis.com/css2?family=Anton&family=Archivo:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'
css=$(curl -fsSL -A "$UA" "$URL")
# keep only latin and latin-ext blocks
css=$(printf '%s\n' "$css" | awk '/^\/\* /{keep=($2=="latin"||$2=="latin-ext")} keep')
n=0
while read -r src; do
  f=$(printf '%s' "$src" | md5sum | cut -c1-12).woff2
  curl -fsSL "$src" -o "$OUT/$f"
  css=${css//"$src"/"/fonts/$f"}
  n=$((n+1))
done < <(printf '%s\n' "$css" | grep -o 'https://fonts.gstatic.com/[^)]*')
printf '%s\n' "$css" > "$OUT/site-fonts.css"
echo "fonts: $n files"
