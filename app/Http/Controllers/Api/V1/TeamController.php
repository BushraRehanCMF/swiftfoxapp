<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    /**
     * List all team members in the account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $account = $request->user()->account;

        $users = User::where('account_id', $account->id)
            ->orderBy('name', 'asc')
            ->get();

        return UserResource::collection($users);
    }

    /**
     * Invite a new team member.
     */
    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['sometimes', 'in:member,owner'],
        ]);

        $account = $request->user()->account;
        $role = $validated['role'] ?? 'member';

        $user = $this->teamService->inviteUser(
            $account,
            $validated['name'],
            $validated['email'],
            $role
        );

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Team member invited successfully. They will receive an email with login instructions.',
        ], 201);
    }

    /**
     * Update a team member's role.
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $account = $currentUser->account;

        // Ensure user belongs to the same account
        if ($user->account_id !== $account->id) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        // Cannot change own role
        if ($user->id === $currentUser->id) {
            return response()->json([
                'error' => [
                    'code' => 'CANNOT_CHANGE_OWN_ROLE',
                    'message' => 'You cannot change your own role.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:member,owner'],
        ]);

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'data' => new UserResource($user->fresh()),
            'message' => 'User role updated successfully.',
        ]);
    }

    /**
     * Remove a team member from the account.
     */
    public function remove(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $account = $currentUser->account;

        // Ensure user belongs to the same account
        if ($user->account_id !== $account->id) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        // Cannot remove self
        if ($user->id === $currentUser->id) {
            return response()->json([
                'error' => [
                    'code' => 'CANNOT_REMOVE_SELF',
                    'message' => 'You cannot remove yourself from the team.',
                ],
            ], 422);
        }

        // Check if this is the last owner
        if ($user->isOwner()) {
            $ownerCount = User::where('account_id', $account->id)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                return response()->json([
                    'error' => [
                        'code' => 'LAST_OWNER',
                        'message' => 'Cannot remove the last owner. Transfer ownership first.',
                    ],
                ], 422);
            }
        }

        $this->teamService->removeUser($user);

        return response()->json([
            'message' => 'Team member removed successfully.',
        ]);
    }

    /**
     * Resend invitation email to a team member.
     */
    public function resendInvite(Request $request, User $user): JsonResponse
    {
        $account = $request->user()->account;

        // Ensure user belongs to the same account
        if ($user->account_id !== $account->id) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        // Only resend if not verified
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_VERIFIED',
                    'message' => 'This user has already verified their email.',
                ],
            ], 422);
        }

        $this->teamService->resendInvitation($user);

        return response()->json([
            'message' => 'Invitation email resent successfully.',
        ]);
    }
}
