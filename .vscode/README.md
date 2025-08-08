# VS Code Configuration untuk WordPress & WooCommerce Development

Konfigurasi ini dirancang untuk memberikan pengalaman development terbaik saat mengembangkan plugin WordPress dan WooCommerce.

## 🚀 Quick Setup

1. **Install Extensions:**

   ```bash
   ./.vscode/install-extensions.sh
   ```

2. **Restart VS Code** setelah instalasi extension selesai

3. **Configure PHP Path** (jika diperlukan):
   - Buka VS Code Settings (Cmd+,)
   - Cari "php.validate.executablePath"
   - Set ke path PHP Anda, contoh: `/usr/bin/php`

## 📦 Extensions yang Disertakan

### Core PHP Extensions

- **PHP Intelephense** - IntelliSense terbaik untuk PHP
- **PHPStan** - Static analysis untuk deteksi error
- **PHP CodeSniffer** - Code quality checker dengan WordPress standards
- **PHP CS Fixer** - Auto-formatter sesuai WordPress coding standards

### WordPress Specific

- **WordPress Toolbox** - WordPress function library
- **WordPress Hooks IntelliSense** - Autocomplete untuk WordPress hooks

### Additional Tools

- **Auto Rename Tag** - Rename HTML/XML tags otomatis
- **Tailwind CSS IntelliSense** - CSS framework support
- **TypeScript Support** - JavaScript/TypeScript IntelliSense

## ⚙️ Konfigurasi Global VS Code

Untuk mengaplikasikan konfigurasi ini ke semua project WordPress, buat file di:

### macOS/Linux:

```
~/.vscode/settings.json
```

### Windows:

```
%APPDATA%/Code/User/settings.json
```

### Global Settings Content:

```json
{
  "php.suggest.basic": false,
  "intelephense.stubs": [
    "apache",
    "bcmath",
    "bz2",
    "calendar",
    "Core",
    "ctype",
    "curl",
    "date",
    "dba",
    "dom",
    "enchant",
    "exif",
    "FFI",
    "fileinfo",
    "filter",
    "fpm",
    "ftp",
    "gd",
    "gettext",
    "gmp",
    "hash",
    "iconv",
    "imap",
    "intl",
    "json",
    "ldap",
    "libxml",
    "mbstring",
    "meta",
    "mysqli",
    "oci8",
    "odbc",
    "openssl",
    "pcntl",
    "pcre",
    "PDO",
    "pgsql",
    "Phar",
    "posix",
    "pspell",
    "readline",
    "Reflection",
    "session",
    "shmop",
    "SimpleXML",
    "snmp",
    "soap",
    "sockets",
    "sodium",
    "SPL",
    "sqlite3",
    "standard",
    "superglobals",
    "sysvmsg",
    "sysvsem",
    "sysvshm",
    "tidy",
    "tokenizer",
    "xml",
    "xmlreader",
    "xmlrpc",
    "xmlwriter",
    "xsl",
    "Zend OPcache",
    "zip",
    "zlib",
    "wordpress"
  ],
  "intelephense.environment.includePaths": [
    "/path/to/your/wordpress/wp-includes",
    "/path/to/your/wordpress/wp-admin/includes"
  ],
  "intelephense.files.maxSize": 5000000,
  "emmet.includeLanguages": {
    "php": "html"
  },
  "files.associations": {
    "*.php": "php"
  }
}
```

## 🛠️ WordPress Function Recognition

Untuk memastikan WordPress functions dikenali:

1. **Update Include Paths** di settings:

   ```json
   "intelephense.environment.includePaths": [
       "/Users/ridwanarifandi/Local Sites/woocommerce/app/public/wp-includes",
       "/Users/ridwanarifandi/Local Sites/woocommerce/app/public/wp-admin/includes",
       "/Users/ridwanarifandi/Local Sites/woocommerce/app/public/wp-content/plugins/woocommerce"
   ]
   ```

2. **Enable WordPress Stubs** - sudah dikonfigurasi di settings.json

3. **Install Composer Dependencies** untuk project-specific function recognition:
   ```bash
   composer require --dev php-stubs/wordpress-stubs
   composer require --dev php-stubs/woocommerce-stubs
   ```

## 🔍 Features yang Tersedia

### IntelliSense & Autocomplete

- ✅ WordPress core functions (add_action, get_option, dll)
- ✅ WooCommerce functions (wc_get_product, dll)
- ✅ PHP built-in functions
- ✅ Class methods dan properties

### Code Quality

- ✅ WordPress Coding Standards checking
- ✅ Auto-formatting dengan PHPCBF
- ✅ Static analysis dengan PHPStan
- ✅ Error detection dan suggestions

### Debugging

- ✅ Xdebug support dengan pre-configured launch settings
- ✅ Breakpoint debugging
- ✅ Variable inspection

### Tasks

- ✅ `Ctrl+Shift+P` → "Tasks: Run Task"
  - PHP CodeSniffer Check
  - PHP Code Beautifier Fix
  - PHPStan Analysis

## 🐛 Troubleshooting

### "Call to unknown function 'add_action'" masih muncul?

1. **Clear Intelephense Cache:**

   ```bash
   ./.vscode/clear-cache.sh
   ```

2. **Restart VS Code:**
   `Cmd+Shift+P` → "Developer: Reload Window"

3. **Check Include Paths:**
   Pastikan WordPress stubs sudah terinstall:

   ```bash
   composer install
   ```

4. **Force Reload Intelephense:**
   `Cmd+Shift+P` → "Intelephense: Clear Cache and Reload"

### Linter warnings seperti "Opening parenthesis of a multi-line function call must be the last content on the line"?

Sudah di-disable! Konfigurasi auto-formatting akan menangani ini:

- **Auto-format on save**: ✅ Enabled
- **WordPress Coding Standards**: ✅ Configured
- **Annoying linter warnings**: ❌ Disabled

### Extensions tidak terpasang?

Jalankan script instalasi manual:

```bash
./.vscode/install-extensions.sh
```

### Performance lambat?

Sudah dioptimalkan dengan:

- Include paths yang tepat
- WordPress/WooCommerce stubs dari Composer
- Diagnostic warnings yang tidak perlu di-disable

## 📝 Notes

- Konfigurasi ini sudah dioptimalkan untuk WordPress/WooCommerce development
- Include paths sudah disesuaikan dengan struktur Local by Flywheel
- WordPress coding standards sudah aktif
- Untuk project lain, sesuaikan include paths di settings

## 🔗 Useful Links

- [PHP Intelephense Documentation](https://intelephense.com/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WooCommerce Development Guide](https://woocommerce.github.io/code-reference/)

---

> **💡 Tip:** Setelah setup, buka file PHP WordPress dan coba ketik `add_action` - Anda akan melihat autocomplete dengan dokumentasi lengkap!
