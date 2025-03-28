SETUP VPS LARAVEL CI CD
composer require laravel/envoy --dev
php vendor/bin/envoy run deploy

SERVER PREPARATION
1.	Install acl 
2.	Install nginx 
3.	Install PHP 
4.	install composer
5.	install mysql server
6.	install git
7.	install redis server

install acl
- apt install acl

install nginx
- apt install nginx

install php
- apt install software-properties-common
- add-apt-repository ppa:ondrej/php
- apt install php8.3 php8.3-fpm php8.3-mysql php8.3-common php8.3-cli php8.3-cgi php8.3-curl php8.3-gd php8.3-mbstring php8.3-intl php8.3-sqlite3 php8.3-xsl php8.3-xml php8.3-zip php8.3-memcached php8.3-opcache

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

3. chmod +x install-composer.sh
4. run ./install-composer.sh
5. mv composer.phar /usr/bin/composer


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


Persiapan sebelum deployment
1. buat directory baru di /var/www -uwww-data mkdir -p storage/framework/sessions
2. buat directory baru di /var/www -uwww-data mkdir -p storage/framework/views
3. buat directory baru di /var/www -uwww-data mkdir -p storage/framework/cache
4. buat file .env di /var/www -uwww-data touch .env

APP_NAME=envoyapp
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://envoy.labkita.my.id

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=envoy
DB_USERNAME=root
DB_PASSWORD=password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

5. buat file Envoy.blade.php di root laravel directory
   @servers(['production' => ['root@xxx.xxx.xxx.xxx']])
 
@setup
    $repo = 'https://github.com/DanuCandra/testing-deploy.git';
    $appDir = '/var/www';
    $branch = 'main';

    date_default_timezone_set('Asia/Jakarta');
    $date = date('YmdHis');

    $builds = $appDir . '/sources';
    $deployment = $builds . '/' . $date;

    $serve = $appDir . '/source';
    $env = $appDir . '/.env';
    $storage = $appDir . '/storage';
@endsetup

@story('deploy')
    git
    install
    live
@endstory

@task('git', ['on' => 'production'])
    git clone -b {{ $branch }} "{{ $repo }}" {{ $deployment }}
@endtask

@task('install', ['on' => 'production'])
    cd {{ $deployment }}

    rm -rf {{ $deployment }}/storage
    
    ln -nfs {{ $env }} {{ $deployment }}/.env
    
    ln -nfs {{ $storage }} {{ $deployment }}/storage

    composer install --prefer-dist --no-dev
    
    php ./artisan migrate --force
@endtask

@task('live', ['on' => 'production'])
    cd {{ $deployment }}
    
    ln -nfs {{ $deployment }} {{ $serve }}
    
    chown -R www-data: /var/www

    systemctl restart php8.3-fpm

    systemctl restart nginx
@endtask

Proses Deployment
1. jalankan perintah ./vendor/bin/envoy run deploy
2. generate app key dengan menjalankan perintah php artisan key:generate di folder /var/www/source
3. penyesuaian folder permission dengan perintah chown -R www-data: /var/www/


MENGGUANAKAN GITHUB ACTION
- buka repository menu settings -> secrets -> menu
- tambahkan secret baru SSH_PRIVATE_KEY value nya ambdil dari private key
- private key dilocal bisa lihat file ini cat ~/.ssh/id_rsa
- tambahkan secret baru SSH_HOST value nya isi alamat ip server
- tambahkan deploy keys dengan menggunakan public key (optional)
- public key dilocal bisa lihat file ini cat ~/.ssh/id_rsa.pub
- private key / public key jika belum ada, bisa generate secara manual dengan perintah ssh-keygen lalu enter sampai selesai
- buat folder baru .github/workflows di laravel app root dir
- buat file baru deploy.yml di workflows folder
- edit file deploy.yml tambahkan ini:
name: deploy

on: 
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
            php-version: 8.3
            tools: composer:v2
            coverage: none
      - name: Install Composer dependencies
        run: composer update
      - name: Setup SSH
        uses: kielabokkie/ssh-key-and-known-hosts-action@v1.2.0
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}
          ssh-host: ${{ secrets.SSH_HOST }}
      - name: Deploy Environment
        run: ./vendor/bin/envoy run deploy

