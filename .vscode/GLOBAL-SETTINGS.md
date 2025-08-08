# Global VS Code Settings untuk WordPress Development

Copy settings ini ke file global VS Code settings Anda:

## Lokasi File Settings Global:

### macOS:
```
~/Library/Application Support/Code/User/settings.json
```

### Linux:
```
~/.config/Code/User/settings.json
```

### Windows:
```
%APPDATA%\Code\User\settings.json
```

## Settings Content:

```json
{
    "php.suggest.basic": false,
    "php.validate.executablePath": "/usr/bin/php",
    
    "intelephense.files.maxSize": 5000000,
    "intelephense.files.associations": [
        "*.php",
        "*.phtml"
    ],
    "intelephense.files.exclude": [
        "**/vendor/**",
        "**/node_modules/**",
        "**/storage/**",
        "**/bootstrap/cache/**",
        "**/.git/**",
        "**/.svn/**",
        "**/.hg/**",
        "**/CVS/**",
        "**/.DS_Store/**"
    ],
    "intelephense.stubs": [
        "apache", "bcmath", "bz2", "calendar", "Core", "ctype",
        "curl", "date", "dba", "dom", "enchant", "exif", "FFI",
        "fileinfo", "filter", "fpm", "ftp", "gd", "gettext",
        "gmp", "hash", "iconv", "imap", "intl", "json", "ldap",
        "libxml", "mbstring", "meta", "mysqli", "oci8", "odbc",
        "openssl", "pcntl", "pcre", "PDO", "pgsql", "Phar",
        "posix", "pspell", "readline", "Reflection", "session",
        "shmop", "SimpleXML", "snmp", "soap", "sockets", "sodium",
        "SPL", "sqlite3", "standard", "superglobals", "sysvmsg",
        "sysvsem", "sysvshm", "tidy", "tokenizer", "xml",
        "xmlreader", "xmlrpc", "xmlwriter", "xsl", "Zend OPcache",
        "zip", "zlib", "wordpress"
    ],
    "intelephense.environment.includePaths": [
        "/Users/ridwanarifandi/Local Sites/woocommerce/app/public/wp-includes",
        "/Users/ridwanarifandi/Local Sites/woocommerce/app/public/wp-admin/includes"
    ],
    "intelephense.environment.phpVersion": "8.1.0",
    
    "emmet.includeLanguages": {
        "php": "html"
    },
    "files.associations": {
        "*.php": "php"
    },
    
    "editor.formatOnSave": false,
    "editor.codeActionsOnSave": {
        "source.fixAll.phpcbf": true
    },
    
    "[php]": {
        "editor.defaultFormatter": "bmewburn.vscode-intelephense-client",
        "editor.tabSize": 4,
        "editor.insertSpaces": false
    }
}
```

## Sesuaikan Path untuk Environment Anda:

Ganti path berikut sesuai dengan setup WordPress lokal Anda:

```json
"intelephense.environment.includePaths": [
    "/path/to/your/wordpress/wp-includes",
    "/path/to/your/wordpress/wp-admin/includes",
    "/path/to/your/wordpress/wp-content/plugins/woocommerce"
]
```

### Contoh untuk setup yang berbeda:

#### XAMPP (Windows):
```json
"intelephense.environment.includePaths": [
    "C:/xampp/htdocs/wordpress/wp-includes",
    "C:/xampp/htdocs/wordpress/wp-admin/includes"
]
```

#### MAMP (macOS):
```json
"intelephense.environment.includePaths": [
    "/Applications/MAMP/htdocs/wordpress/wp-includes",
    "/Applications/MAMP/htdocs/wordpress/wp-admin/includes"
]
```

#### Docker/Local by Flywheel:
```json
"intelephense.environment.includePaths": [
    "/Users/[username]/Local Sites/[site-name]/app/public/wp-includes",
    "/Users/[username]/Local Sites/[site-name]/app/public/wp-admin/includes"
]
```

#### VVV (Varying Vagrant Vagrants):
```json
"intelephense.environment.includePaths": [
    "/vagrant/www/wordpress-default/public_html/wp-includes",
    "/vagrant/www/wordpress-default/public_html/wp-admin/includes"
]
```
