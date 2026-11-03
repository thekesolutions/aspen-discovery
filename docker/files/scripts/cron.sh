#!/bin/bash

# Colors for log output
RED='\033[0;31m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

log() {
    local level="${1:-INFO}"
    local message="$2"
    local color=""

    case "$level" in
        ERROR) color="$RED" ;;
        WARN)  color="$YELLOW" ;;
    esac

    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] [CRON] [${color}${level}${NC}] ${message}"
}

log_info()  { log "INFO" "$1"; }
log_warn()  { log "WARN" "$1"; }
log_error() { log "ERROR" "$1"; }

log_info "Starting Cron initialization"

export CONFIG_DIRECTORY="/usr/local/aspen-discovery/sites/${SITE_NAME}"

# Check if site configuration exists
confSiteFile="$CONFIG_DIRECTORY/conf/config.ini"

tries=0
MAX_TRIES=10

while [ ! -f "$confSiteFile" ]; do

	log_info "Waiting for site configuration: $confSiteFile... (attempt $tries/$MAX_TRIES)"
	sleep 5
	((tries++))

	if [ $tries -eq $MAX_TRIES ] ; then
		log_error "Site configuration not initialized. Skipping cron startup and waiting..."
		exit 1
	fi

done

# Background processes read and write the same data folders as the web server
source /usr/local/aspen-discovery/docker/files/scripts/linkDataDirs.sh
link_data_dirs

# Rebuild the site crontab from the image on every start, so crontab fixes reach
# existing installs. Site-specific jobs belong in crontab_local, which is appended.
crontabTemplate="/usr/local/aspen-discovery/docker/files/cron/crontab"
siteCrontab="$CONFIG_DIRECTORY/conf/crontab"
localCrontab="$CONFIG_DIRECTORY/conf/crontab_local"

newCrontab=$(mktemp)
sed "s|{sitename}|${SITE_NAME}|g" "$crontabTemplate" > "$newCrontab"
if [ -f "$localCrontab" ]; then
	log_info "Appending $localCrontab"
	{ echo; cat "$localCrontab"; echo; } >> "$newCrontab"
fi

if [ -f "$siteCrontab" ] && ! cmp -s "$newCrontab" "$siteCrontab"; then
	cp "$siteCrontab" "$siteCrontab.bak"
	log_warn "$siteCrontab was replaced, previous version saved as crontab.bak; put site-specific jobs in crontab_local"
fi
install -m 644 "$newCrontab" "$siteCrontab"
rm -f "$newCrontab"

log_info "Setting crontab to be executed"
crontab -u root "$siteCrontab" >/proc/1/fd/1 2>/proc/1/fd/2

log_info "Starting Cron in foreground mode..."
# Start cron daemon
cron -f -L 15 >/proc/1/fd/1 2>/proc/1/fd/2
