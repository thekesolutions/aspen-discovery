#Base it on Debian 10
FROM debian:buster-slim

#Install deps
RUN apt -y update \
  && apt -y install \
  apt-utils \
  wget \
  apache2 \
  apt-transport-https \
  lsb-release \
  ca-certificates \
  curl \
  git \
  vim \
  bind9 \
  bind9utils \
  wget \
  software-properties-common \
  default-jdk \
  openjdk-11-jdk \
  unzip \
  rng-tools \
  python-certbot-apache \
  mariadb-client \
  expect \
  sudo \
  cron \
  locales \
  gnupg2 \
  && rm -rf /var/cache/apt/archives/* \
  && rm -rf /var/lib/apt/lists/*

RUN sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen && \
  dpkg-reconfigure --frontend=noninteractive locales && \
  update-locale LANG=en_US.UTF-8

ENV LANG en_US.UTF-8 
ENV LC_ALL en_US.UTF-8

#Add php repo
RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
  && sh -c 'echo "deb https://packages.sury.org/php/ buster main" > /etc/apt/sources.list.d/php.list'



#Install php deps
RUN apt -y update \
  && apt install -y \
  php8.0 \
  php8.0-mcrypt \
  php8.0-gd \
  php8.0-curl \
  php8.0-mysql \
  php8.0-zip \
  php8.0-xml \
  php8.0-intl \
  php8.0-mbstring \
  php8.0-xdebug \
  lsof \
  && rm -rf /var/cache/apt/archives/* \
  && rm -rf /var/lib/apt/lists/*

# Prepare php configuration
RUN mkdir /templates

COPY php.ini_template /templates/php.ini

# Load apache modules
RUN a2enmod rewrite

# Add aspen-discovery
RUN cd /usr/local \
  && git clone --depth=1 https://github.com/mdnoble73/aspen-discovery.git \
  && rm -rf ./aspen-discovery/.git

# Create temp smarty directories
RUN cd /usr/local/aspen-discovery \
  && mkdir tmp \
  && chown -R www-data:www-data tmp \
  && chmod -R 755 tmp

# Create users
RUN cd /usr/local/aspen-discovery/install \
  && sed -i 's/adduser/useradd/g' setup_aspen_user_debian.sh \
  && mkdir -p /var/log/aspen-discovery \
  && bash /usr/local/aspen-discovery/install/setup_aspen_user_debian.sh \
  && mkdir -p /data/aspen-discovery/test.localhostaspen/solr7 \
  && cp -r /usr/local/aspen-discovery/data_dir_setup/solr7 /data/aspen-discovery/test.localhostaspen \
  && rm -R /usr/local/aspen-discovery/

COPY dockerrun.sh /

RUN chmod +x /dockerrun.sh

ENTRYPOINT [ "/dockerrun.sh" ]
CMD [ "sleep", "infinity" ]
# Increase entropy
#RUN cp /usr/local/aspen-discovery/install/limits.conf /etc/security/limits.conf \
#    && cp /usr/local/aspen-discovery/install/rngd.service /etc/systemd/system/rngd.service \
#    && systemctl daemon-reload \
#    && systemctl start rngd