# Customer Sync Testing Instructions

## Status Plugin

# Customer Sync Testing Instructions

## Status Plugin

Plugin Woo Odoo Integration telah diperbaiki dengan perubahan berikut:

1. **Simplified Hook Registration**: Hanya menggunakan `woocommerce_checkout_order_processed` hook
2. **Guest Customer Support**: Semua customer (registered dan guest) akan disync ke Odoo
3. **Clean Code**: Method yang tidak diperlukan sudah dihapus
4. **Comprehensive Logging**: Logging ditambahkan untuk troubleshooting

## Hook yang Digunakan

### Single Entry Point

- `woocommerce_checkout_order_processed` - Trigger untuk semua customer sync

### Customer Types

- **Registered Customer**: Customer dengan account WordPress akan disync menggunakan `sync_customer_to_odoo()`
- **Guest Customer**: Customer tanpa account akan disync menggunakan `sync_guest_customer_to_odoo()`

### Scheduled Tasks (Untuk Admin Manual Sync)

- `woo_odoo_integration_sync_customer` - Untuk scheduled sync registered customer

## API Functions

### Untuk Registered Customer

- `woo_odoo_integration_api_sync_customer()` - Sync registered customer

### Untuk Guest Customer

- `woo_odoo_integration_api_create_guest_customer()` - Sync guest customer data

## Cara Testing

### 1. Test Registered Customer

1. Buat account customer di WooCommerce
2. Buat order dengan customer tersebut
3. Cek log files untuk melihat sync process
4. Cek customer meta `_odoo_customer_uuid`

### 2. Test Guest Customer

1. Checkout sebagai guest (tanpa login)
2. Isi data billing lengkap
3. Complete order
4. Cek log files untuk guest customer sync
5. Cek order meta `_odoo_guest_uuid`

### 3. Cek Log Files

Log files tersimpan di: `wp-content/uploads/woo-odoo-integration-logs/`

- `woo-odoo-customer-sync-YYYY-MM-DD.log`

## Ekspektasi Hasil

Setelah checkout (baik registered maupun guest):

1. Log akan menunjukkan sync process
2. Registered customer: meta `_odoo_customer_uuid` akan terisi
3. Guest customer: order meta `_odoo_guest_uuid` akan terisi
4. Tidak ada lagi pesan "guest checkout, skipping"

## Debugging Tools

File debug tools masih tersedia:

1. **check-hooks.php** - Cek status hook registration
2. **manual-test.php** - Test manual customer sync
3. **simple-debug.php** - Web interface untuk testing

## File yang Diperbaiki

1. `admin/class-woo-odoo-integration-user.php` - Simplified methods, added guest support
2. `includes/class-woo-odoo-integration.php` - Simplified hook registration
3. `helper/api.php` - Added `woo_odoo_integration_api_create_guest_customer()` function

## Testing Expected Log Output

**Untuk Registered Customer:**

```
Info sync_customer_to_odoo_after_checkout called with order ID: XXX
Info Processing registered customer sync for customer ID XXX from order XXX
Info Starting sync_customer_to_odoo for customer ID: XXX
Info Successfully synced customer XXX to Odoo. UUID: xxx-xxx-xxx
```

**Untuk Guest Customer:**

```
Info sync_customer_to_odoo_after_checkout called with order ID: XXX
Info Processing guest customer sync for order XXX
Info Starting guest customer sync for order ID: XXX
Info Successfully synced guest customer for order XXX. UUID: xxx-xxx-xxx
```

Silakan test dengan membuat order baru (baik registered maupun guest) dan lihat apakah kedua jenis customer sudah tersync ke Odoo.

1. **Hook Registration**: Hook sudah ditambahkan untuk semua event customer lifecycle
2. **Duplicate Methods**: Method duplikat sudah dihapus
3. **Comprehensive Logging**: Logging ditambahkan di semua sync functions
4. **Multiple Fallbacks**: Beberapa hook untuk memastikan customer sync berjalan

## Hook yang Sudah Didaftarkan

### Customer Registration & Checkout

- `woocommerce_created_customer` - Ketika customer baru mendaftar
- `woocommerce_checkout_order_processed` - Setelah checkout diproses
- `woocommerce_new_order` - Ketika order baru dibuat

### Order Completion

- `woocommerce_order_status_completed` - Ketika order selesai
- `woocommerce_payment_complete` - Ketika pembayaran selesai
- `woocommerce_thankyou` - Di halaman thank you

### Customer Updates

- `profile_update` - Ketika profil customer diupdate
- `woocommerce_customer_save` - Ketika data customer WC disave

### Scheduled Tasks

- `woo_odoo_integration_sync_customer` - Untuk scheduled sync
- `woo_odoo_integration_update_customer` - Untuk scheduled update

## File Debug yang Tersedia

1. **check-hooks.php** - Cek status hook registration
2. **manual-test.php** - Test manual customer sync
3. **simple-debug.php** - Web interface untuk testing
4. **test-sync.php** - Simple test interface

## Cara Testing

### 1. Cek Hook Status

Akses: `your-site.com/wp-content/plugins/woo-odoo-integration/check-hooks.php`

### 2. Test Manual Sync

Edit file `manual-test.php`, ubah `$test_customer_id = 1;` dengan ID customer yang ingin ditest, lalu jalankan via command line atau browser.

### 3. Test via Web Interface

Akses: `your-site.com/wp-content/plugins/woo-odoo-integration/simple-debug.php`

### 4. Test Real Order

1. Buat order baru di WooCommerce
2. Cek log files di `wp-content/uploads/woo-odoo-integration-logs/`
3. Cek customer meta data untuk `_odoo_customer_uuid`

## Ekspektasi Hasil

Setelah order dibuat, seharusnya:

1. File log `woo-odoo-customer-sync-YYYY-MM-DD.log` terbuat
2. Customer mendapat meta `_odoo_customer_uuid` jika sync berhasil
3. Hook `woocommerce_thankyou` dan lainnya akan trigger sync

## Debugging Error

Jika masih ada masalah:

1. Cek plugin setting untuk Customer Sync enabled
2. Cek API URL dan API Key sudah benar
3. Cek log files untuk error messages
4. Jalankan manual test untuk specific customer

## File yang Sudah Diperbaiki

1. `admin/class-woo-odoo-integration-user.php` - Method duplikat dihapus
2. `includes/class-woo-odoo-integration.php` - Hook registration ditambah
3. File debug tools dibuat untuk troubleshooting

## Note Penting

Error lint yang muncul adalah normal - ini adalah fungsi WordPress/WooCommerce yang memang ada saat runtime tapi tidak dikenali oleh static analyzer. Plugin tetap akan berfungsi normal.

Silakan test dengan membuat order baru dan lihat apakah customer sync sudah berjalan dan log files terbuat.
