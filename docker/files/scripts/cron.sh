#!/bin/sh

export CONFIG_DIRECTORY="/usr/local/aspen-discovery/sites/${SITE_NAME}"

# Check if site configuration exists
confSiteFile="$CONFIG_DIRECTORY/conf/config.ini"
if [ ! -f "$confSiteFile" ] ; then
    echo "ERROR: Site configuration not initialized. Skipping cron startup and waiting"
    sleep 5
	exit 1
fi

# Move and create temporarily sym-links to etc/cron directory
sanitizedSitename=$(echo "$SITE_NAME" | tr -dc '[:alnum:]_')
#cp "$CONFIG_DIRECTORY/conf/crontab_settings.txt" "/etc/cron.d/$sanitizedSitename"
cp "$CONFIG_DIRECTORY/conf/crontab" "/etc/cron.d/$sanitizedSitename"
chmod 644 "/etc/cron.d/$sanitizedSitename"

# Adjust permissions if required
if [[ ! -z "${LOCAL_USER_ID}" && "${LOCAL_USER_ID}" != "33" ]]; then
    sudo -u ${LOCAL_USER_ID} php /usr/local/aspen-discovery/code/web/cron/checkBGProcessesDocker.php "${SITE_NAME}" &
fi

crontab "/etc/cron.d/$sanitizedSitename"
cron -f -L 2
