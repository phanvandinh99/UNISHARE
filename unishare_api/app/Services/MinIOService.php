<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MinIOService
{
    protected $client;
    protected $bucket;
    protected $reservedSpacePrefix = 'minio_reserved_space:';
    protected $reservedSpaceExpiration = 3600; // 1 giờ
    
    /**
     * Tạo một instance mới của service.
     */
    public function __construct()
    {
        if (config('filesystems.use_minio', false)) {
            $this->initializeMinIOClient();
        }
    }
    
    /**
     * Khởi tạo client MinIO.
     */
    protected function initializeMinIOClient()
    {
        try {
            $this->client = new S3Client([
                'version' => 'latest',
                'region'  => config('filesystems.disks.minio.region', 'us-east-1'),
                'endpoint' => config('filesystems.disks.minio.endpoint'),
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key'    => config('filesystems.disks.minio.key'),
                    'secret' => config('filesystems.disks.minio.secret'),
                ],
            ]);
            
            $this->bucket = config('filesystems.disks.minio.bucket');
            
            // Kiểm tra xem bucket có tồn tại không, nếu không thì tạo mới
            if (!$this->client->doesBucketExist($this->bucket)) {
                $this->client->createBucket([
                    'Bucket' => $this->bucket,
                ]);
                
                // Thiết lập quyền truy cập cho bucket
                $this->client->putBucketPolicy([
                    'Bucket' => $this->bucket,
                    'Policy' => json_encode([
                        'Version' => '2012-10-17',
                        'Statement' => [
                            [
                                'Sid' => 'PublicReadGetObject',
                                'Effect' => 'Allow',
                                'Principal' => '*',
                                'Action' => ['s3:GetObject'],
                                'Resource' => ["arn:aws:s3:::{$this->bucket}/*"]
                            ]
                        ]
                    ])
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to initialize MinIO client: ' . $e->getMessage());
        }
    }
    
    /**
     * Tải lên file lên MinIO.
     */
    public function uploadFile($filePath, $fileName, $mimeType = null, $reservationId = null)
    {
        if (!$this->client) {
            throw new \Exception('MinIO service not initialized');
        }
        
        // Giải phóng dung lượng đã đặt trước nếu có
        if ($reservationId) {
            $this->releaseReservedSpace($reservationId);
        }
        
        $key = 'uploads/' . basename($fileName);
        
        try {
            // Tải lên file
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => fopen($filePath, 'r'),
                'ContentType' => $mimeType ?: mime_content_type($filePath),
                'ACL'    => 'public-read',
            ]);
            
            return [
                'key' => $key,
                'url' => $result['ObjectURL'],
            ];
        } catch (S3Exception $e) {
            Log::error('MinIO upload error: ' . $e->getMessage());
            throw new \Exception('Failed to upload file to MinIO: ' . $e->getMessage());
        }
    }
    
    /**
     * Xóa file từ MinIO.
     */
    public function deleteFile($key)
    {
        if (!$this->client) {
            throw new \Exception('MinIO service not initialized');
        }
        
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
            
            return true;
        } catch (S3Exception $e) {
            Log::error('MinIO delete error: ' . $e->getMessage());
            throw new \Exception('Failed to delete file from MinIO: ' . $e->getMessage());
        }
    }
    
    /**
     * Lấy file từ MinIO.
     */
    public function getFile($key)
    {
        if (!$this->client) {
            throw new \Exception('MinIO service not initialized');
        }
        
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
            
            return $result['Body']->getContents();
        } catch (S3Exception $e) {
            Log::error('MinIO get error: ' . $e->getMessage());
            throw new \Exception('Failed to get file from MinIO: ' . $e->getMessage());
        }
    }
    
    /**
     * Lấy URL của file.
     */
    public function getFileUrl($key)
    {
        if (!$this->client) {
            throw new \Exception('MinIO service not initialized');
        }
        
        try {
            $command = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
            
            // Tạo URL có thời hạn 1 giờ
            $presignedRequest = $this->client->createPresignedRequest($command, '+1 hour');
            
            return (string) $presignedRequest->getUri();
        } catch (S3Exception $e) {
            Log::error('MinIO URL error: ' . $e->getMessage());
            throw new \Exception('Failed to get file URL from MinIO: ' . $e->getMessage());
        }
    }
    
    /**
     * Lấy dung lượng trống.
     */
    public function getAvailableSpace()
    {
        // MinIO không cung cấp API để lấy dung lượng trống
        // Chúng ta có thể sử dụng một giá trị mặc định hoặc lấy từ cấu hình
        $defaultSpace = config('filesystems.disks.minio.max_size', 1024 * 1024 * 1024 * 10); // 10GB mặc định
        
        // Trừ đi dung lượng đã đặt trước
        $reservedSpace = $this->getTotalReservedSpace();
        
        return $defaultSpace - $reservedSpace;
    }
    
    /**
     * Kiểm tra xem có đủ dung lượng không.
     */
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
        
        Log::info("Reserved {$size} bytes for upload {$reservationId} on MinIO");
        
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
            Log::info("Released {$size} bytes for upload {$reservationId} on MinIO");
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
