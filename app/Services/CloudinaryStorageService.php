<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryStorageService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        // បង្កើត Connection តែម្តងគត់នៅពេលហៅ Service នេះមកប្រើ
        $this->cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
    }

    /**
     * មុខងារសម្រាប់ Upload រូបភាព និងមានជម្រើសក្នុងការលុបរូបចាស់
     * * @param UploadedFile $file ឯកសាររូបភាពដែលបាន Upload ពី Request
     * @param string $folder ឈ្មោះ Folder ក្នុង Cloudinary (ឧ. 'profiles', 'products')
     * @param string|null $oldImageUrl URL នៃរូបភាពចាស់ដើម្បីលុបវាចេញ
     * @param array $transformations ការកំណត់ទំហំ ឬការកាត់រូប
     * @return string ត្រឡប់ URL ថ្មីមកវិញ
     */
    public function uploadImage(UploadedFile $file, string $folder, ?string $oldImageUrl = null, array $transformations = []): string
    {
        // ១. លុបរូបចាស់ចេញសិន (ប្រសិនបើមានទិន្នន័យបញ្ជូនមក)
        if ($oldImageUrl) {
            $this->deleteImage($oldImageUrl, $folder);
        }

        // ២. រៀបចំ Options សម្រាប់ការ Upload
        $options = [
            'folder' => $folder,
        ];

        // បើមានការកំណត់ Transformation យើងដាក់វាចូល, បើអត់ទេ យើងដាក់ Default
        if (!empty($transformations)) {
            $options['transformation'] = $transformations;
        } else {
            // Default Optimization ជានិច្ចទោះមិនបានបញ្ជាក់
            $options['transformation'] = [
                'quality' => 'auto:best',
                'fetch_format' => 'auto'
            ];
        }

        // ៣. ធ្វើការ Upload ទៅ Cloudinary
        $upload = $this->cloudinary->uploadApi()->upload($file->getRealPath(), $options);

        // ៤. ត្រឡប់ URL ដែលមានសុវត្ថិភាព (https) ទៅកាន់ Controller វិញ
        return $upload['secure_url'];
    }

    /**
     * មុខងារសម្រាប់លុបរូបភាពពី Cloudinary
     */
    public function deleteImage(string $imageUrl, string $folder): void
    {
        try {
            // ទាញយកតែឈ្មោះ File ចេញពី URL រួចផ្គុំជាមួយឈ្មោះ Folder ជា Public ID
            $filename = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_FILENAME);
            $publicId = $folder . '/' . $filename;

            $this->cloudinary->uploadApi()->destroy($publicId);
        } catch (\Exception $e) {
            // យើងបោះបង់ Error នេះចូល Log ព្រោះទោះលុបមិនបាន ក៏មិនគួររារាំងការ Upload ថ្មីដែរ
            Log::error("Cloudinary Delete Failed: " . $e->getMessage());
        }
    }
}
