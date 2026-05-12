<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // ទាញយកទិន្នន័យក្នុងកន្ត្រកបច្ចុប្បន្នរបស់អតិថិជន
    public function index(Request $request)
    {
        // ស្វែងរក Cart របស់ User, បើគ្មានទេ បង្កើតថ្មីមួយឱ្យគាត់ដោយស្វ័យប្រវត្តិ
        $cart = Cart::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        // ទាញយក Items ទាំងអស់ព្រមទាំងទិន្នន័យ Product (Eager Loading ដើម្បីឱ្យដើរលឿន)
        $cart->load([
            'items.product.thumbnail', // 🌟 ទាញយករូបភាពតំណាង
            // 'items.product.images',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cart fetched successfully.',
            'data'    => new CartResource($cart)
        ], 200);
    }

    // បន្ថែមទំនិញចូលកន្ត្រក
    public function addItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity'   => 'required|integer|min:1'
        ]);

        // 🌟 ១. ទាញយក Product ដើម្បីឆែកស្តុកសិន
        $product = Product::findOrFail($request->product_id);

        // 🌟 ២. ឆែកមើលថាតើការ Add លើកដំបូងនេះ លើសស្តុកឬអត់?
        if ($product->current_stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Sorry, only {$product->current_stock} items are available in stock."
            ], 400);
        }

        $cart = Cart::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        $cartItem = $cart->items()->where('product_id', $request->product_id)->first();

        if ($cartItem) {
            // 🌟 ៣. បើមានក្នុងកន្ត្រកស្រាប់ ត្រូវបូកចំនួនចាស់ និងថ្មីចូលគ្នា រួចឆែកស្តុកម្តងទៀត
            $newQuantity = $cartItem->quantity + $request->quantity;

            if ($product->current_stock < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot add more. You already have {$cartItem->quantity} in your cart, and only {$product->current_stock} are available in total."
                ], 400);
            }

            $cartItem->increment('quantity', $request->quantity);
        } else {
            $cart->items()->create([
                'product_id' => $request->product_id,
                'quantity'   => $request->quantity
            ]);
        }

        // 🌟 កែប្រែត្រង់នេះ៖ បន្ថែម thumbnail និង images
        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully.',
            'data'    => new CartResource($cart->load([
                'items.product.thumbnail',
                'items.product.images'
            ]))
        ], 200);
    }

    // កែប្រែចំនួន (Quantity) របស់ទំនិញក្នុងកន្ត្រក (+ ឬ -)
    public function updateItem(Request $request, string $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem = CartItem::with('product')->where('id', $itemId)
            ->whereHas('cart', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.'
            ], 404);
        }

        // 🌟 ៤. ឆែកមើលថាតើចំនួនដែលគាត់ Update ថ្មី (ឧ. ចុច + ឡើងដល់ ៥) លើសស្តុកឬអត់?
        if ($cartItem->product->current_stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Cannot update. Only {$cartItem->product->current_stock} items are available in stock."
            ], 400);
        }

        $cartItem->update([
            'quantity' => $request->quantity
        ]);

        $cart = $cartItem->cart()->first();

        // 🌟 កែប្រែត្រង់នេះ៖ បន្ថែម thumbnail និង images
        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully.',
            'data'    => new CartResource($cart->load([
                'items.product.thumbnail',
                'items.product.images'
            ]))
        ], 200);
    }
    // លុបទំនិញណាមួយចេញពីកន្ត្រក
    public function removeItem(Request $request, string $itemId)
    {
        $cartItem = CartItem::where('id', $itemId)
            ->whereHas('cart', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.'
            ], 404);
        }

        $cartId = $cartItem->cart_id;
        $cartItem->delete();

        $cart = Cart::with('items.product')->find($cartId);

        return response()->json([
            'success' => true,
            'message' => 'Product removed from cart successfully.',
            'data'    => new CartResource($cart)
        ], 200);
    }

    // លុបទំនិញទាំងអស់ចេញពីកន្ត្រក (ឧ. ពេល Checkout រួចរាល់)
    public function clearCart(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();

        if ($cart) {
            $cart->items()->delete(); // លុបតែ Items បានហើយ ទុកតួ Cart ដដែល
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully.',
            'data'    => $cart ? new CartResource($cart) : []
        ], 200);
    }
}
