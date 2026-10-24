#!/bin/bash
set -uo pipefail
mkdir -p applied-mobile-ab
for trial in baseline-1 analytics-1 ads-1 both-1 both-2 ads-2 analytics-2 baseline-2; do
 mode="$(echo "$trial" | cut -d- -f1)"
 set --
 if [[ "$mode" == analytics || "$mode" == both ]]; then
   set -- "$@" '--blocked-url-patterns=*googletagmanager.com/*' '--blocked-url-patterns=*google-analytics.com/*' '--blocked-url-patterns=*analytics.google.com/*'
 fi
 if [[ "$mode" == ads || "$mode" == both ]]; then
   set -- "$@" '--blocked-url-patterns=*googlesyndication.com/*' '--blocked-url-patterns=*doubleclick.net/*' '--blocked-url-patterns=*googleadservices.com/*'
 fi
 echo "Running $trial"
 npx --no-install lighthouse https://esarkariyojna.com/hi/ --only-categories=performance --throttling-method=devtools --chrome-flags="--headless=new --no-sandbox --disable-dev-shm-usage" --output=json --output-path="applied-mobile-ab/$trial" --quiet "$@"
 printf '%s %s\n' "$trial" "$?" >> applied-mobile-ab/exits.txt
done
python3 scripts/summarize_applied_mobile_ab.py
