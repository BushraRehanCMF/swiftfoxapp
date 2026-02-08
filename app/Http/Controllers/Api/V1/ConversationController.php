<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignConversationRequest;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Http\Requests\Api\V1\SyncLabelsRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected MessageService $messageService
    ) {}

    /**
     * List all conversations for the account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Conversation::with(['contact', 'assignedUser', 'labels', 'lastMessage'])
            ->orderBy('last_message_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assigned user
        if ($request->has('assigned_user_id')) {
            if ($request->assigned_user_id === 'unassigned') {
                $query->whereNull('assigned_user_id');
            } else {
                $query->where('assigned_user_id', $request->assigned_user_id);
            }
        }

        // Filter by label
        if ($request->has('label_id')) {
            $query->whereHas('labels', function ($q) use ($request) {
                $q->where('labels.id', $request->label_id);
            });
        }

        // Search by contact name or phone
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $conversations = $query->paginate(25);

        return ConversationResource::collection($conversations);
    }

    /**
     * Get a single conversation with messages.
     */
    public function show(Conversation $conversation): ConversationResource
    {
        $conversation->load(['contact', 'assignedUser', 'labels', 'messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }]);

        return new ConversationResource($conversation);
    }

    /**
     * Get messages for a conversation (paginated).
     */
    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return MessageResource::collection($messages);
    }

    /**
     * Send a message in a conversation.
     * Note: The can.send middleware already validates sending permissions.
     */
    public function sendMessage(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        // Send the message
        $message = $this->messageService->sendMessage(
            $conversation,
            $request->validated('content')
        );

        return response()->json([
            'data' => new MessageResource($message),
            'message' => 'Message sent successfully.',
        ], 201);
    }

    /**
     * Assign a user to the conversation.
     */
    public function assign(AssignConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $userId = $request->validated('user_id');
        $user = null;

        if ($userId) {
            // Verify user belongs to the same account
            $user = User::where('id', $userId)
                ->where('account_id', $request->user()->account_id)
                ->first();

            if (!$user) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_USER',
                        'message' => 'The selected user does not belong to your account.',
                    ],
                ], 422);
            }
        }

        $this->conversationService->assignUser($conversation, $user);

        return response()->json([
            'data' => new ConversationResource($conversation->fresh(['contact', 'assignedUser', 'labels'])),
            'message' => $user ? 'Conversation assigned successfully.' : 'Conversation unassigned successfully.',
        ]);
    }

    /**
     * Sync labels for the conversation.
     */
    public function syncLabels(SyncLabelsRequest $request, Conversation $conversation): JsonResponse
    {
        $this->conversationService->syncLabels($conversation, $request->validated('label_ids'));

        return response()->json([
            'data' => new ConversationResource($conversation->fresh(['contact', 'assignedUser', 'labels'])),
            'message' => 'Labels updated successfully.',
        ]);
    }

    /**
     * Close a conversation.
     */
    public function close(Conversation $conversation): JsonResponse
    {
        $this->conversationService->close($conversation);

        return response()->json([
            'data' => new ConversationResource($conversation->fresh(['contact', 'assignedUser', 'labels'])),
            'message' => 'Conversation closed successfully.',
        ]);
    }

    /**
     * Reopen a conversation.
     */
    public function reopen(Conversation $conversation): JsonResponse
    {
        $this->conversationService->reopen($conversation);

        return response()->json([
            'data' => new ConversationResource($conversation->fresh(['contact', 'assignedUser', 'labels'])),
            'message' => 'Conversation reopened successfully.',
        ]);
    }
}
