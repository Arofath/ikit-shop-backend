<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\Response;

class SupabaseStorageService
{
    protected string $supabaseUrl;
    protected string $serviceKey;
    public function __construct()
    {
        $this->supabaseUrl = config('services.supabase.url');
        $this->serviceKey = config('services.supabase.service_key');
    }

    public function uploadImage(
        UploadedFile $file,
        string $bucket,
        ?string $oldImageUrl = null,
        ?string $prefix = null
    ): string {
        // Delete old image if exists
        if ($oldImageUrl) {
            $this->deleteImage($oldImageUrl, $bucket);
        }

        $fileName = ($prefix ?? 'file') . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();

        $uploadUrl = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$fileName}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey,
            'Content-Type'  => $file->getMimeType(),
        ])->withBody(
            file_get_contents($file),
            $file->getMimeType()
        )->post($uploadUrl);


        // faild upload
        if (isset($response['error']) && $response['error'] !== null) {
            throw new \Exception('Supabase upload failed: ' . $response['error']['message']);
        }


        return "{$this->supabaseUrl}/storage/v1/object/public/{$bucket}/{$fileName}";
    }

    public function deleteImage(string $imageUrl, string $bucket): void
    {
        $path = str_replace(
            "{$this->supabaseUrl}/storage/v1/object/public/{$bucket}/",
            '',
            $imageUrl
        );

        $deleteUrl = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$path}";

        Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey,
        ])->delete($deleteUrl);
    }

    // ថែមក្នុង app/Services/SupabaseStorageService.php

    public function listFiles(string $bucket): array
    {
       
        $listUrl = "{$this->supabaseUrl}/storage/v1/object/list/{$bucket}";
        /** @var Response $response */
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey,
        ])->post($listUrl, ['prefix' => '']);

        return $response->successful() ? $response->json() : [];
    }

    public function deleteMultiple(string $bucket, array $fileNames): void
    {
        if (empty($fileNames)) return;

        $deleteUrl = "{$this->supabaseUrl}/storage/v1/object/{$bucket}";
        Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->serviceKey,
        ])->delete($deleteUrl, ['prefixes' => $fileNames]);
    }
}
