#!/bin/bash

# Script untuk menginstall semua extensions yang diperlukan untuk WordPress development
# Jalankan script ini untuk menginstall semua extension yang direkomendasikan

echo "🚀 Installing VS Code extensions for WordPress & WooCommerce development..."

# Core PHP Extensions
echo "📦 Installing PHP IntelliSense (Intelephense)..."
code --install-extension bmewburn.vscode-intelephense-client

echo "📦 Installing PHPStan..."
code --install-extension swordev.phpstan

echo "📦 Installing PHP CodeSniffer..."
code --install-extension wongjn.php-sniffer

echo "📦 Installing PHP CS Fixer..."
code --install-extension persoderlind.vscode-phpcbf

# WordPress Specific Extensions
echo "📦 Installing WordPress Toolbox..."
code --install-extension wordpresstoolbox.wordpress-toolbox

echo "📦 Installing WordPress Hooks IntelliSense..."
code --install-extension johnbillion.vscode-wordpress-hooks

# Additional Development Extensions
echo "📦 Installing Auto Rename Tag..."
code --install-extension formulahendry.auto-rename-tag

echo "📦 Installing Tailwind CSS IntelliSense..."
code --install-extension bradlc.vscode-tailwindcss

echo "📦 Installing TypeScript Support..."
code --install-extension ms-vscode.vscode-typescript-next

echo "📦 Installing JSON Language Support..."
code --install-extension ms-vscode.vscode-json

# Optional but useful extensions
echo "📦 Installing additional helpful extensions..."
code --install-extension ms-vscode.vscode-css-peek
code --install-extension esbenp.prettier-vscode
code --install-extension ms-vscode.vscode-html-css-support
code --install-extension streetsidesoftware.code-spell-checker

echo "✅ All extensions installed successfully!"
echo ""
echo "🔧 Next steps:"
echo "1. Restart VS Code"
echo "2. Open your WordPress project"
echo "3. Install PHP IntelliSense premium license (optional but recommended)"
echo "4. Configure your PHP path in VS Code settings if needed"
echo ""
echo "📚 For global configuration, check the README.md in .vscode folder"
