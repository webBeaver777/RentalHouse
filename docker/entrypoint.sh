#!/bin/sh
set -e

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL..."
until php -r "
try {
    new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');
    exit(0);
} catch (PDOException \$e) {
    exit(1);
}
" 2>/dev/null; do
    echo "PostgreSQL is unavailable - sleeping"
    sleep 2
done
echo "PostgreSQL is ready!"

# Wait for Redis to be ready
echo "Waiting for Redis..."
until nc -z ${REDIS_HOST:-redis} ${REDIS_PORT:-6379} 2>/dev/null; do
    echo "Redis is unavailable - sleeping"
    sleep 2
done
echo "Redis is ready!"

# Set appropriate permissions for storage and cache
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Install PHP dependencies if vendor directory is empty
if [ ! -d "/var/www/html/vendor" ] || [ -z "$(ls -A /var/www/html/vendor 2>/dev/null)" ]; then
    echo "Installing PHP dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generate application key if not set (check .env file directly)
CURRENT_KEY=$(grep "^APP_KEY=" /var/www/html/.env | cut -d'=' -f2)
if [ -z "$CURRENT_KEY" ] || [ "$CURRENT_KEY" = "" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
else
    echo "Application key already set, skipping generation..."
fi

# Install NPM dependencies if node_modules directory is empty
if [ ! -d "/var/www/html/node_modules" ] || [ -z "$(ls -A /var/www/html/node_modules 2>/dev/null)" ]; then
    echo "Installing NPM dependencies..."
    npm install
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Copy appropriate nginx configuration based on environment
if [ "$APP_ENV" = "production" ]; then
    echo "Using production nginx configuration..."
    cp /var/www/html/docker/nginx/production.conf /etc/nginx/http.d/default.conf
else
    echo "Using local nginx configuration..."
    cp /var/www/html/docker/nginx/local.conf /etc/nginx/http.d/default.conf
fi

# Build assets
echo "Building assets..."
npm run build

# Create storage symlink
php artisan storage:link 2>/dev/null || true

echo "Starting application..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
