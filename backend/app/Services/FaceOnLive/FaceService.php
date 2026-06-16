<?php

namespace App\Services\FaceOnLive;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the FaceOnLive face microservice.
 * Ported from idp.pollus.tech so verify.valyd.net can run liveness, feature
 * extraction and 1:1 similarity without depending on a Valyd user account.
 */
class FaceService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.faceonlive.url', 'https://faceonlive-face.pollus.us');
    }

    public function getLivenessInfo($image): array
    {
        $url = rtrim($this->baseUrl, '/') . '/face/liveness';

        try {
            $response = Http::timeout(60)
                ->attach('image', $this->imageToBytes($image), 'img.png')
                ->post($url);

            $response->throw();

            $data = $response->json()['data'];
            $box = $data['box'];

            $bbox = [
                $box['x'],
                $box['y'],
                $box['x'] + $box['w'] - 1,
                $box['y'] + $box['h'] - 1,
            ];

            return [
                'success' => true,
                'bbox' => $bbox,
                'live_score' => (int) $data['score'],
                'result' => $data['result'],
            ];
        } catch (\Exception $e) {
            $errorData = [
                'success' => false,
                'error' => [
                    'code' => 'liveness_check_failed',
                    'message' => $e->getMessage(),
                ],
                'data' => [
                    'error_type' => 'processing_error',
                    'context' => [
                        'endpoint' => $url,
                        'exception' => get_class($e),
                    ],
                ],
            ];

            if (method_exists($e, 'response') && $e->response) {
                try {
                    $errorResponse = $e->response->json();
                    if (isset($errorResponse['error'])) {
                        $errorData['error'] = $errorResponse['error'];
                    }
                    if (isset($errorResponse['data'])) {
                        $errorData['data'] = array_merge($errorData['data'], $errorResponse['data']);
                    }
                } catch (\Exception $parseError) {
                    // Ignore parse errors
                }
            }

            return $errorData;
        }
    }

    public function getFeatureInfo($image): array
    {
        $url = rtrim($this->baseUrl, '/') . '/face/attribute';

        $response = Http::timeout(60)
            ->attach('image', $this->imageToBytes($image), 'img.png')
            ->post($url);

        $response->throw();

        $d = $response->json()['data'];

        $bbox = [
            $d['box']['x'],
            $d['box']['y'],
            $d['box']['x'] + $d['box']['w'] - 1,
            $d['box']['y'] + $d['box']['h'] - 1,
        ];

        $featSize = (int) ($d['feature_size'] ?? 0);
        $featB64 = $d['feature_b64'] ?? '';

        $feature = [];
        if ($featB64 && $featSize > 0) {
            $raw = base64_decode($featB64);
            $feature = array_values(unpack('C*', substr($raw, 0, min($featSize, strlen($raw)))));
        }

        return [
            'bbox' => $bbox,
            'liveness' => (int) ($d['liveness'] ?? 0),
            'feature' => $feature,
            'feature_size' => count($feature),
        ];
    }

    public function getFaceSimilarity(array $feat1, array $feat2, int $featureSize = 2056): float
    {
        $url = rtrim($this->baseUrl, '/') . '/face/compare-new';

        $response = Http::timeout(60)
            ->post($url, [
                'feat1' => array_map('floatval', $feat1),
                'feat2' => array_map('floatval', $feat2),
                'FEATURE_SIZE' => $featureSize,
            ]);

        $response->throw();

        return (float) $response->json()['data']['similarity'];
    }

    private function imageToBytes($image): string
    {
        if (is_resource($image)) {
            return stream_get_contents($image);
        }

        if (is_string($image)) {
            if (strpos($image, "\0") !== false) {
                return $image;
            }

            if (strlen($image) < 4096 && file_exists($image) && is_file($image) && is_readable($image)) {
                $sanitizedPath = str_replace("\0", '', $image);
                if ($sanitizedPath === $image && file_exists($sanitizedPath)) {
                    return file_get_contents($sanitizedPath);
                }
            }

            return $image;
        }

        return (string) $image;
    }
}
