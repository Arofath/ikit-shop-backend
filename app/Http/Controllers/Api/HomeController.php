<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HomeService;

class HomeController extends Controller
{
    public function index(HomeService $homeService)
    {
        $data = $homeService->getHomepageData();

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }
}
