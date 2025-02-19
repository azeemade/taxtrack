<?php

namespace App\Services\SharedServices;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
// use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Cloudinary\Api\Upload\UploadApi;

class FileUploadService
{
    public static function upload($requestFiles)
    {
        $images = [];
        if (isset($requestFiles)) {
            $files = $requestFiles;
            foreach ($files as $file) {
                if (is_string($file)) {
                    $images[] = self::uploadString($file);
                } else {
                    $images[] = self::uploadBinary($file);
                }
            }
        }
        return $images;
    }


    protected static function uploadBinary($file)
    {
        $ext = $file->getClientOriginalExtension();
        $name = basename($file->getClientOriginalName(), '.' . $ext);
        $uploadedFile = Cloudinary::upload($file->getRealPath(), ['public_id' => $name . '.' . $ext, 'resource_type' => 'raw']);
        $url = $uploadedFile->getSecurePath();
        $retrievalId = $uploadedFile->getPublicId();
        $size = $file->getSize();
        $sizeInMB = $size / 1024;
        return ["title" => $name, "size" => $sizeInMB, "url" => $url, "ext" => $ext, "retrieval_id" => $retrievalId];
    }

    protected static function uploadString($file)
    {
        $fileData = base64_decode(preg_replace('#^data:[^;]+;base64,#i', '', $file));
        $uniqueId = rand(10, 100000);
        $tmpFilePath = sys_get_temp_dir() . '/' . $uniqueId . '_' . date("Y-m-d") . '_' . time();
        file_put_contents($tmpFilePath, $fileData);

        // this just to help us get file info.
        $tmpFile = new File($tmpFilePath);

        $uploadedFile = (new UploadApi())->upload($file, [
            'public_id' => $tmpFile->getFilename() . '.' . self::getExt($file),
            'resource_type' => 'raw', // Set resource type to 'raw'
        ]);

        return ["title" => $tmpFile->getFilename(), "size" => $uploadedFile['bytes'] / 1024, "url" => $uploadedFile['secure_url'], "ext" => self::getExt($file), "retrieval_id" => $uploadedFile['public_id']];
    }


    protected static function getExt($file)
    {
        preg_match('/^data:(.*?);base64,/', $file, $matches);

        if (isset($matches[1])) {
            $mime_type = $matches[1];

            // MIME types mapped to file extensions
            $mime_to_extension = [
                // Images
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/svg+xml' => 'svg',
                'image/webp' => 'webp',
                'image/bmp' => 'bmp',
                'image/tiff' => 'tiff',
                // Audio
                'audio/mpeg' => 'mp3',
                'audio/ogg' => 'ogg',
                'audio/wav' => 'wav',
                'audio/aac' => 'aac',
                'audio/x-wav' => 'wav',
                'audio/flac' => 'flac',
                // Video
                'video/mp4' => 'mp4',
                'video/mpeg' => 'mpeg',
                'video/ogg' => 'ogv',
                'video/webm' => 'webm',
                'video/x-msvideo' => 'avi',
                'video/x-flv' => 'flv',
                // Documents
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'text/plain' => 'txt',
                'text/csv' => 'csv',
                'application/rtf' => 'rtf',
                'application/xml' => 'xml',
                // Archives
                'application/zip' => 'zip',
                'application/x-rar-compressed' => 'rar',
                'application/x-7z-compressed' => '7z',
                'application/x-tar' => 'tar',
                'application/gzip' => 'gz',
                'application/x-bzip2' => 'bz2',
                // Miscellaneous
                'application/json' => 'json',
                'application/octet-stream' => 'bin',
                'application/x-www-form-urlencoded' => 'urlencoded',
            ];

            // Determine the file extension
        }
        return isset($mime_to_extension[$mime_type]) ? $mime_to_extension[$mime_type] : 'unknown';
    }
}
