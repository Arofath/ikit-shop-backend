<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FavoriteResource;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // ១. ទាញយកទំនិញ Favorite ទាំងអស់របស់អតិថិជន
    public function index(Request $request)
    {
        // ស្វែងរក Favorite របស់ User រួចទាញយក Product និងរូបភាព (Eager Loading)
        $favorites = Favorite::where('user_id', $request->user()->id)
            ->with([
                'product.thumbnail',
                'product.images'
            ])
            ->latest() // តម្រៀបថ្មីៗនៅខាងលើ
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Favorites fetched successfully.',
            'data'    => FavoriteResource::collection($favorites)
        ], 200);
    }

    // ២. មុខងារ Toggle (Add ឬ Remove ក្នុងពេលតែមួយ)
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
        ]);

        $userId = $request->user()->id;
        $productId = $request->product_id;

        // ឆែកមើលថាតើគាត់បាន Favorite វាហើយឬនៅ?
        $favorite = Favorite::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($favorite) {
            // បើមានហើយ -> លុបចេញ (Remove from Favorite)
            $favorite->delete();

            return response()->json([
                'success'     => true,
                'message'     => 'Product removed from favorites.',
                'is_favorite' => false // ប្រាប់ Frontend ឱ្យដោះពណ៌ក្រហមពីបេះដូងវិញ
            ], 200);
        } else {
            // បើមិនទាន់មាន -> បន្ថែមចូល (Add to Favorite)
            Favorite::create([
                'user_id'    => $userId,
                'product_id' => $productId,
            ]);

            return response()->json([
                'success'     => true,
                'message'     => 'Product added to favorites.',
                'is_favorite' => true // ប្រាប់ Frontend ឱ្យលាបពណ៌ក្រហមលើបេះដូង
            ], 200);
        }
    }
}
