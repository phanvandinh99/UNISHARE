<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    protected $client;
    protected $service;
    protected $reservedSpacePrefix = 'google_drive_reserved_space:';
    protected $reservedSpaceExpiration = 3600; // 1 giờ
    
    public function __construct()
    {
        if (config('filesystems.use_google_drive', false)) {
            $this->initializeGoogleClient();
        }
    }
    
    protected function initializeGoogleClient()
    {
        try {
            $this->client = new Google_Client();
            $this->client->setAuthConfig(storage_path('app/google-service-account.json'));
            $this->client->addScope(Google_Service_Drive::DRIVE);
            $this->service = new Google_Service_Drive($this->client);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google Drive client: ' . $e->getMessage());
        }
    }
    
    public function uploadFile($filePath, $fileName, $mimeType = null, $reservationId = null)
    {
        if (!$this->service) {
            throw new \Exception('Google Drive service not initialized');
        }
        
        // Giải phóng dung lượng đã đặt trước nếu có
        if ($reservationId) {
            $this->releaseReservedSpace($reservationId);
        }
        
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $fileName,
            'parents' => [config('filesystems.disks.google.folder_id')]
        ]);
        
        $content = file_get_contents($filePath);
        
        $file = $this->service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType ?: mime_content_type($filePath),
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);
        
        return $file->id;
    }
    
    public function deleteFile($fileId)
    {
        if (!$this->service) {
            throw new \Exception('Google Drive service not initialized');
        }
        
        $this->service->files->delete($fileId);
        
        return true;
    }
    
    public function getFile($fileId)
    {
        if (!$this->service) {
            throw new \Exception('Google Drive service not initialized');
        }
        
        $file = $this->service->files->get($fileId, ['alt' => 'media']);
        
        return $file->getBody()->getContents();
    }
    
    public function getAvailableSpace()
    {
        if (!$this->service) {
            throw new \Exception('Google Drive service not initialized');
        }
        
        $about = $this->service->about->get(['fields' => 'storageQuota']);
        $storageQuota = $about->getStorageQuota();
        
        $limit = $storageQuota->getLimit();
        $usage = $storageQuota->getUsage();
        
        // Trừ đi dung lượng đã đặt trước
        $reservedSpace = $this->getTotalReservedSpace();
        
        return $limit - $usage - $reservedSpace;
    }
    
    public function hasEnoughSpace($requiredSpace)
    {
        try {
            $availableSpace = $this->getAvailableSpace();
            return $availableSpace >= $requiredSpace;
        } catch (\Exception $e) {
            Log::error('Failed to check available space: ' . $e->getMessage());
            // Mặc định là true nếu không thể kiểm tra
            return true;
        }
    }
    
    /**
     * Đặt trước dung lượng cho một tải lên.
     */
    public function reserveSpace($size, $reservationId)
    {
        $key = $this->reservedSpacePrefix . $reservationId;
        Cache::put($key, $size, $this->reservedSpaceExpiration);
        
        Log::info("Reserved {$size} bytes for upload {$reservationId}");
        
        return true;
    }
    
    /**
     * Giải phóng dung lượng đã đặt trước.
     */
    public function releaseReservedSpace($reservationId)
    {
        $key = $this->reservedSpacePrefix . $reservationId;
        $size = Cache::get($key, 0);
        
        if ($size > 0) {
            Cache::forget($key);
            Log::info("Released {$size} bytes for upload {$reservationId}");
        }
        
        return true;
    }
    
    /**
     * Lấy tổng dung lượng đã đặt trước.
     */
    protected function getTotalReservedSpace()
    {
        $totalReserved = 0;
        $prefix = $this->reservedSpacePrefix;
        
        // Lấy tất cả các khóa cache bắt đầu bằng prefix
        $keys = Cache::getPrefix() ? [] : Cache::get($prefix . '*');
        
        foreach ($keys as $key) {
            $size = Cache::get($key, 0);
            $totalReserved += $size;
        }
        
        return $totalReserved;
    }
}
