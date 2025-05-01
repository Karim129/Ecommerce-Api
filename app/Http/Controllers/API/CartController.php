<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Cart",
 *     description="API Endpoints for shopping cart management"
 * )
 *
 * @OA\Schema(
 *     schema="CartItem",
 *     required={"id", "user_id", "product_id", "quantity"},
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="product", ref="#/components/schemas/Product"),
 *     @OA\Property(property="quantity", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/cart",
     *     summary="Get user's cart items",
     *     tags={"Cart"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, ar)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cart items retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/CartItem")),
     *             @OA\Property(property="total", type="number", format="float")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $cartItems = $request->user()->cart()
            ->with('product')
            ->get();

        return [
            'items' => CartResource::collection($cartItems),
            'total' => $cartItems->sum(function ($item) {
                return $item->quantity * ($item->product->discounted_price ?? $item->product->price);
            }),
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/cart",
     *     summary="Add item to cart",
     *     tags={"Cart"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, ar)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"})
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"product_id", "quantity"},
     *
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="quantity", type="integer", minimum=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Item added to cart successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/CartItem")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Product out of stock or validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($product->quantity < $request->quantity) {
            return response()->json([
                'message' => __('api.cart.out_of_stock'),
            ], 422);
        }

        $existingItem = Cart::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $request->quantity,
            ]);
            $cartItem = $existingItem;
        } else {
            $cartItem = Cart::create([
                'user_id' => $request->user()->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        return new CartResource($cartItem->load('product'));
    }

    /**
     * @OA\Put(
     *     path="/api/cart/{id}",
     *     summary="Update cart item quantity",
     *     tags={"Cart"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cart Item ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, ar)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"})
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"quantity"},
     *
     *             @OA\Property(property="quantity", type="integer", minimum=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cart item updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/CartItem")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Cart item not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Product out of stock or validation error"
     *     )
     * )
     */
    public function update(Request $request, Product $product)
    {
        $cart = Cart::where('user_id', $request->user()->id)->where('product_id', $product->id)->firstOrFail();
        $this->authorize('update', $cart);
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($cart->product->quantity < $request->quantity) {
            return response()->json([
                'message' => __('api.cart.out_of_stock'),
            ], 422);
        }

        $cart->update(['quantity' => $request->quantity]);

        return new CartResource($cart->load('product'));
    }

    /**
     * @OA\Delete(
     *     path="/api/cart/{id}",
     *     summary="Remove item from cart",
     *     tags={"Cart"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cart Item ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Cart item removed successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart item not found"
     *     )
     * )
     */
    public function destroy(Product $product)
    {
        $cart = Cart::where('user_id', auth()->id())->where('product_id', $product->id)->firstOrFail();
        $this->authorize('delete', $cart);

        $cart->delete();

        return response()->json([
            'message' => __('api.cart.deleted'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/cart/clear",
     *     summary="Clear all items from cart",
     *     tags={"Cart"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=204,
     *         description="Cart cleared successfully"
     *     )
     * )
     */
    public function clear(Request $request)
    {
        $request->user()->cart()->delete();

        return response()->json([
            'message' => __('api.cart.cleared'),
        ]);
    }
}
