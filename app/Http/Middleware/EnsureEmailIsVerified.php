<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // if (! Auth::check()) {
        //     return $this->unauthorized(__('api.auth.user_not_found'));
        // }
        $user = User::where('email', $request->email)->firstOrFail();

        if (! $user->hasVerifiedEmail()) {

            // Generate the verification URL
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->id,
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            return $this->validationErrorWithUrl(
                __('api.auth.email_not_verified'),
                $verificationUrl,
                401
            );
        }

        return $next($request);
    }
}
