# Automatic Product Sync - Quick Setup Guide

## Deskripsi

Fitur Automatic Product Sync memungkinkan sinkronisasi otomatis stock produk dari Odoo ERP ke WooCommerce setiap hari di tengah malam, dengan sistem chunking untuk menangani banyak produk secara aman.

## Cara Kerja

1. **Schedule**: Berjalan otomatis setiap tengah malam sesuai timezone WordPress
2. **Chunking**: Membagi produk dalam batch kecil (default: 10 produk per 5 menit)
3. **Safe Processing**: Mencegah timeout dan overload server
4. **Progress Tracking**: Monitor real-time melalui admin interface

## Setup & Configuration

### 1. Akses Admin Interface

```
WordPress Admin → WooCommerce → Odoo Auto Sync
```

### 2. Konfigurasi Basic

- **Chunk Size**: 10 produk (recommended untuk server normal)
- **Chunk Interval**: 5 menit (recommended untuk menghindari server overload)
- Simpan pengaturan

### 3. Test Manual Sync

1. Klik "Start Sync Now" untuk test
2. Monitor progress di status display
3. Pastikan tidak ada error di logs

## Monitoring & Control

### Status Display

- **In Progress**: Menampilkan progress bar dan statistik real-time
- **Completed**: Summary hasil sync terakhir
- **Idle**: Tidak ada sync yang berjalan

### Manual Controls

- **Start Sync Now**: Trigger sync manual (tidak mengganggu schedule)
- **Cancel Current Sync**: Stop sync yang sedang berjalan
- **Refresh Status**: Update tampilan status

### Real-time Updates

Interface akan auto-refresh setiap 30 detik saat sync berjalan.

## Troubleshooting

### Sync Tidak Berjalan Otomatis

**Check WordPress Cron:**

```php
// Add to functions.php temporarily
var_dump(wp_next_scheduled('woo_odoo_auto_sync_product_stock'));
```

**Jika cron disabled:**

```php
// Check di wp-config.php
// Jika ada: define('DISABLE_WP_CRON', true);
// Maka setup system cron job manual
```

### Performance Issues

**Reduce Load:**

- Chunk Size: 5-10 produk
- Interval: 10-15 menit

**Check Server Resources:**

- Monitor CPU/Memory usage selama sync
- Check PHP max_execution_time
- Verify database performance

### No Products Updated

**Verify SKU Mapping:**

1. Pastikan produk WooCommerce punya SKU
2. Check logs untuk UUID mapping
3. Test API endpoint manual

**Debug Mode:**

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs: /wp-content/debug.log
// atau WooCommerce > Status > Logs > woo-odoo-scheduler
```

### Sync Stuck/Corrupted

**Manual Reset:**

1. Admin interface: "Cancel Current Sync"
2. Atau via database:
   ```php
   delete_option('woo_odoo_auto_sync_meta');
   wp_clear_scheduled_hook('woo_odoo_auto_sync_product_stock');
   ```

## Best Practices

### Server Configuration

- **PHP Memory**: Minimum 256MB, recommended 512MB
- **Max Execution Time**: Minimum 300 seconds
- **WordPress Cron**: Enabled (jangan DISABLE_WP_CRON)

### Product Management

- Pastikan semua produk punya SKU yang valid
- Gunakan SKU yang consistent dengan Odoo UUID
- Regular cleanup produk tidak aktif

### Monitoring Schedule

- Check sync status setiap pagi
- Monitor error logs weekly
- Verify API connectivity monthly

### Optimization Tips

1. **Small Chunks**: Untuk server shared hosting, gunakan chunk size 5-10
2. **Long Intervals**: Untuk banyak produk, gunakan interval 10-15 menit
3. **Off-Peak Timing**: Sync berjalan tengah malam untuk menghindari traffic tinggi
4. **Regular Maintenance**: Clear transients dan optimize database secara berkala

## Advanced Configuration

### Custom Hooks

```php
// Customize chunk settings
add_filter('woo_odoo_integration_auto_sync_chunk_size', function() { return 15; });
add_filter('woo_odoo_integration_auto_sync_chunk_interval', function() { return 3; });

// Monitor sync events
add_action('woo_odoo_auto_sync_completed', function($sync_meta) {
    // Send email notification
    // Log to external monitoring
    // Custom post-sync actions
});
```

### External Monitoring

```php
// Check sync status via code
$scheduler = new Woo_Odoo_Integration_Scheduler('plugin-name', '1.0.0');
$status = $scheduler->get_sync_status();

if ($status && $status['status'] === 'in_progress') {
    // Sync running
} elseif ($status && $status['status'] === 'completed') {
    // Last sync completed
    $success_rate = $status['successful_updates'] / $status['total_products'];
}
```

## FAQ

### Q: Berapa lama sync process akan berjalan?

A: Tergantung jumlah produk dan konfigurasi. Contoh:

- 100 produk, chunk 10, interval 5 menit = ~50 menit
- 500 produk, chunk 10, interval 5 menit = ~4 jam

### Q: Apakah aman menjalankan sync saat website traffic tinggi?

A: Ya, dengan chunking system dan interval yang tepat, impact minimal. Sync berjalan di background menggunakan WordPress cron.

### Q: Bagaimana jika koneksi API terputus di tengah sync?

A: System akan log error untuk chunk yang gagal, tapi lanjut process chunk berikutnya. Produk yang gagal bisa di-sync manual atau tunggu sync berikutnya.

### Q: Bisa mengubah waktu sync dari tengah malam?

A: Saat ini fixed ke tengah malam. Untuk custom schedule, bisa modify code di `schedule_daily_sync()` method.

### Q: Apakah akan conflict dengan manual sync?

A: Tidak, keduanya menggunakan fungsi sync yang sama. Tapi sebaiknya tidak run bersamaan untuk performa optimal.

## Support

Untuk bantuan lebih lanjut:

1. Check dokumentasi lengkap di `docs/Automatic-Product-Sync-Feature.md`
2. Enable debug mode dan check logs
3. Test dengan chunk size kecil dulu (5 produk)
4. Verify API connectivity manual

## Updates & Maintenance

### Regular Checks

- Weekly: Review error logs
- Monthly: Check sync performance metrics
- Quarterly: Update chunk settings berdasarkan catalog growth

### Plugin Updates

- Backup sync settings sebelum update plugin
- Test automatic sync setelah update
- Verify cron schedule masih aktif

---

**Note**: Fitur ini memerlukan API Odoo yang aktif dan konfigurasi authentication yang benar. Pastikan credential API sudah ter-setup dengan baik sebelum mengaktifkan automatic sync.
