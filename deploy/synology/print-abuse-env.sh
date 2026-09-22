#!/bin/sh
# Read-only configuration discovery for cloudflared -> gateway -> server.
# Run in the deployed Compose directory. Does not edit env files or restart services.
set -eu
PATH=/usr/local/bin:$PATH
export PATH

fail() { printf '%s\n' "$*" >&2; exit 1; }
public_url=${1:-}
case "$public_url" in
    https://*) ;;
    *) fail 'Usage: sh print-abuse-env.sh https://your-public-hostname' ;;
esac
command -v docker >/dev/null 2>&1 || fail 'docker must be available on PATH.'
command -v curl >/dev/null 2>&1 || fail 'curl must be available on PATH.'
[ -f production.env ] && [ -f compose.yaml ] || fail 'Run in the deployed directory containing production.env and compose.yaml.'

gateway_id=$(docker compose --env-file production.env ps -q gateway)
[ -n "$gateway_id" ] || fail 'No running gateway service was found.'
nginx_config=$(docker exec "$gateway_id" nginx -T 2>/dev/null) || fail 'Cannot inspect gateway Nginx configuration.'
if printf '%s\n' "$nginx_config" | grep -Eq '^[[:space:]]*(real_ip_header|set_real_ip_from|real_ip_recursive)[[:space:]]'; then
    fail 'Gateway already restores client IPs. Its trusted-proxy configuration needs inspection; no values were guessed.'
fi
printf '%s\n' "$nginx_config" | awk '$1=="log_format" && $2=="main" && index($3,"$remote_addr")==2 {found=1} END {exit !found}' || fail 'Unexpected Nginx log format; cannot safely identify the connection peer.'
printf '%s\n' "$nginx_config" | grep -Eq 'proxy_set_header[[:space:]]+X-Forwarded-For[[:space:]]+\$proxy_add_x_forwarded_for' || fail 'Gateway does not append the connection peer to X-Forwarded-For.'

gateway_ip=$(docker compose --env-file production.env exec -T server php -r 'echo gethostbyname("gateway");')
probe="codex_proxy_probe_$(od -An -N12 -tx1 /dev/urandom | tr -d ' \n')"
curl --silent --show-error --connect-timeout 10 --max-time 30 --output /dev/null \
    --header 'Cache-Control: no-cache' "${public_url%/}/?${probe}=1" || fail 'Public request failed.'
peer_ip=''
attempt=0
while [ "$attempt" -lt 5 ]; do
    peer_ip=$(docker logs --since 2m "$gateway_id" 2>&1 | awk -v marker="$probe" 'index($0, marker) {ip=$1} END {print ip}')
    [ -z "$peer_ip" ] || break
    attempt=$((attempt + 1))
    sleep 1
done
[ -n "$peer_ip" ] || fail 'The public request did not reach this gateway log. Check the Tunnel target or caching; no values were guessed.'

# Only accept private IPv4 peers for this NAS deployment. Fail rather than trust a visitor IP.
for candidate in "$gateway_ip" "$peer_ip"; do
    printf '%s\n' "$candidate" | awk -F. '
        NF != 4 {exit 1}
        {for (i=1;i<=4;i++) if ($i !~ /^[0-9]+$/ || $i > 255) exit 1}
        !($1==10 || $1==127 || ($1==172 && $2>=16 && $2<=31) || ($1==192 && $2==168)) {exit 1}
    ' || fail 'Expected a private IPv4 proxy address. This topology requires manual inspection.'
done
trusted="$gateway_ip/32"
[ "$peer_ip" = "$gateway_ip" ] || trusted="$trusted,$peer_ip/32"
printf '%s\n' "# Measured gateway: $gateway_ip; measured upstream peer: $peer_ip" >&2
printf '%s\n' '# Valid for a Tunnel connecting directly to gateway. Additional reverse proxies require their addresses too.' >&2
cat <<EOF
API_RATE_LIMIT_ENABLED=true
TRUSTED_PROXY_CIDRS=$trusted
GAME_GUEST_CALLS_PER_MINUTE=10
GAME_GUEST_CALLS_PER_DAY=120
GAME_USER_CALLS_PER_MINUTE=20
GAME_USER_CALLS_PER_DAY=300
GAME_IP_CALLS_PER_MINUTE=60
GAME_IP_CALLS_PER_DAY=1000
GAME_GLOBAL_CALLS_PER_DAY=10000
GAME_IP_CONCURRENCY=4
GAME_GLOBAL_CONCURRENCY=20
EOF
