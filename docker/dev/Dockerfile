FROM php:8.0-cli

# php:8.0-cli is built on Debian 11 (bullseye), which is past end of life. Its
# security pool has been removed from deb.debian.org: the package index still
# lists the files but fetching them returns 404, so `apt-get install` fails.
# The same pool is kept on archive.debian.org, whose Release files are no
# longer re-signed, hence Check-Valid-Until=false. This image is dev tooling
# only; nothing built here ships in the ZIP.
RUN sed -i 's|http://deb.debian.org/debian-security|http://archive.debian.org/debian-security|' /etc/apt/sources.list \
 && apt-get -o Acquire::Check-Valid-Until=false -o Acquire::Retries=5 update \
 && apt-get -o Acquire::Retries=5 install -y --no-install-recommends git unzip libzip-dev \
 && docker-php-ext-install pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
