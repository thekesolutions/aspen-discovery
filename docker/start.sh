#!/bin/bash

#for USER in mysql www-data solr;do
#        sudo usermod -a -G $LINUX_GROUP_ID $USER
#done

siteDir="/mnt/_usr_local_aspen-discovery_sites_${SITE_NAME}"

if [ ! -d "$siteDir" ] && [ "$ENABLE_APACHE" == "yes" ] ; then
	#First execution

	if [ ! -z "$DATABASE_ROOT_PASSWORD" ] ; then
		#Assign permissions to $DBUSER over $ASPEN_DBName
		mysql -u$DATABASE_ROOT_USER -p$DATABASE_ROOT_PASSWORD -h$DATABASE_HOST -P$DATABASE_PORT -e "create user '$DATABASE_USER'@'%' identified by '$DATABASE_PASSWORD'; grant all on $DATABASE_NAME.* to '$DATABASE_USER'@'%'; flush privileges;"
	fi

  cd /usr/local/aspen-discovery/docker || exit

  #Create pertinent configurations
  confDir="/usr/local/aspen-discovery/sites/${SITE_NAME}"
  mkdir "$confDir"
  php createConfig.php "$confDir"

  #Create and initialize Koha's configurations
  php initKohaLink.php "$confDir/conf/config.ini" "$confDir/conf/config.pwd.ini"

  #Create and initialize database
  php initDatabase.php "$confDir/conf/config.pwd.ini"

  #Create aspen directories
  php createDirs.php "$confDir/conf/config.ini"

	#Delete apache's default site
	unlink /etc/apache2/sites-enabled/000-default.conf
	unlink /etc/apache2/sites-enabled/httpd-$SITE_NAME.conf
	cp /etc/apache2/sites-available/httpd-$SITE_NAME  /etc/apache2/sites-enabled/httpd-$SITE_NAME

	#Change the priority (for Aspen sign in purposes)
	mysql -u$DATABASE_USER -p$DATABASE_PASSWORD -h$DATABASE_HOST -P$DATABASE_PORT $DATABASE_NAME -e "update account_profiles set weight=0 where name='admin'; update account_profiles set weight=1 where name='ils';"

	#Copy data within a persistent volume
	for i in ${PERSISTENT_DIRECTORIES[@]}; do
		dir=$(echo $i | sed 's/\//_/g'); 
		rsync -al $i/ /mnt/$dir; 
	done

fi

#Create symbolic links of persistent volumes
for i in ${PERSISTENT_DIRECTORIES[@]}; do
	dir=$(echo $i | sed 's/\//_/g'); 
	mv $i $i-back; 
	ln -s /mnt/$dir $i; 
done

#Wait for mysql responses
while ! nc -z "$DATABASE_HOST" "$DATABASE_PORT"; do sleep 3; done

#Turn on apache
if [ "$ENABLE_APACHE" == "yes" ]; then
	service apache2 start 
fi

#Turn on Cron
if [ "$ENABLE_CRON" == "yes" ]; then
	service cron start 
	php /usr/local/aspen-discovery/code/web/cron/checkBackgroundProcesses.php $SITE_NAME &
fi


#Infinite loop
/bin/bash -c "trap : TERM INT; sleep infinity & wait"

