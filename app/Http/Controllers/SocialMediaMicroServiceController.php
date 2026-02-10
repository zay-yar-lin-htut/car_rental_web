<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Services\FileService;
use Illuminate\Http\Request;

class SocialMediaMicroServiceController extends Controller
{
    protected FileService $fileService;
    protected Helper $helper;
    protected string $baseUrl;
    public function __construct(FileService $fileService, Helper $helper)
    {
        $this->fileService = $fileService;
        $this->helper = $helper;
        $this->baseUrl = rtrim(env('R2_URL', ''), '/');
    }

    public function social_media_file_upload (Request $request)
    {
        $rule = [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240',
        ];

        $validate = $this->helper->Validate($request, $rule);
        if (is_null($validate)) {
            $file = $request->file('image');
        } else {
            return $this->helper->PostMan(null, 422, $validate);
        }
        
        $filename = $this->fileService->uploadFile($file, 'SocialMedia/');
        $fileUrl = $this->baseUrl . '/' . $filename;

        if(!$filename){
            return $this->helper->PostMan(null, 500, "File upload failed");
        }
        return $this->helper->PostMan($fileUrl, 200, "File uploaded successfully");
    }

    public function social_media_file_delete(Request $request) {
        $rule = [
            'path' => 'required|string',
        ];

        $validate = $this->helper->Validate($request, $rule);
        if (!is_null($validate)) {   
            return $this->helper->PostMan(null, 422, $validate);
        }

        $fullPath = $request->input('path');

        $relativePath = str_replace($this->baseUrl, '', $fullPath);
        $relativePath = ltrim($relativePath, '/');

        $isDelete = $this->fileService->deleteFile($relativePath);

        if ($isDelete === true) {
            return $this->helper->PostMan(null, 200, "File deleted successfully");
        }

        return $this->helper->PostMan(null, 500, "File deletion failed");
    }
}