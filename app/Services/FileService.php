<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Helpers\Helper;
use Illuminate\Http\Request;

class FileService
{
    private S3Client $client;
    protected $helper;


    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
        $key = env('R2_ACCESS_KEY_ID');
        $secret = env('R2_SECRET_ACCESS_KEY');
        
        if (empty($key) || empty($secret)) {
            throw new \InvalidArgumentException('R2 credentials missing from .env: Check R2_ACCESS_KEY_ID and R2_SECRET_ACCESS_KEY');
        }
        
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => env('R2_ENDPOINT'), 
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'use_path_style_endpoint' => true,
            'http' => [
                'verify' => env('APP_ENV') === 'local' ? false : true, 
            ],
        ]);
    }

    public function uploadFile($file, $path)
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';  // Fallback to 'jpg' if no extension
        $key = $path . uniqid() . '.' . $extension;

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            return response()->json(['error' => 'Failed to open file stream'], 500);
        }

        try {
            $result = $this->client->putObject([
                'Bucket' => env('R2_BUCKET'),
                'Key' => $key,
                'Body' => $stream,
                'ContentType' => $file->getMimeType(),    
            ]);

            fclose($stream);  // Close on success
            return $key;
        } catch (AwsException $e) {
            fclose($stream);  // Close on error
            return null;
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
            return null;
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
                    $baseUrl = rtrim(env('R2_URL', ''), '/');
                    $files[] = $baseUrl . '/' . $object['Key'];
                }
            }

            return $files;
        } catch (\Exception $e) {
            throw new HttpException(500, 'Failed to list files: ' . $e->getMessage());
        }
    }
}