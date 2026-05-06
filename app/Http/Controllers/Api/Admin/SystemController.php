<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SystemController extends Controller
{
    public function clearCache()
    {
        try {
            // លុប Application Cache ធម្មតា
            Cache::flush();

            // (ជម្រើស) បើចង់ Clear ដល់កម្រិត System របស់ Laravel តែម្តង អាចប្រើ Artisan Command:
            Artisan::call('cache:clear');
            // Artisan::call('config:clear');
            // Artisan::call('route:clear');

            return response()->json([
                'success' => true,
                'message' => 'System cache cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }
}
