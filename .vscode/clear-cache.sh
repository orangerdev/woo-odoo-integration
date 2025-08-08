#!/bin/bash

# Script untuk mengclear Intelephense cache dan mereload VS Code

echo "🔄 Clearing Intelephense cache and reloading VS Code..."

# Clear Intelephense cache manually
echo "🗑️  Clearing Intelephense cache..."
rm -rf ~/Library/Caches/vscode-intelephense/*

echo "🔄 Reloading VS Code window..."
code --command workbench.action.reloadWindow

echo "✅ Done! WordPress and WooCommerce functions should now be recognized."
echo ""
echo "🔧 Next steps:"
echo "1. Wait for VS Code to reload"
echo "2. Open a PHP file and test typing 'add_action' - should show autocomplete"
echo "3. All WordPress/WooCommerce functions should now be recognized"
echo "4. Auto-formatting is now enabled on save"
