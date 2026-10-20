<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Messaging\BrokerPengadaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $dbStatus = 'OK';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'DOWN: ' . $e->getMessage();
        }

        $rabbitStatus = 'OK';
        try {
            $koneksi = BrokerPengadaan::koneksi();
            $koneksi->close();
        } catch (\Throwable $e) {
            $rabbitStatus = 'DOWN: ' . $e->getMessage();
        }

        $isHealthy = ($dbStatus === 'OK' && $rabbitStatus === 'OK');

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'layanan' => 'Warung Ibu Beti - Pengadaan & Purchase Order',
            'versi' => '1.0.0',
            'checks' => [
                'database' => $dbStatus,
                'rabbitmq' => $rabbitStatus,
            ],
            'timestamp' => now()->toIso8601String(),
        ], $isHealthy ? 200 : 503);
    }
}
