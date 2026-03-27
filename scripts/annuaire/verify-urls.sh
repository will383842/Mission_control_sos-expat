#!/bin/bash
# =============================================
# Verify all URLs in country_directory table
# Usage: ./verify-urls.sh [database_url]
# Output: report with status codes, flags 404s and 5xx
# =============================================

DB_URL="${1:-postgresql://localhost:5432/mission_control}"

echo "=== SOS-Expat Directory URL Verification ==="
echo "Date: $(date)"
echo ""

# Extract all URLs from DB
URLS=$(psql "$DB_URL" -t -A -c "SELECT id, url FROM country_directory WHERE is_active = true ORDER BY id")

TOTAL=0
OK=0
REDIRECT=0
BLOCKED=0
TIMEOUT=0
ERROR=0
DEAD=0

echo "ID|STATUS|URL" > /tmp/url-check-results.csv

while IFS='|' read -r id url; do
  [ -z "$url" ] && continue
  TOTAL=$((TOTAL + 1))

  code=$(curl -sL -o /dev/null -w "%{http_code}" --connect-timeout 8 --max-time 15 \
    -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36" \
    "$url" 2>/dev/null)

  echo "$id|$code|$url" >> /tmp/url-check-results.csv

  case $code in
    200) OK=$((OK + 1)) ;;
    301|302|303|307|308) REDIRECT=$((REDIRECT + 1)) ;;
    403|429) BLOCKED=$((BLOCKED + 1)) ;;  # Anti-bot, site works
    000) TIMEOUT=$((TIMEOUT + 1)); echo "TIMEOUT: $url" ;;
    404) DEAD=$((DEAD + 1)); echo "DEAD 404: $url (id=$id)" ;;
    5*) ERROR=$((ERROR + 1)); echo "ERROR $code: $url (id=$id)" ;;
    *) echo "UNUSUAL $code: $url (id=$id)" ;;
  esac

  # Progress every 50
  [ $((TOTAL % 50)) -eq 0 ] && echo "... checked $TOTAL URLs"
done <<< "$URLS"

echo ""
echo "=== RESULTS ==="
echo "Total:     $TOTAL"
echo "200 OK:    $OK"
echo "Redirect:  $REDIRECT"
echo "Anti-bot:  $BLOCKED (403/429, sites work but block bots)"
echo "Timeout:   $TIMEOUT (slow government sites)"
echo "404 Dead:  $DEAD"
echo "5xx Error: $ERROR"
echo ""

if [ "$DEAD" -gt 0 ] || [ "$ERROR" -gt 0 ]; then
  echo "ACTION REQUIRED: Fix or deactivate dead/error URLs above"
  echo "To deactivate: UPDATE country_directory SET is_active = false WHERE id IN (...);"
fi

echo ""
echo "Full results: /tmp/url-check-results.csv"
