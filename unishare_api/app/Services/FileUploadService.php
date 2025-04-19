<?php

namespace App\Services;

use App\Models\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected $googleDriveService;
    protected $minioService;
    protected $lockTimeout = 30; // Thời gian khóa tối đa (giây)
    protected $defaultStorage;

    /**
     * Tạo một instance mới của service.
     */
    public function __construct(GoogleDriveService $googleDriveService, MinIOService $minioService)
    {
        $this->googleDriveService = $googleDriveService;
        $this->minioService = $minioService;
        $this->defaultStorage = config('filesystems.default_storage', 'local');
    }

    /**
     * Khởi tạo một tải lên file mới.
     */
    public function initializeUpload(
        UploadedFile $file = null,
        $userId,
        $uploadableType,
        $uploadableId = null,
        $chunksTotal = 1,
        $storageType = null
    ) {
        $fileHash = $file ? $this->generateFileHash($file) : null;
        $originalFilename = $file ? $file->getClientOriginalName() : 'placeholder.txt';
        $storedFilename = $file ? $this->generateUniqueFilename($file) : Str::uuid() . '.txt';
        $uploadSessionId = Str::uuid()->toString();
        $fileSize = $file ? $file->getSize() : 0;

        // Sử dụng storage type được chỉ định hoặc mặc định
        $storageType = $storageType ?: $this->defaultStorage;

        // Kiểm tra xem file với hash giống nhau đã tồn tại chưa
        if ($fileHash) {
            $existingUpload = FileUpload::where('file_hash', $fileHash)->first();
            if ($existingUpload && $existingUpload->isComplete()) {
                // Trả về bản ghi tải lên file đã tồn tại
                return [
                    'file_upload' => $existingUpload,
                    'is_duplicate' => true,
                    'upload_session_id' => $uploadSessionId,
                ];
            }
        }

        // Kiểm tra dung lượng trống dựa trên loại storage
        if ($fileSize > 0) {
            try {
                // Lấy khóa để tránh tranh chấp
                $lock = $this->acquireLock('storage_space_check_' . $storageType);

                if (!$lock) {
                    throw new \Exception('Không thể kiểm tra dung lượng trống. Vui lòng thử lại sau.');
                }

                try {
                    // Kiểm tra dung lượng trống dựa trên loại storage
                    $availableSpace = $this->getAvailableSpace($storageType);

                    if ($availableSpace < $fileSize) {
                        $this->releaseLock($lock);
                        throw new \Exception('Không đủ dung lượng trống để tải lên file này.');
                    }

                    // Đặt trước dung lượng
                    $this->reserveSpace($fileSize, $uploadSessionId, $storageType);
                } finally {
                    // Đảm bảo khóa được giải phóng
                    $this->releaseLock($lock);
                }
            } catch (\Exception $e) {
                Log::error('Storage space check failed: ' . $e->getMessage());
                // Tiếp tục mà không kiểm tra dung lượng nếu có lỗi
            }
        }

        // Tạo bản ghi tải lên file mới
        $fileUpload = FileUpload::create([
            'user_id' => $userId,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => "uploads/{$userId}/{$storedFilename}",
            'file_type' => $file ? $file->getMimeType() : 'text/plain',
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'status' => 'pending',
            'upload_session_id' => $uploadSessionId,
            'chunks_total' => $chunksTotal,
            'chunks_received' => 0,
            'uploadable_type' => $uploadableType,
            'uploadable_id' => $uploadableId,
            'storage_type' => $storageType,
        ]);

        return [
            'file_upload' => $fileUpload,
            'is_duplicate' => false,
            'upload_session_id' => $uploadSessionId,
        ];
    }

    /**
     * Xử lý một phần của file.
     */
    public function processChunk(
        UploadedFile $chunk,
        string $uploadSessionId,
        int $chunkIndex,
        int $chunksTotal
    ) {
        // Tìm bản ghi tải lên file
        $fileUpload = FileUpload::where('upload_session_id', $uploadSessionId)->first();
        if (!$fileUpload) {
            throw new \Exception('Không tìm thấy phiên tải lên.');
        }

        // Kiểm tra xem phần này đã được tải lên chưa
        $chunkPath = "chunks/{$fileUpload->user_id}/{$uploadSessionId}/{$chunkIndex}";
        if (Storage::disk('local')->exists($chunkPath)) {
            // Phần này đã được tải lên, có thể là do kết nối bị ngắt và người dùng thử lại
            Log::info("Chunk {$chunkIndex} for upload {$uploadSessionId} already exists. Skipping.");
            return $fileUpload;
        }

        // Lưu phần file
        Storage::disk('local')->put($chunkPath, file_get_contents($chunk->getRealPath()));

        // Cập nhật bản ghi tải lên file
        $fileUpload->increment('chunks_received');

        // Kiểm tra xem tất cả các phần đã được nhận chưa
        if ($fileUpload->chunks_received >= $chunksTotal) {
            // Xử lý file hoàn chỉnh
            $this->processCompleteFile($fileUpload);
        }

        return $fileUpload;
    }

    /**
     * Xử lý file hoàn chỉnh sau khi tất cả các phần đã được nhận.
     */
    protected function processCompleteFile(FileUpload $fileUpload)
    {
        // Cập nhật trạng thái thành đang xử lý
        $fileUpload->update(['status' => 'processing']);

        try {
            // Gộp các phần nếu cần thiết
            if ($fileUpload->chunks_total > 1) {
                $this->mergeChunks($fileUpload);
            } else {
                // Di chuyển phần duy nhất đến vị trí cuối cùng
                $chunkPath = "chunks/{$fileUpload->user_id}/{$fileUpload->upload_session_id}/0";
                $finalPath = $fileUpload->file_path;

                if (Storage::disk('local')->exists($chunkPath)) {
                    Storage::disk('local')->move($chunkPath, $finalPath);
                }
            }

            // Xử lý tải lên dựa trên loại storage
            $storageType = $fileUpload->storage_type ?: $this->defaultStorage;

            switch ($storageType) {
                case 'google_drive':
                    $this->uploadToGoogleDrive($fileUpload);
                    break;

                case 'minio':
                    $this->uploadToMinIO($fileUpload);
                    break;

                default:
                    // Mặc định là lưu trên local, không cần làm gì thêm
                    break;
            }

            // Cập nhật trạng thái thành hoàn thành
            $fileUpload->update(['status' => 'completed']);

            // Dọn dẹp các phần
            $this->cleanupChunks($fileUpload);
        } catch (\Exception $e) {
            // Cập nhật trạng thái thành thất bại
            $fileUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Dọn dẹp các phần
            $this->cleanupChunks($fileUpload);

            // Giải phóng dung lượng đã đặt trước
            $this->releaseReservedSpace($fileUpload->upload_session_id, $fileUpload->storage_type);

            throw $e;
        }
    }

    /**
     * Tải lên file lên Google Drive.
     */
    protected function uploadToGoogleDrive(FileUpload $fileUpload)
    {
        if (!config('filesystems.use_google_drive', false)) {
            throw new \Exception('Google Drive không được cấu hình.');
        }

        try {
            // Lấy khóa để tránh tranh chấp
            $lock = $this->acquireLock('google_drive_upload_' . $fileUpload->id);

            if (!$lock) {
                throw new \Exception('Không thể tải lên Google Drive. Vui lòng thử lại sau.');
            }

            try {
                // Tải lên Google Drive
                $googleDriveId = $this->googleDriveService->uploadFile(
                    Storage::disk('local')->path($fileUpload->file_path),
                    $fileUpload->original_filename,
                    $fileUpload->file_type,
                    $fileUpload->upload_session_id
                );

                // Cập nhật bản ghi tải lên file với ID Google Drive
                $fileUpload->update([
                    'google_drive_id' => $googleDriveId,
                    'external_url' => null, // Không có URL trực tiếp cho Google Drive
                ]);
            } finally {
                // Đảm bảo khóa được giải phóng
                $this->releaseLock($lock);
            }
        } catch (\Exception $e) {
            Log::error('Google Drive upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Tải lên file lên MinIO.
     */
    protected function uploadToMinIO(FileUpload $fileUpload)
    {
        if (!config('filesystems.use_minio', false)) {
            throw new \Exception('MinIO không được cấu hình.');
        }

        try {
            // Lấy khóa để tránh tranh chấp
            $lock = $this->acquireLock('minio_upload_' . $fileUpload->id);

            if (!$lock) {
                throw new \Exception('Không thể tải lên MinIO. Vui lòng thử lại sau.');
            }

            try {
                // Tải lên MinIO
                $result = $this->minioService->uploadFile(
                    Storage::disk('local')->path($fileUpload->file_path),
                    $fileUpload->original_filename,
                    $fileUpload->file_type,
                    $fileUpload->upload_session_id
                );

                // Cập nhật bản ghi tải lên file với thông tin MinIO
                $fileUpload->update([
                    'minio_key' => $result['key'],
                    'external_url' => $result['url'],
                ]);
            } finally {
                // Đảm bảo khóa được giải phóng
                $this->releaseLock($lock);
            }
        } catch (\Exception $e) {
            Log::error('MinIO upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gộp các phần thành một file duy nhất.
     */
    protected function mergeChunks(FileUpload $fileUpload)
    {
        $finalPath = $fileUpload->file_path;
        $tempFile = Storage::disk('local')->path("temp/{$fileUpload->upload_session_id}");

        // Tạo thư mục tạm nếu chưa tồn tại
        if (!file_exists(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }

        // Tạo hoặc cắt ngắn file tạm
        $outFile = fopen($tempFile, 'w');

        // Thêm từng phần vào file tạm
        for ($i = 0; $i < $fileUpload->chunks_total; $i++) {
            $chunkPath = "chunks/{$fileUpload->user_id}/{$fileUpload->upload_session_id}/{$i}";

            if (Storage::disk('local')->exists($chunkPath)) {
                $chunkContent = Storage::disk('local')->get($chunkPath);
                fwrite($outFile, $chunkContent);
            } else {
                fclose($outFile);
                throw new \Exception("Phần {$i} bị thiếu.");
            }
        }

        fclose($outFile);

        // Di chuyển file tạm đến vị trí cuối cùng
        Storage::disk('local')->put($finalPath, file_get_contents($tempFile));

        // Xóa file tạm
        unlink($tempFile);
    }

    /**
     * Dọn dẹp các phần sau khi xử lý.
     */
    protected function cleanupChunks(FileUpload $fileUpload)
    {
        $chunksDir = "chunks/{$fileUpload->user_id}/{$fileUpload->upload_session_id}";
        Storage::disk('local')->deleteDirectory($chunksDir);
    }

    /**
     * Tạo tên file duy nhất cho file đã tải lên.
     */
    protected function generateUniqueFilename(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Tạo hash cho file để phát hiện trùng lặp.
     */
    protected function generateFileHash(UploadedFile $file)
    {
        // Đối với file nhỏ, sử dụng toàn bộ nội dung
        if ($file->getSize() < 10 * 1024 * 1024) { // Nhỏ hơn 10MB
            return md5_file($file->getRealPath());
        }

        // Đối với file lớn hơn, sử dụng một mẫu để tạo hash
        $handle = fopen($file->getRealPath(), 'rb');

        // Đọc 1MB đầu tiên
        $firstMB = fread($handle, 1024 * 1024);

        // Tìm đến giữa và đọc 1MB
        fseek($handle, (int)($file->getSize() / 2));
        $middleMB = fread($handle, 1024 * 1024);

        // Tìm đến cuối trừ 1MB và đọc 1MB
        fseek($handle, max(0, $file->getSize() - 1024 * 1024));
        $lastMB = fread($handle, 1024 * 1024);

        fclose($handle);

        // Kết hợp kích thước file với các mẫu để tạo hash duy nhất
        return md5($file->getSize() . md5($firstMB . $middleMB . $lastMB));
    }

    /**
     * Lấy một file theo ID phiên tải lên của nó.
     */
    public function getFileBySessionId(string $uploadSessionId)
    {
        return FileUpload::where('upload_session_id', $uploadSessionId)->first();
    }

    /**
     * Xử lý tải lên bị gián đoạn.
     */
    public function handleInterruptedUpload(string $uploadSessionId)
    {
        $fileUpload = $this->getFileBySessionId($uploadSessionId);

        if (!$fileUpload) {
            return null;
        }

        // Nếu tải lên vẫn đang tiến hành, chúng ta có thể tiếp tục
        if ($fileUpload->status === 'pending') {
            // Kiểm tra xem có phần nào đã được tải lên chưa
            $chunksDir = "chunks/{$fileUpload->user_id}/{$uploadSessionId}";
            $receivedChunks = [];

            if (Storage::disk('local')->exists($chunksDir)) {
                $files = Storage::disk('local')->files($chunksDir);
                foreach ($files as $file) {
                    $chunkIndex = (int) basename($file);
                    $receivedChunks[] = $chunkIndex;
                }
            }

            // Cập nhật số lượng phần đã nhận
            $chunksReceived = count($receivedChunks);
            $fileUpload->update(['chunks_received' => $chunksReceived]);

            return [
                'file_upload' => $fileUpload,
                'chunks_received' => $chunksReceived,
                'chunks_total' => $fileUpload->chunks_total,
                'received_chunks' => $receivedChunks,
                'can_resume' => true,
            ];
        }

        return [
            'file_upload' => $fileUpload,
            'can_resume' => false,
        ];
    }

    /**
     * Xóa một tải lên file.
     */
    public function deleteFileUpload(FileUpload $fileUpload)
    {
        // Xóa file từ bộ nhớ local
        if (Storage::disk('local')->exists($fileUpload->file_path)) {
            Storage::disk('local')->delete($fileUpload->file_path);
        }

        // Xóa từ storage tương ứng
        if ($fileUpload->storage_type === 'google_drive' && $fileUpload->google_drive_id) {
            try {
                $this->googleDriveService->deleteFile($fileUpload->google_drive_id);
            } catch (\Exception $e) {
                Log::error('Failed to delete file from Google Drive: ' . $e->getMessage());
            }
        } elseif ($fileUpload->storage_type === 'minio' && $fileUpload->minio_key) {
            try {
                $this->minioService->deleteFile($fileUpload->minio_key);
            } catch (\Exception $e) {
                Log::error('Failed to delete file from MinIO: ' . $e->getMessage());
            }
        }

        // Xóa các phần nếu chúng tồn tại
        $this->cleanupChunks($fileUpload);

        // Giải phóng dung lượng đã đặt trước
        $this->releaseReservedSpace($fileUpload->upload_session_id, $fileUpload->storage_type);

        // Xóa bản ghi tải lên file
        $fileUpload->delete();
    }

    /**
     * Lấy khóa để tránh tranh chấp.
     */
    protected function acquireLock($name, $timeout = null)
    {
        $timeout = $timeout ?: $this->lockTimeout;
        $lockName = "file_upload_lock:{$name}";

        return DB::transaction(function () use ($lockName, $timeout) {
            $result = DB::select("SELECT GET_LOCK(?, ?) AS acquired", [$lockName, $timeout]);
            return $result[0]->acquired ? $lockName : false;
        });
    }

    /**
     * Giải phóng khóa.
     */
    protected function releaseLock($lockName)
    {
        if (!$lockName) {
            return true;
        }

        return DB::transaction(function () use ($lockName) {
            $result = DB::select("SELECT RELEASE_LOCK(?) AS released", [$lockName]);
            return $result[0]->released;
        });
    }

    /**
     * Lấy dung lượng trống dựa trên loại storage.
     */
    protected function getAvailableSpace($storageType)
    {
        switch ($storageType) {
            case 'google_drive':
                if (!config('filesystems.use_google_drive', false)) {
                    throw new \Exception('Google Drive không được cấu hình.');
                }
                return $this->googleDriveService->getAvailableSpace();

            case 'minio':
                if (!config('filesystems.use_minio', false)) {
                    throw new \Exception('MinIO không được cấu hình.');
                }
                return $this->minioService->getAvailableSpace();

            default:
                // Đối với local storage, sử dụng disk_free_space
                return disk_free_space(storage_path('app'));
        }
    }

    /**
     * Đặt trước dung lượng dựa trên loại storage.
     */
    protected function reserveSpace($size, $reservationId, $storageType)
    {
        switch ($storageType) {
            case 'google_drive':
                if (!config('filesystems.use_google_drive', false)) {
                    return true;
                }
                return $this->googleDriveService->reserveSpace($size, $reservationId);

            case 'minio':
                if (!config('filesystems.use_minio', false)) {
                    return true;
                }
                return $this->minioService->reserveSpace($size, $reservationId);

            default:
                // Đối với local storage, không cần đặt trước
                return true;
        }
    }

    /**
     * Giải phóng dung lượng đã đặt trước dựa trên loại storage.
     */
    protected function releaseReservedSpace($reservationId, $storageType)
    {
        switch ($storageType) {
            case 'google_drive':
                if (!config('filesystems.use_google_drive', false)) {
                    return true;
                }
                return $this->googleDriveService->releaseReservedSpace($reservationId);

            case 'minio':
                if (!config('filesystems.use_minio', false)) {
                    return true;
                }
                return $this->minioService->releaseReservedSpace($reservationId);

            default:
                // Đối với local storage, không cần giải phóng
                return true;
        }
    }

    /**
     * Lấy URL của file.
     */
    public function getFileUrl(FileUpload $fileUpload)
    {
        if ($fileUpload->external_url) {
            return $fileUpload->external_url;
        }

        switch ($fileUpload->storage_type) {
            case 'google_drive':
                if (!$fileUpload->google_drive_id) {
                    throw new \Exception('File không có Google Drive ID.');
                }
                // Google Drive không hỗ trợ URL trực tiếp, trả về null
                return null;

            case 'minio':
                if (!$fileUpload->minio_key) {
                    throw new \Exception('File không có MinIO key.');
                }
                return $this->minioService->getFileUrl($fileUpload->minio_key);

            default:
                // Đối với local storage, trả về URL tương đối
                return url('storage/' . $fileUpload->file_path);
        }
    }

    /**
     * Lấy nội dung của file.
     */
    public function getFileContents(FileUpload $fileUpload)
    {
        switch ($fileUpload->storage_type) {
            case 'google_drive':
                if (!$fileUpload->google_drive_id) {
                    throw new \Exception('File không có Google Drive ID.');
                }
                return $this->googleDriveService->getFile($fileUpload->google_drive_id);

            case 'minio':
                if (!$fileUpload->minio_key) {
                    throw new \Exception('File không có MinIO key.');
                }
                return $this->minioService->getFile($fileUpload->minio_key);

            default:
                // Đối với local storage, đọc từ disk
                return Storage::disk('local')->get($fileUpload->file_path);
        }
    }
}
