<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BakongService
{
    public function generateKHQR(array $data): array
    {
        $baseUrl = config('services.bakong.node_url');

        $response = Http::timeout(15)
            ->acceptJson()
            ->post(
                rtrim($baseUrl, '/') . '/api/khqr/generate',
                [
                    'orderId'     => $data['orderId'],
                    'orderNumber' => $data['orderNumber'],
                    'amount'      => $data['amount'],
                    'currency'    => $data['currency'],
                ]
            );

        if (!$response->successful()) {
            throw new \RuntimeException(
                $response->json('message')
                    ?? 'Failed to generate KHQR'
            );
        }

        $result = $response->json();

        if (
            !isset($result['success']) ||
            $result['success'] !== true ||
            !isset($result['data'])
        ) {
            throw new \RuntimeException(
                $result['message']
                    ?? 'Invalid KHQR service response'
            );
        }

        return $result['data'];
    }
}
