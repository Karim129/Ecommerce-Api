<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Google\Client as GoogleClient;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication"
 * )
 *
 * @OA\Schema(
 *     schema="GoogleLoginRequest",
 *     required={"token"},
 *
 *     @OA\Property(property="token", type="string", description="Google OAuth token")
 * )
 *
 * @OA\Schema(
 *     schema="AuthResponse",
 *
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="user", ref="#/components/schemas/User"),
 *     @OA\Property(property="token", type="string")
 * )
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
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
     *             required={"name", "email", "password", "password_confirmation"},
     *
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="secret123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="token", type="string")
     *         )
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
    public function register(RegisterRequest $request)
    {

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('users', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'image' => $imagePath,
        ]);

        $user->assignRole('customer');
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success(
            [
                'user' => new UserResource($user),
                'token' => $token,
            ],
            __('api.auth.registered'),
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Authenticate user and generate token",
     *     tags={"Authentication"},
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
     *             required={"email", "password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function login(Request $request)
    {

        if (! Auth::attempt($request->only('email', 'password'))) {
            if (! $user = User::where('email', $request->email)->first()) {
                return $this->error(
                    __('api.auth.invalid_credentials'),
                    401,
                    [
                        'email' => [
                            __('api.auth.user_not_found'),

                        ],
                    ]

                );
            }
            if (! Hash::check($request->password, $user->password)) {
                $errors = [
                    'password' => [
                        __('api.auth.password'),
                    ],
                ];
            }

            return $this->error(
                __('api.auth.invalid_credentials'),
                401,
                $errors
            );
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success(
            [
                'user' => new UserResource($user),
                'token' => $token,
            ],
            __('api.auth.logged_in'),
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/api/login/google",
     *     summary="Authenticate user with Google",
     *     tags={"Authentication"},
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
     *             required={"token"},
     *
     *             @OA\Property(property="token", type="string", description="Google OAuth token")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid Google token",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $client = new GoogleClient([
                'client_id' => config('services.google.client_id'),
                'application_name' => config('app.name'),
            ]);

            // For debugging - log the token and client configuration
            \Log::debug('Google Client Configuration:', [
                'client_id' => config('services.google.client_id'),
                'token_length' => strlen($request->token),
            ]);

            try {
                // Verify the ID token
                $payload = $client->verifyIdToken($request->token);

                if (! $payload) {
                    \Log::error('Google token payload is null');

                    return $this->error('Invalid token payload - null response', 401);
                }
            } catch (\Google\Service\Exception $e) {
                \Log::error('Google Service Exception: '.$e->getMessage());

                return $this->error('Google Service Error: '.$e->getMessage(), 401);
            } catch (\Exception $e) {
                \Log::error('Token verification failed: '.$e->getMessage());

                return $this->error('Token verification failed: '.$e->getMessage(), 401);
            }

            // Log successful payload for debugging
            \Log::info('Google payload received:', [
                'sub' => $payload['sub'] ?? null,
                'email' => $payload['email'] ?? null,
                'name' => $payload['name'] ?? null,
            ]);

            $user = User::where('google_id', $payload['sub'])->first();

            if (! $user) {
                $user = User::where('email', $payload['email'])->first();

                if ($user) {
                    // Link existing account with Google
                    $user->google_id = $payload['sub'];
                    $user->image = $payload['picture'] ?? null;
                    $user->email_verified_at = now();
                    $user->save();
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $payload['name'],
                        'email' => $payload['email'],
                        'google_id' => $payload['sub'],
                        'image' => $payload['picture'] ?? null,
                        'password' => Hash::make(Str::random(24)),
                        'email_verified_at' => now(),
                    ]);

                    $user->assignRole('customer');
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => new UserResource($user),
            ], 'Successfully logged in with Google');
        } catch (\Exception $e) {
            \Log::error('Google login failed: '.$e->getMessage());

            return $this->error('Failed to authenticate with Google: '.$e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user (Revoke token)",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
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
    public function logout(Request $request)
    {

        $request->user()->tokens()->delete();

        return $this->loggedOut(__('api.auth.logged_out'), 200);
    }

    /**
     * @OA\Get(
     *     path="/api/email/verify/{id}/{hash}",
     *     summary="Verify email address",
     *     tags={"Authentication"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="hash",
     *         in="path",
     *         description="Email verification hash",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid verification link",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
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
    public function verifyEmail(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        if (! hash_equals(
            (string) $request->route('hash'),
            sha1($user->getEmailForVerification())
        )) {
            return response()->json([
                'message' => __('api.auth.verification.invalid_link'),
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('api.auth.verification.already_verified'),
            ], 400);
        }

        if ($user->markEmailAsVerified()) {
            if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
                event(new Verified($user));
            }
        }

        return response()->json([
            'message' => __('api.auth.verification.verified'),
            // 'user' => new UserResource($user)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/email/resend",
     *     summary="Resend email verification link",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Verification link sent successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
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
    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('api.auth.verification.already_verified'),
            ], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => __('api.auth.verification.sent'),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user",
     *     summary="Get authenticated user details",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="User details retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
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
    public function user(Request $request)
    {
        return response()->json(new UserResource($request->user()));
    }
}
