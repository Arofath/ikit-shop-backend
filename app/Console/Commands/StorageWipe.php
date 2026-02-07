<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SupabaseStorageService;

class StorageWipe extends Command
{
    protected $signature = 'storage:wipe {--force : Force the operation to run without confirmation}';
    protected $description = 'Delete all files from Supabase buckets';

    public function handle(SupabaseStorageService $storage)
    {
        // ១. ការពារដាច់ខាតមិនឱ្យរត់លើ Production Server
        if (app()->environment('production')) {
            $this->error('****************************************************');
            $this->error('* DANGER: This command cannot run in Production! *');
            $this->error('****************************************************');
            return 1;
        }

        // ២. បន្ថែមការសួរបញ្ជាក់ (Confirmation) ដើម្បីការពារការច្រឡំដៃ
        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to wipe ALL files in Supabase? This cannot be undone!')) {
                $this->comment('Operation cancelled.');
                return 0;
            }
        }

        $buckets = [
            env('SUPABASE_BUCKET'),
            env('SUPABASE_CATEGORY_BUCKET'),
            env('SUPABASE_BRAND_BUCKET'),
            env('SUPABASE_PRODUCT_BUCKET'),
            env('SUPABASE_SLIDESHOW_BUCKET'),
            // ថែម bucket ផ្សេងៗទៀត...
        ];

        $this->warn('Wiping Supabase storage...');

        foreach ($buckets as $bucket) {
            if (!$bucket) continue;

            $files = $storage->listFiles($bucket);

            // Supabase listFiles ជួនកាលផ្ញើមកជា array នៃ objects ដែលមាន 'name'
            $fileNames = collect($files)->pluck('name')->toArray();

            if (!empty($fileNames)) {
                $storage->deleteMultiple($bucket, $fileNames);
                $this->info("✓ Cleared bucket: {$bucket} (" . count($fileNames) . " files)");
            } else {
                $this->line("- Bucket {$bucket} is already empty.");
            }
        }

        $this->info('Successfully cleaned all storage buckets!');
    }
}

// php artisan storage:wipe --force && php artisan migrate:fresh