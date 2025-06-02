<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    public function __construct()
    {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => env('R2_ENDPOINT'), 
            'credentials' => [
                'key'    => env('R2_ACCESS_KEY_ID'),
                'secret' => env('R2_SECRET_ACCESS_KEY'),
            ],
            'bucket_endpoint' => false, 
            'use_path_style_endpoint' => true,
            'http' => [
                'verify' => false, 
            ],
        ]);
    }

    public function uploadFile($file, $path)
    {
        $key = $path . uniqid() . '.jpg';

        try {
            $result=$this->client->putObject([
                'Bucket' => env('R2_BUCKET'),
                'Key' => $key,
                'Body' => fopen($file->getRealPath(), 'rb'),
                'ContentType' => $file->getMimeType(),    
            ]);

        return $key;
        } catch (AwsException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteFile(string $key)
    {
        try {
            $this->client->deleteObject([
                'Bucket' => env('R2_BUCKET'),
                'Key'    => $key,
            ]);

            return true;
        } catch (\Exception $e) {
            throw new HttpException(500, 'Failed to delete file: ' . $e->getMessage());
        }
    }

    public function listFiles(string $path): array
    {
        try {
            $result = $this->client->listObjectsV2([
                'Bucket' => env('R2_BUCKET'),
                'Prefix' => $path,
            ]);

            $files = [];

            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = rtrim(env('R2_URL'), '/') . '/' . $object['Key'];
                }
            }

            return $files;
        } catch (\Exception $e) {
            throw new HttpException(500, 'Failed to list files: ' . $e->getMessage());
        }
    }
}