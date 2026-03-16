#!/bin/bash
# Cepat Shop cPanel Permissions Script
# Run this script from your application's root directory via cPanel Terminal or SSH.

echo "Setting standard permissions: 644 for files, 755 for directories..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

echo "Setting writable permissions (775) for storage and bootstrap/cache..."
chmod -R 775 storage bootstrap/cache

echo "Permissions have been successfully updated for cPanel hosting environment!"
