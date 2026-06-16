<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;

/**
 * Catalog of verification services the platform offers. Each has a stable
 * unique id (the feature key) used when composing workflows.
 */
class ServiceController extends Controller
{
    public const CATALOG = [
        ['id' => 'id_verification', 'name' => 'KYC / ID Verification', 'description' => 'Capture a government ID, run OCR + authenticity checks.', 'icon' => 'id'],
        ['id' => 'liveness', 'name' => 'Liveness', 'description' => 'Passive liveness — confirm a real, present person.', 'icon' => 'eye'],
        ['id' => 'face_match', 'name' => 'Face Match', 'description' => '1:1 match of a selfie against the ID portrait.', 'icon' => 'face'],
        ['id' => 'age', 'name' => 'Age Verification', 'description' => 'Zero-knowledge age-band proof (18+, 21+, …).', 'icon' => 'cake'],
        ['id' => 'credential', 'name' => 'License Verification', 'description' => 'Verify a professional license against official state registries — no ID required.', 'icon' => 'badge'],
    ];

    public function index()
    {
        return GlobalHelper::apiSuccess(['services' => self::CATALOG]);
    }
}
