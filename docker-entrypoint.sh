#!/bin/bash
set -e

echo "=== Stock App Production Startup ==="
echo "Environment: $APP_ENV"
echo ""

# Wait for database to be ready (database-agnostic PHP check)
echo "Waiting for database connection..."
php -r '
$conn = getenv("DB_CONNECTION") ?: "pgsql";
if ($conn === "sqlite") {
    echo "Using SQLite database.\n";
    exit(0);
}
$host = getenv("DB_HOST") ?: "127.0.0.1";
$port = getenv("DB_PORT") ?: ($conn === "pgsql" ? "5432" : "3306");
$db = getenv("DB_DATABASE");
$user = getenv("DB_USERNAME");
$pass = getenv("DB_PASSWORD");

$dsn = "$conn:host=$host;port=$port;dbname=$db";
if ($conn === "mysql" || $conn === "mariadb") {
    $dsn = "mysql:host=$host;port=$port;dbname=$db";
}

$start = time();
while (time() - $start < 60) {
    try {
        new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "✓ Database connection established successfully.\n";
        exit(0);
    } catch (PDOException $e) {
        echo "Database connection failed. Retrying in 1s...\n";
        sleep(1);
    }
}
echo "Error: Database connection timed out.\n";
exit(1);
'

# Run migrations
echo ""
echo "Running database migrations..."
php artisan migrate --force --quiet
echo "✓ Migrations completed"

# Clear and cache configuration
echo ""
echo "Caching configuration..."
php artisan config:cache
echo "✓ Configuration cached"

# Cache routes
echo "Caching routes..."
php artisan route:cache
echo "✓ Routes cached"

# Cache views
echo "Caching views..."
php artisan view:cache
echo "✓ Views cached"

# Configure Nginx port dynamically based on $PORT (defaults to 80)
PORT_TO_USE="${PORT:-80}"
echo "Configuring Nginx to listen on port $PORT_TO_USE..."
sed -i "s/listen [0-9]\+;/listen ${PORT_TO_USE};/g" /etc/nginx/nginx.conf

echo ""
echo "=== Startup Complete ==="
echo "Starting Supervisord (Nginx + PHP-FPM)..."
echo ""

exec /usr/bin/supervisord -c /etc/supervisord.conf
