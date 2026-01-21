#!/bin/bash
echo "🚀 Verificando entorno de desarrollo local..."

# 1. Check Apache
if systemctl is-active --quiet httpd; then
    echo "✅ Apache is running"
else
    echo "❌ Apache is DOWN. Starting..."
    sudo systemctl start httpd
fi

# 2. Check MariaDB
if systemctl is-active --quiet mariadb; then
    echo "✅ MariaDB is running"
else
    echo "❌ MariaDB is DOWN. Starting..."
    sudo systemctl start mariadb
fi

# 3. Check PHP-FPM (phpenv)
if pgrep -f "php-fpm" > /dev/null; then
    echo "✅ PHP-FPM is running"
else
    echo "⚠️ PHP-FPM might be down. Starting..."
    # Asumimos que phpenv está en el path o usamos ruta absoluta
    export PATH="$HOME/.phpenv/bin:$HOME/.phpenv/shims:$PATH"
    eval "$(phpenv init -)"
    ~/.phpenv/versions/8.1.33/sbin/php-fpm
fi

echo "🌐 API URL: http://apidev.local"
echo "📂 Project: /home/developer/Escritorio/Antigravity/ninesys-apidev"
