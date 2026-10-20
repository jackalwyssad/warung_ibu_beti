<?php

use App\Http\Controllers\Api\V1\BarangController;
use App\Http\Controllers\Api\V1\BotWhatsAppController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HutangDagangController;
use App\Http\Controllers\Api\V1\KasirController;
use App\Http\Controllers\Api\V1\PenerimaanBarangController;
use App\Http\Controllers\Api\V1\PesananPembelianController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 0. Health Check
    Route::get('/health', [HealthController::class, 'check']);

    // ==========================================
    // SISTEM UTAMA TUGAS 3
    // ==========================================

    // 1. Sistem Stok / Inventori Sembako
    Route::get('/barang', [BarangController::class, 'index']);
    Route::post('/barang', [BarangController::class, 'store']);
    Route::get('/barang/{sku}', [BarangController::class, 'show']);

    // 2. Sistem Kasir (POS) - Gaya Integrasi: Shared Database (Real-time ACID)
    Route::get('/kasir/transaksi', [KasirController::class, 'index']);
    Route::post('/kasir/transaksi', [KasirController::class, 'store']);

    // 3. Bot Pemesanan WhatsApp - Gaya Integrasi: Messaging (RabbitMQ Asinkron)
    Route::post('/wa/pesanan', [BotWhatsAppController::class, 'store']);
    Route::get('/wa/pesanan/{nomor}', [BotWhatsAppController::class, 'show']);

    // ==========================================
    // SISTEM PENGADAAN & SUPPLIER (KULAKAN RESTOCK)
    // ==========================================
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);

    Route::get('/pesanan', [PesananPembelianController::class, 'index']);
    Route::post('/pesanan', [PesananPembelianController::class, 'store']);
    Route::get('/pesanan/{id}', [PesananPembelianController::class, 'show']);
    Route::post('/pesanan/{id}/konfirmasi', [PesananPembelianController::class, 'konfirmasi']);

    Route::get('/penerimaan', [PenerimaanBarangController::class, 'index']);
    Route::post('/pesanan/{id}/terima-barang', [PenerimaanBarangController::class, 'store']);

    Route::get('/hutang', [HutangDagangController::class, 'index']);
    Route::post('/hutang/{id}/bayar', [HutangDagangController::class, 'bayar']);
});
