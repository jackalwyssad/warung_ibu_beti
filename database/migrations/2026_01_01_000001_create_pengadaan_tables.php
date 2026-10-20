<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('kode_supplier', 30)->unique();
            $table->string('nama', 100);
            $table->string('kontak', 50);
            $table->text('alamat')->nullable();
            $table->bigInteger('total_hutang')->default(0);
            $table->bigInteger('limit_kredit')->default(10000000);
            $table->timestamps();
        });

        Schema::create('pesanan_pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_po', 40)->unique();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->enum('status', ['draft', 'dikonfirmasi', 'diproses', 'selesai', 'dibatalkan'])->default('draft');
            $table->bigInteger('total_nilai')->default(0);
            $table->enum('metode_bayar', ['tempo', 'tunai'])->default('tempo');
            $table->text('keterangan')->nullable();
            $table->date('tanggal_pesan');
            $table->date('tanggal_estimasi')->nullable();
            $table->timestamps();
        });

        Schema::create('item_pesanan_pembelian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_pembelian_id')->constrained('pesanan_pembelian')->cascadeOnDelete();
            $table->string('sku', 40);
            $table->string('nama_barang', 120);
            $table->integer('jumlah_pesan');
            $table->integer('jumlah_diterima')->default(0);
            $table->bigInteger('harga_satuan');
            $table->bigInteger('subtotal');
            $table->timestamps();
        });

        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_penerimaan', 40)->unique();
            $table->foreignId('pesanan_pembelian_id')->constrained('pesanan_pembelian')->cascadeOnDelete();
            $table->dateTime('tanggal_terima');
            $table->string('penerima', 100);
            $table->text('catatan')->nullable();
            $table->enum('status', ['parsial', 'lengkap'])->default('lengkap');
            $table->timestamps();
        });

        Schema::create('hutang_dagang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->foreignId('pesanan_pembelian_id')->nullable()->constrained('pesanan_pembelian')->nullOnDelete();
            $table->string('nomor_faktur', 50)->unique();
            $table->bigInteger('jumlah_hutang');
            $table->bigInteger('jumlah_dibayar')->default(0);
            $table->bigInteger('sisa_hutang');
            $table->date('jatuh_tempo');
            $table->enum('status', ['belum_lunas', 'lunas'])->default('belum_lunas');
            $table->timestamps();
        });

        Schema::create('outbox_kirim', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100);
            $table->string('routing_key', 100);
            $table->json('payload');
            $table->enum('status', ['pending', 'terkirim', 'gagal'])->default('pending');
            $table->integer('retry_count')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_kirim');
        Schema::dropIfExists('hutang_dagang');
        Schema::dropIfExists('penerimaan_barang');
        Schema::dropIfExists('item_pesanan_pembelian');
        Schema::dropIfExists('pesanan_pembelian');
        Schema::dropIfExists('supplier');
    }
};
