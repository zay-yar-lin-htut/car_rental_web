<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    public function uploadToCloudflareR2(UploadedFile $file, string $folderPath)
    {
        if (!$file->isValid()) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(400, 'Invalid file upload');
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $fullPath = trim($folderPath, '/') . '/' . $filename;

        $uploaded = Storage::disk('r2')->put($fullPath, file_get_contents($file));

        if (!$uploaded) {
            throw new \Exception('File upload to R2 failed.');
        }

        return Storage::disk('r2')->url($fullPath);
    }
}