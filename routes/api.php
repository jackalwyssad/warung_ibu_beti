<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HutangDagangController;
use App\Http\Controllers\Api\V1\PenerimaanBarangController;
use App\Http\Controllers\Api\V1\PesananPembelianController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Health Check
    Route::get('/health', [HealthController::class, 'check']);

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);

    // Pesanan Pembelian (Purchase Orders)
    Route::get('/pesanan', [PesananPembelianController::class, 'index']);
    Route::post('/pesanan', [PesananPembelianController::class, 'store']);
    Route::get('/pesanan/{id}', [PesananPembelianController::class, 'show']);
    Route::post('/pesanan/{id}/konfirmasi', [PesananPembelianController::class, 'konfirmasi']);

    // Penerimaan Barang Fisik
    Route::get('/penerimaan', [PenerimaanBarangController::class, 'index']);
    Route::post('/pesanan/{id}/terima-barang', [PenerimaanBarangController::class, 'store']);

    // Hutang Dagang Supplier
    Route::get('/hutang', [HutangDagangController::class, 'index']);
    Route::post('/hutang/{id}/bayar', [HutangDagangController::class, 'bayar']);
});
