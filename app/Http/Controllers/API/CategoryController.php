<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Categories",
 *     description="API Endpoints for category management"
 * )
 *
 * @OA\Schema(
 *     schema="Category",
 *     required={"id", "name", "status"},
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(
 *         property="name",
 *         type="object",
 *         @OA\Property(property="en", type="string", example="Category Name"),
 *         @OA\Property(property="ar", type="string", example="اسم التصنيف")
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="object",
 *         @OA\Property(property="en", type="string", example="Category Description"),
 *         @OA\Property(property="ar", type="string", example="وصف التصنيف")
 *     ),
 *     @OA\Property(property="image", type="string", format="uri"),
 *     @OA\Property(property="status", type="string", enum={"active", "not active"}),
 *     @OA\Property(
 *         property="products",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/Product")
 *     ),
 *
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/categories",
     *     summary="List all active categories",
     *     tags={"Categories"},
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
     *         description="List of categories",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Category")
     *             ),
     *
     *             @OA\Property(property="status", type="string", example="success")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $categories = Category::with('products')
            // ->where('status', 'active')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{id}",
     *     summary="Get category details",
     *     tags={"Categories"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
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
     *         description="Category details",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     )
     * )
     */
    public function show(Category $category)
    {
        return new CategoryResource($category->load('products'));
    }

    /**
     * @OA\Post(
     *     path="/api/categories",
     *     summary="Create a new category",
     *     tags={"Categories"},
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
     *             required={"name"},
     *
     *             @OA\Property(
     *                 property="name",
     *                 type="object",
     *                 @OA\Property(property="en", type="string", example="Category Name"),
     *                 @OA\Property(property="ar", type="string", example="اسم التصنيف")
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="object",
     *                 @OA\Property(property="en", type="string", example="Category Description"),
     *                 @OA\Property(property="ar", type="string", example="وصف التصنيف")
     *             ),
     *             @OA\Property(property="image", type="string", format="binary"),
     *             @OA\Property(property="status", type="string", enum={"active", "not active"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Category")
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
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function store(Request $request)
    {
        // dd($request);

        $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'image' => 'required|image|max:2048',
            'status' => 'required|in:active,not active',

        ]);

        $imagePath = $request->file('image')->store('categories', 'public');

        $category = Category::create([
            'name' => [
                'en' => $request->input('name_en'),
                'ar' => $request->input('name_ar'),
            ],
            'description' => [
                'en' => $request->input('description_en'),
                'ar' => $request->input('description_ar'),
            ],
            'image' => $imagePath,
            'status' => $request->status ?? true,
        ]);

        return new CategoryResource($category);
    }

    /**
     * @OA\Put(
     *     path="/api/categories/{id}",
     *     summary="Update category details",
     *     tags={"Categories"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
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
     *             required={"name"},
     *
     *             @OA\Property(
     *                 property="name",
     *                 type="object",
     *                 @OA\Property(property="en", type="string", example="Updated Category Name"),
     *                 @OA\Property(property="ar", type="string", example="اسم التصنيف المحدث")
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="object",
     *                 @OA\Property(property="en", type="string", example="Updated Category Description"),
     *                 @OA\Property(property="ar", type="string", example="وصف التصنيف المحدث")
     *             ),
     *             @OA\Property(property="image", type="string", format="binary"),
     *             @OA\Property(property="status", type="string", enum={"active", "not active"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Category")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name_en' => 'sometimes|required|string|max:255',
            'name_ar' => 'sometimes|required|string|max:255',
            'description_en' => 'sometimes|required|string',
            'description_ar' => 'sometimes|required|string',
            'image' => 'sometimes|required|image|max:2048',
            'status' => 'sometimes|required|in:active,not active',
        ]);
        // dd($request);

        $data = [
            'name' => [
                'en' => $request->input('name_en') ?? $category->name['en'],
                'ar' => $request->input('name_ar') ?? $category->name['ar'],
            ],
            'description' => [
                'en' => $request->input('description_en') ?? $category->description['en'],
                'ar' => $request->input('description_ar') ?? $category->description['ar'],
            ],
            'status' => $request->status ?? $category->status,
        ];

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return new CategoryResource($category);
    }

    /**
     * @OA\Delete(
     *     path="/api/categories/{id}",
     *     summary="Delete a category",
     *     tags={"Categories"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Category deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function destroy(Category $category)
    {
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'message' => __('api.categories.deleted'),
        ]);
    }
}
