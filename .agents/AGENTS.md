# Aturan Proyek Khusus (Project-Scoped Rules)

Aturan ini dibuat berdasarkan preferensi user selama proses pengembangan:

1. **Database / Migration**: Jika ada permintaan penambahan kolom atau pembuatan tabel baru, JANGAN gunakan Laravel Migrations (`php artisan make:migration`). Alih-alih, berikan query manual SQL secara langsung kepada user agar user bisa mengeksekusinya sendiri (misalnya di phpMyAdmin atau DBMS lainnya). Alasannya terkait dengan kendala lingkungan versi PHP di server CLI user.
