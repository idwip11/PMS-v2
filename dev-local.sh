#!/usr/bin/env bash
set -euo pipefail

PHP_BIN="/usr/local/opt/php@8.3/bin/php"
MYSQL_ADMIN="/Applications/XAMPP/xamppfiles/bin/mysqladmin"
MYSQL_SOCKET="/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"
XAMPP_CTL="/Applications/XAMPP/xamppfiles/xampp"
PMA_PHP="/Applications/XAMPP/xamppfiles/bin/php-8.0.28"
PMA_ROOT="/Applications/XAMPP/xamppfiles/phpmyadmin"
PMA_PID=""

if [[ ! -x "$PHP_BIN" ]]; then
    echo "PHP 8.3 lokal tidak ditemukan di $PHP_BIN"
    exit 1
fi

if ! "$MYSQL_ADMIN" -uroot --socket="$MYSQL_SOCKET" ping >/dev/null 2>&1; then
    echo "Menyalakan MySQL XAMPP..."
    osascript -e "do shell script \"$XAMPP_CTL startmysql\" with administrator privileges"
fi

if ! curl -fsS http://localhost:8081/ >/dev/null 2>&1; then
    "$PMA_PHP" -S localhost:8081 -t "$PMA_ROOT" >writable/logs/phpmyadmin-local.log 2>&1 &
    PMA_PID="$!"
fi

cleanup() {
    if [[ -n "$PMA_PID" ]]; then
        kill "$PMA_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT INT TERM

echo "HomiQ PMS berjalan di http://localhost:8080"
echo "phpMyAdmin berjalan di http://localhost:8081"
"$PHP_BIN" spark serve --host localhost --port 8080
