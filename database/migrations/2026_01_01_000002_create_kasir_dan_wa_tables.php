<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sistem Stok / Inventori (Shared Database)
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('nama_barang', 120);
            $table->string('kategori', 50)->default('sembako');
            $table->integer('stok')->default(0);
            $table->bigInteger('harga_beli')->default(0);
            $table->bigInteger('harga_jual')->default(0);
            $table->string('satuan', 20)->default('pcs');
            $table->timestamps();
        });

        // 2. Sistem Kasir (POS) - Mengakses tabel barang secara Shared Database
        Schema::create('transaksi_kasir', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transaksi', 40)->unique();
            $table->dateTime('tanggal_transaksi');
            $table->bigInteger('total_belanja');
            $table->enum('metode_bayar', ['tunai', 'qris'])->default('tunai');
            $table->enum('status_bayar', ['lunas', 'pending'])->default('lunas');
            $table->string('kasir_nama', 80)->default('Kasir Utama');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('item_transaksi_kasir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_kasir_id')->constrained('transaksi_kasir')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->string('sku', 40);
            $table->string('nama_barang', 120);
            $table->integer('qty');
            $table->bigInteger('harga_satuan');
            $table->bigInteger('subtotal');
            $table->timestamps();
        });

        // 3. Bot Pemesanan WhatsApp - Terhubung via Messaging (RabbitMQ)
        Schema::create('pesanan_wa', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pesanan_wa', 40)->unique();
            $table->string('nomor_telepon', 30);
            $table->string('nama_pelanggan', 100);
            $table->text('alamat_antar')->nullable();
            $table->enum('status', ['pending', 'diproses', 'siap_diambil', 'selesai', 'dibatalkan'])->default('pending');
            $table->bigInteger('total_nilai')->default(0);
            $table->enum('metode_bayar', ['tunai', 'qris'])->default('tunai');
            $table->timestamps();
        });

        Schema::create('item_pesanan_wa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_wa_id')->constrained('pesanan_wa')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->string('sku', 40);
            $table->string('nama_barang', 120);
            $table->integer('qty');
            $table->bigInteger('harga_satuan');
            $table->bigInteger('subtotal');
            $table->timestamps();
        });

        // 4. Sistem Pembayaran (QRIS & Tunai)
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_referensi', 50)->unique();
            $table->morphs('payable'); // transaksi_kasir atau pesanan_wa
            $table->enum('metode', ['tunai', 'qris']);
            $table->bigInteger('total_tagihan');
            $table->bigInteger('uang_diterima')->default(0);
            $table->bigInteger('kembalian')->default(0);
            $table->string('qr_string', 255)->nullable();
            $table->enum('status', ['sukses', 'pending', 'gagal'])->default('sukses');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('item_pesanan_wa');
        Schema::dropIfExists('pesanan_wa');
        Schema::dropIfExists('item_transaksi_kasir');
        Schema::dropIfExists('transaksi_kasir');
        Schema::dropIfExists('barang');
    }
};
