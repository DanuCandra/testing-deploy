# Setup VPS Laravel CI/CD

## Install Laravel Envoy
```sh
composer require laravel/envoy --dev
php vendor/bin/envoy run deploy
```

---

## Server Preparation
### Install Dependencies
```sh
apt update && apt upgrade -y
apt install -y acl nginx mysql-server git redis-server
```

### Install PHP
```sh
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-cli php8.3-curl php8.3-gd php8.3-mbstring php8.3-intl php8.3-sqlite3 php8.3-xsl php8.3-xml php8.3-zip php8.3-memcached php8.3-opcache
```

### Install Composer
1. Buat file `install-composer.sh`
```sh
nano install-composer.sh
```
2. Tambahkan kode berikut:
```sh
#!/bin/sh
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    >&2 echo 'ERROR: Invalid installer checksum'
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet
RESULT=$?
rm composer-setup.php
exit $RESULT
```
3. Jalankan perintah berikut:
```sh
chmod +x install-composer.sh
./install-composer.sh
mv composer.phar /usr/bin/composer
```

### Konfigurasi MySQL
```sh
mysql -u root -p
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'password';
CREATE DATABASE envoy;
```

---

## Konfigurasi Nginx
### Backup dan Edit Konfigurasi Default
```sh
cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak
nano /etc/nginx/sites-available/default
```
Tambahkan atau ubah konfigurasi berikut:
```nginx
server {
    listen 80;
    server_name envoy.labkita.my.id;
    root /var/www/app2/current/public;
    index index.html index.htm index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```
Restart Nginx:
```sh
systemctl restart nginx
```

---

## Install dan Konfigurasi phpMyAdmin dengan Nginx
### Install phpMyAdmin
```sh
apt install -y phpmyadmin
```
### Konfigurasi phpMyAdmin di Nginx
Buat konfigurasi baru untuk phpMyAdmin:
```sh
nano /etc/nginx/sites-available/phpmyadmin
```
Tambahkan konfigurasi berikut:
```nginx
server {
    listen 80;
    server_name db.server-danu.cloud;
    root /usr/share/phpmyadmin;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```
Simpan file dan buat symlink ke `sites-enabled`:
```sh
ln -s /etc/nginx/sites-available/phpmyadmin /etc/nginx/sites-enabled/
```
Restart Nginx:
```sh
systemctl restart nginx
```
Sekarang phpMyAdmin dapat diakses melalui `http://db.server-danu.cloud`.

---



## Persiapan Sebelum Deployment
Buat direktori yang dibutuhkan:
```sh
mkdir -p /var/www/storage/framework/{sessions,views,cache}
touch /var/www/.env
chown -R www-data:www-data /var/www/
```

### Konfigurasi `.env`
```sh
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"


```

---

## Konfigurasi Laravel Envoy
Buat file `Envoy.blade.php` di root proyek Laravel:
```blade
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
    php artisan migrate --force
@endtask

@task('live', ['on' => 'production'])
    cd {{ $deployment }}
    ln -nfs {{ $deployment }} {{ $serve }}
    chown -R www-data: /var/www
    systemctl restart php8.3-fpm
    systemctl restart nginx
@endtask
```

### Proses Deployment
```sh
./vendor/bin/envoy run deploy
php artisan key:generate
chown -R www-data: /var/www/
```

---

## Menggunakan GitHub Actions
### Konfigurasi Secrets
1. Buka **GitHub Repository** → **Settings** → **Secrets and variables** → **Actions**.
2. Tambahkan:
   - `SSH_PRIVATE_KEY` (isi dengan private key lokal: `cat ~/.ssh/id_rsa`)
   - `SSH_HOST` (alamat IP server)
3. Tambahkan **Deploy Keys** dengan public key (`cat ~/.ssh/id_rsa.pub`).

### Buat Workflow GitHub Actions
Buat folder `.github/workflows/` di root proyek Laravel, lalu buat file `deploy.yml`:
```yaml
name: Deploy Laravel App

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
      - name: Install Composer dependencies
        run: composer update
      - name: Setup SSH
        uses: kielabokkie/ssh-key-and-known-hosts-action@v1.2.0
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}
          ssh-host: ${{ secrets.SSH_HOST }}
      - name: Deploy Environment
        run: ./vendor/bin/envoy run deploy
```

---

## Selesai 🎉
Sekarang setiap kali melakukan push ke branch `main`, deployment akan berjalan otomatis!

---

