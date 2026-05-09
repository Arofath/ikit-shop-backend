<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
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
        $cart->load('items.product');

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

        // រក ឬ បង្កើត Cart របស់ User
        $cart = Cart::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        // ឆែកមើលថាតើទំនិញនេះមានក្នុងកន្ត្រកស្រាប់ហើយឬនៅ?
        $cartItem = $cart->items()->where('product_id', $request->product_id)->first();

        if ($cartItem) {
            // បើមានហើយ គ្រាន់តែបូកចំនួន (Quantity) បន្ថែម
            $cartItem->increment('quantity', $request->quantity);
        } else {
            // បើមិនទាន់មាន បង្កើត Item ថ្មី
            $cart->items()->create([
                'product_id' => $request->product_id,
                'quantity'   => $request->quantity
            ]);
        }

        // Load ទិន្នន័យមកវិញដើម្បីបញ្ជូនទៅ Frontend
        $cart->load('items.product');

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully.',
            'data'    => new CartResource($cart)
        ], 200);
    }

    // កែប្រែចំនួន (Quantity) របស់ទំនិញក្នុងកន្ត្រក (+ ឬ -)
    public function updateItem(Request $request, string $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

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

        // កែប្រែចំនួនថ្មី
        $cartItem->update([
            'quantity' => $request->quantity
        ]);

        $cart = $cartItem->cart()->with('items.product')->first();

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully.',
            'data'    => new CartResource($cart)
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
