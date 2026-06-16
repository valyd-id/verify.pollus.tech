<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Decodes an image supplied either as a multipart file upload or as a
 * base64 / data-URL string into raw bytes.
 */
class ImageInput
{
    public static function bytes(Request $request, string $field): ?string
    {
        if ($request->hasFile($field)) {
            $file = $request->file($field);
            if ($file && $file->isValid()) {
                return file_get_contents($file->getRealPath());
            }
            return null;
        }

        $value = $request->input($field);
        if (!is_string($value) || $value === '') {
            return null;
        }

        return self::fromString($value);
    }

    public static function fromString(string $value): ?string
    {
        if (str_starts_with($value, 'data:image')) {
            $value = preg_replace('/^data:image\/[^;]+;base64,/', '', $value);
        }
        $bytes = base64_decode($value, true);
        return $bytes === false ? null : $bytes;
    }
}
