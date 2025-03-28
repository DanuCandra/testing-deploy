SETUP VPS LARAVEL CI CD

SERVER PREPARATION
1.	Install acl 
2.	Install nginx 
3.	Install PHP 
4.	install composer
5.	install mysql server
6.	install git
7.	install redis server

install acl
apt install acl

install nginx
apt install nginx

install php
apt install software-properties-common
add-apt-repository ppa:ondrej/php
apt install php8.3 php8.3-fpm php8.3-mysql php8.3-common php8.3-cli php8.3-cgi php8.3-curl php8.3-gd php8.3-mbstring php8.3-intl php8.3-sqlite3 php8.3-xsl php8.3-xml php8.3-zip php8.3-memcached php8.3-opcache

install composer
1.	buat shell file install-composer.sh = nano install-composer.sh
#!/bin/sh

EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]
then
    >&2 echo 'ERROR: Invalid installer checksum'
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet
RESULT=$?
rm composer-setup.php
exit $RESULT
2. chmod +x install-composer.sh
3. run ./install-composer.sh
4. mv composer.phar /usr/bin/composer


apt install mysql-server
mysql -uroot -p if password is asked just enter
alter user root identified with mysql_native_password by 'password';
create database envoy;

install git
apt install git

install redis server
apt install redis-server

nginx configuration
1. backup /etc/nginx/sites-available/default
2. cp /etc/nginx/sites-available/default default.bak
3. adjust default config
     root /var/www/app2/current/public;
  index index.html index.htm index.nginddx-debian.html index.php;
  location / {
		try_files $uri $uri/ /index.php?$query_string;
	}
  location ~ .php$ {
		include snippets/fastcgi-php.conf;
		fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
	}
  location ~ /.ht {
		deny all;
	}

