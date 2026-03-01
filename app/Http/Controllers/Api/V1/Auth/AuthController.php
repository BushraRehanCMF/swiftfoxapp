<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuthService;
use App\Services\EmailVerificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected EmailVerificationService $emailVerificationService
    ) {}

    /**
     * Register a new account and user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        // Send email verification link
        $this->emailVerificationService->sendVerificationEmail($result['user']);

        return response()->json([
            'message' => 'Account created successfully. Please check your email to verify your account before signing in.',
            'data' => [
                'email' => $result['user']->email,
            ],
        ], 201);
    }

    /**
     * Log in an existing user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $ipAddress = $request->ip() ?? '0.0.0.0';

        // Check if account is locked due to failed attempts
        if (LoginAttempt::isLocked($request->email, $ipAddress)) {
            throw ValidationException::withMessages([
                'email' => ['Too many failed login attempts. Please try again in 15 minutes.'],
            ]);
        }

        // Authenticate
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            // Record failed attempt
            LoginAttempt::recordFailedAttempt($request->email, $ipAddress);
            throw $e;
        }

        $user = Auth::user();

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email address. Check your inbox for a verification link.'],
            ]);
        }

        // Clear failed login attempts on successful login
        LoginAttempt::clearAttempts($request->email, $ipAddress);

        // Check if account is disabled (for non-super admins)
        if ($user->hasAccount() && $user->account->subscription_status === 'expired') {
            // Still allow login but in read-only mode
        }

        // Revoke previous tokens and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully.',
            'data' => [
                'user' => new UserResource($user->load('account')),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Log out the current user.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('account');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    /**
     * Reset password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }

    /**
     * Verify email address from signed link.
     */
    public function verifyEmail(Request $request, User $user): JsonResponse
    {
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $this->emailVerificationService->verifyEmail($user);

        return response()->json([
            'message' => 'Email verified successfully. You can now log in.',
        ]);
    }
}
