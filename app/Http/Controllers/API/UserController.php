<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for user management"
 * )
 *
 * @OA\Schema(
 *     schema="StoreUserRequest",
 *     required={"name", "email", "password", "password_confirmation", "role"},
 *
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255),
 *     @OA\Property(property="password", type="string", format="password", minLength=8),
 *     @OA\Property(property="password_confirmation", type="string", format="password"),
 *     @OA\Property(property="role", type="string", enum={"admin", "customer"}),
 *     @OA\Property(property="image", type="string", format="binary")
 * )
 *
 * @OA\Schema(
 *     schema="UpdateUserRequest",
 *
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255),
 *     @OA\Property(property="password", type="string", format="password", minLength=8),
 *     @OA\Property(property="role", type="string", enum={"admin", "customer"}),
 *     @OA\Property(property="image", type="string", format="binary"),
 *     @OA\Property(property="is_admin", type="boolean")
 * )
 */
class UserController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="List all users",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response="200", description="List of users"),
     *     @OA\Response(response="403", description="Unauthorized")
     * )
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('roles')->paginate(25);

        return $this->success(UserResource::collection($users), 'List of users');
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Create a new user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/StoreUserRequest")
     *     ),
     *
     *     @OA\Response(response="201", description="User created successfully"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="422", description="Validation error")
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('users', 'public');
        }

        $data['password'] = Hash::make($data['password']);
        if ($data['role'] == 'admin') {
            $data['is_admin'] = true;
        }
        $data['email_verified_at'] = now();
        $user = User::create($data);
        $user->assignRole($data['role']);

        return $this->success($user, 'User created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{user}",
     *     summary="Get user details",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="User details"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="404", description="User not found")
     * )
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->success(new UserResource($user->load('roles')), 'User details');
    }

    /**
     * @OA\Put(
     *     path="/api/users/{user}",
     *     summary="Update user details",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/UpdateUserRequest")
     *     ),
     *
     *     @OA\Response(response="200", description="User updated successfully"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="404", description="User not found"),
     *     @OA\Response(response="422", description="Validation error")
     * )
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        // dd($request->name);
        if ($request->hasFile('image')) {
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $data['image'] = $request->file('image')->store('users', 'public');
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }
        $user->update([
            'name' => $data['name'],
            'image' => $data['image'],
            'password' => $data['password'],
            'is_admin' => $data['is_admin'],
            'updated_at' => now(),

        ]);

        return $this->success(new UserResource($user), 'User updated successfully', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{user}",
     *     summary="Delete a user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response="200", description="User deleted successfully"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="404", description="User not found")
     * )
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->image) {
            Storage::disk('public')->delete($user->image);
        }

        $user->delete();

        return $this->success(null, 'User deleted successfully', 200);
    }
}
