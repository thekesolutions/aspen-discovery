#!/usr/bin/env bash
#

# This script is used to run the docker container
# cp -R /test.localhostaspen /usr/local/aspen-discovery/sites

service cron start;

chown -R aspen /data/aspen-discovery/test.localhostaspen/solr7;

service apache2 start;

su -c "/usr/local/aspen-discovery/sites/test.localhostaspen/test.localhostaspen.sh start" aspen;

crontab /etc/cron.d/cron

exec "$@"
