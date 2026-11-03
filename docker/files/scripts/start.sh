#!/bin/bash
set -e

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

    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] [BACKEND] [${color}${level}${NC}] ${message}"
}

log_info()  { log "INFO" "$1"; }
log_warn()  { log "WARN" "$1"; }
log_error() { log "ERROR" "$1"; }

echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%                                                                           %%%%%%%%%%%%%"
echo "%%%%%%%%%%%%                      WELCOME TO ASPEN DISCOVERY                           %%%%%%%%%%%%%"
echo "%%%%%%%%%%%%                                                                           %%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%"
echo "                                        "
echo "                                        "
echo "                                        "
echo "                                        "
echo "                                        "


log_info "Aspen Discovery starting for: ${SITE_NAME}"

export CONFIG_DIRECTORY="/usr/local/aspen-discovery/sites/$SITE_NAME"

# Move to docker directory
cd "/usr/local/aspen-discovery/docker/files/scripts" || exit

# Check if site configuration exists
confSiteFile="$CONFIG_DIRECTORY/conf/config.ini"
if [ ! -f "$confSiteFile" ] ; then
	log_info "$confSiteFile not found. Generating..."
	mkdir -p "$CONFIG_DIRECTORY"
	if ! php createConfig.php "$CONFIG_DIRECTORY" ; then
		log_error "Failed to create instance config"
		exit 1
	fi
fi

# Sync environment variables to config files (runs every start)
log_info "Syncing environment variables to config..."
if ! php syncEnvToConfig.php ; then
	log_warn "Environment sync failed, using existing config"
fi

# Initialize Aspen database
log_info "Initializing database"
if ! php initDatabase.php ; then
	log_error "Database initialization failed"
	exit 1
fi

# Create the key used to encrypt sensitive database fields, an existing key is never replaced
log_info "Checking encryption key"
if ! php /usr/local/aspen-discovery/code/web/cron/createKeyFile.php "$SITE_NAME" || [ ! -f "$CONFIG_DIRECTORY/conf/passkey" ]; then
	log_error "Encryption key creation failed"
	exit 1
fi

# Create missing dirs and fix ownership and permissions if needed
log_info "Setting up data and log directories"
if ! php createDirs.php ; then
	log_error "Directories creation and permission fixes failed"
	exit 1
fi

# FIXME: This seems to be creating dirs like images/images, etc
#        It should be put outside the codebase, and mounted accordingly

# Move and create temporarily sym-links to data directory
source /usr/local/aspen-discovery/docker/files/scripts/linkDataDirs.sh
link_data_dirs

# Run pending database updates
log_info "Running pending database updates..."
php updateDatabase.php "$SITE_NAME"

log_info "Starting PHP-FPM in foreground mode..."
php-fpm8.4 --test && exec php-fpm8.4 -F