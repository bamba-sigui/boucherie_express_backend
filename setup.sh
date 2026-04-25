#!/bin/bash
# Boucherie Express - Setup Script

echo "=== Boucherie Express Setup ==="

# Install PHP if not installed
if ! command -v php &> /dev/null; then
    echo "PHP not found. Installing with Homebrew..."
    brew install php
fi

# Install Composer if not installed
if ! command -v composer &> /dev/null; then
    echo "Composer not found. Installing..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Navigate to project directory
cd "$(dirname "$0")"

echo "Installing dependencies..."
composer install

echo "Copying .env file..."
if [ ! -f .env ]; then
    cp .env.example .env
fi

echo "Generating application key..."
php artisan key:generate

echo "Running migrations..."
php artisan migrate

echo "Seeding database..."
php artisan db:seed

echo "=== Setup Complete! ==="
echo ""
echo "Run: php artisan serve"
echo "Admin: http://localhost:8000/admin"
echo "API: http://localhost:8000/api/v1"