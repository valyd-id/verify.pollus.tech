<?php

namespace App\Services\FaceOnLive;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the FaceOnLive OCR microservice (ID document extraction +
 * authenticity). Ported from idp.pollus.tech.
 */
class IdOcrService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.faceonlive.ocr_url', 'https://faceonlive-ocr.pollus.us');
    }

    public function getIdOcrInfo(?string $frontFile, ?string $backFile = null, int $timeout = 30): array
    {
        if (!$frontFile && !$backFile) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'No image files provided',
                ],
                'data' => [
                    'error_type' => 'VALIDATION_ERROR',
                    'context' => [
                        'front_file' => (bool) $frontFile,
                        'back_file' => (bool) $backFile,
                    ],
                ],
            ];
        }

        try {
            // Always use the multipart /ocr/idcard endpoint. It accepts a single
            // image (image1) or front+back (image1+image2). The base64 single-image
            // endpoint (/ocr/idcard_base64) is currently broken (HTTP 500), so we
            // never use it — a front-only capture goes through here too.
            $url = rtrim($this->baseUrl, '/') . '/ocr/idcard';
            $primary = $frontFile ?: $backFile;

            $request = Http::timeout($timeout)
                ->attach('image1', file_get_contents($primary), basename($primary));
            if ($frontFile && $backFile) {
                $request = $request->attach('image2', file_get_contents($backFile), basename($backFile));
            }
            $response = $request->post($url);

            $response->throw();
            $j = $response->json();

            $status = $j['status'] ?? 'error';
            $ocrResDict = $j['data'] ?? [];
            $authenticity = $j['authenticity'] ?? null;

            if ($status !== 'ok' && $status !== 'error') {
                $status = 'error';
            }

            if ($status === 'ok') {
                return [
                    'success' => true,
                    'data' => [
                        'service' => 'OCR',
                        'result' => [
                            'status' => $status,
                            'ocr_data' => $ocrResDict ?: [],
                            'authenticity' => $authenticity,
                        ],
                        'context' => [
                            'endpoint' => $url,
                            'files_provided' => [
                                'front' => (bool) $frontFile,
                                'back' => (bool) $backFile,
                            ],
                        ],
                    ],
                ];
            }

            return [
                'success' => false,
                'error' => [
                    'code' => 'processing_error',
                    'message' => "OCR processing failed: {$status}",
                ],
                'data' => [
                    'error_type' => 'PROCESSING_ERROR',
                    'context' => [
                        'ocr_status' => $status,
                        'ocr_data' => $ocrResDict,
                        'endpoint' => $url,
                    ],
                ],
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('ID OCR connection failed: ' . $e->getMessage());
            return $this->networkError($url ?? 'unknown', $e->getMessage());
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('ID OCR request failed: ' . $e->getMessage());
            return $this->networkError($url ?? 'unknown', $e->getMessage(), $e->response?->status());
        } catch (\Exception $e) {
            Log::error('ID OCR failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'ocr_error',
                    'message' => 'OCR processing failed',
                ],
                'data' => [
                    'error_type' => 'PROCESSING_ERROR',
                    'context' => [
                        'endpoint' => $url ?? 'unknown',
                        'error' => $e->getMessage(),
                    ],
                ],
            ];
        }
    }

    private function networkError(string $endpoint, string $message, ?int $statusCode = null): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 'network_error',
                'message' => 'OCR service connection failed',
            ],
            'data' => [
                'error_type' => 'NETWORK_ERROR',
                'context' => array_filter([
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'error' => $message,
                ]),
            ],
        ];
    }
}
