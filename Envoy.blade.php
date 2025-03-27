@servers(['production' => ['root@203.194.113.123']])

@setup
    $repo = 'https://github.com/DanuCandra/test-dev.git';
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
    prepare
    git
    install
    live
@endstory

@task('prepare', ['on' => 'production'])
    set -e
    echo "🔹 Membuat direktori build jika belum ada..."
    mkdir -p {{ $builds }}

    echo "🔹 Memeriksa apakah file .env dan storage tersedia..."
    [ -f {{ $env }} ] || echo "⚠️  Warning: .env file not found!"
    [ -d {{ $storage }} ] || echo "⚠️  Warning: Storage directory not found!"
@endtask

@task('git', ['on' => 'production'])
    set -e
    echo "🔹 Meng-clone repository..."
    git clone -b {{ $branch }} "{{ $repo }}" {{ $deployment }}
@endtask

@task('install', ['on' => 'production'])
    set -e
    cd {{ $deployment }}

    echo "🔹 Menghapus storage lama jika ada..."
    rm -rf {{ $deployment }}/storage

    echo "🔹 Membuat symbolic link untuk .env dan storage..."
    ln -nfs {{ $env }} {{ $deployment }}/.env
    ln -nfs {{ $storage }} {{ $deployment }}/storage

    echo "🔹 Menginstall dependencies dengan Composer..."
    composer install --prefer-dist --no-dev --no-interaction --quiet

    echo "🔹 Menjalankan migrasi database..."
    php artisan migrate --force
@endtask

@task('live', ['on' => 'production'])
    set -e
    cd {{ $deployment }}

    echo "🔹 Memperbarui symlink untuk aplikasi..."
    ln -nfs {{ $deployment }} {{ $serve }}

    echo "🔹 Mengatur kepemilikan file..."
    chown -R www-data: {{ $deployment }}

    echo "🔹 Restart PHP-FPM dan Nginx..."
    systemctl restart php8.3-fpm || systemctl restart php-fpm
    systemctl restart nginx
@endtask
