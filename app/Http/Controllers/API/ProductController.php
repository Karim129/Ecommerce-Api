<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Products",
 *     description="API Endpoints for product management"
 * )
 *
 * @OA\Schema(
 *     schema="Product",
 *     required={"id", "name", "price", "quantity", "status"},
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(
 *         property="name",
 *         type="object",
 *         @OA\Property(property="en", type="string", example="Product Name"),
 *         @OA\Property(property="ar", type="string", example="اسم المنتج")
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="object",
 *         @OA\Property(property="en", type="string", example="Product Description"),
 *         @OA\Property(property="ar", type="string", example="وصف المنتج")
 *     ),
 *     @OA\Property(property="images", type="array", @OA\Items(type="string", format="uri")),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="discounted_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="quantity", type="integer"),
 *     @OA\Property(property="status", type="boolean"),
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/Category")
 *     ),
 *
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="List all active products",
     *     tags={"Products"},
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
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter products by category ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of products",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="status", type="string", example="success")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Product::with('categories');
        // ->where('status', 'active');

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }
        $products = $query->paginate(12);

        return ProductResource::collection($products);
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Get product details",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
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
     *     @OA\Response(
     *         response=200,
     *         description="Product details",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function show(Product $product)
    {
        return new ProductResource($product->load('categories'));
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     summary="Create a new product",
     *     tags={"Products"},
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
     *             required={"name", "price", "quantity", "category_ids"},
     *
     *             @OA\Property(property="name", type="object",
     *                 @OA\Property(property="en", type="string", example="Product Name"),
     *                 @OA\Property(property="ar", type="string", example="اسم المنتج")
     *             ),
     *             @OA\Property(property="description", type="object",
     *                 @OA\Property(property="en", type="string", example="Product Description"),
     *                 @OA\Property(property="ar", type="string", example="وصف المنتج")
     *             ),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary")),
     *             @OA\Property(property="price", type="number", format="float", example=99.99),
     *             @OA\Property(property="discounted_price", type="number", format="float", example=79.99),
     *             @OA\Property(property="quantity", type="integer", example=100),
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="category_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Handle categories input
        $categories = $request->categories;
        if (is_string($categories)) {
            $categories = json_decode($categories, true);
        }

        $request->merge(['categories' => $categories]);
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('products', 'public');
            }
            // dd($request->images);
        }
        $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'images.*' => 'required|image|max:2048',
            'images' => 'required|array|min:1',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0|lt:price',
            'quantity' => 'required|integer|min:0',
            'status' => 'required|string|in:active,not active',
            'categories' => 'required|array|min:1',
            'categories.*' => 'required|integer|exists:categories,id',
        ]);

        $images = [];
        foreach ($request->file('images') as $image) {
            $images[] = $image->store('products', 'public');
        }

        $product = Product::create([
            'name' => [
                'en' => $request->input('name_en'),
                'ar' => $request->input('name_ar'),
            ],
            'description' => [
                'en' => $request->input('description_en'),
                'ar' => $request->input('description_ar'),
            ],
            'images' => $images,
            'price' => $request->price,
            'discounted_price' => $request->discounted_price,
            'quantity' => $request->quantity,
            'status' => $request->status ?? 'active',
        ]);

        $product->categories()->attach($categories);

        return new ProductResource($product->load('categories'));
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     summary="Update product details",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
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
     *             required={"name", "price", "quantity", "category_ids"},
     *
     *             @OA\Property(property="name", type="object",
     *                 @OA\Property(property="en", type="string", example="Updated Product Name"),
     *                 @OA\Property(property="ar", type="string", example="اسم المنتج المحدث")
     *             ),
     *             @OA\Property(property="description", type="object",
     *                 @OA\Property(property="en", type="string", example="Updated Product Description"),
     *                 @OA\Property(property="ar", type="string", example="وصف المنتج المحدث")
     *             ),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary")),
     *             @OA\Property(property="remove_images", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="price", type="number", format="float", example=89.99),
     *             @OA\Property(property="discounted_price", type="number", format="float", example=69.99),
     *             @OA\Property(property="quantity", type="integer", example=150),
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="category_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function update(UpdateProductRequest $request, Product $product)
    {

        $request->validated();

        $data = [
            'name' => [
                'en' => $request->input('name_en'),
                'ar' => $request->input('name_ar'),
            ],
            'description' => [
                'en' => $request->input('description_en'),
                'ar' => $request->input('description_ar'),
            ],
            'price' => $request->input('price'),
            'discounted_price' => $request->input('discounted_price'),
            'quantity' => $request->input('quantity'),
            'status' => $request->input('status', $product->status),
        ];

        // Handle image uploads
        if ($request->hasFile('images')) {
            $images = [];
            $uploadedFiles = $request->file('images');

            // Delete old images if new ones are being uploaded
            foreach ($product->images as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }

            foreach ($uploadedFiles as $image) {
                if ($image->isValid()) {
                    $path = $image->store('products', 'public');
                    $images[] = $path;
                }
            }

            $data['images'] = $images;
        }

        $product->update($data);
        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        }

        return new ProductResource($product->load('categories'));
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Delete a product",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Product deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function destroy(Product $product)
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image);
        }

        $product->delete();

        return response()->json([
            'message' => __('api.products.deleted'),
        ]);
    }
}
