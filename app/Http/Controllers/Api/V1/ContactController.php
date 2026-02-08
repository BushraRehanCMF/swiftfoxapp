<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncLabelsRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ConversationResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    /**
     * List all contacts for the account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::with('labels')
            ->orderBy('name', 'asc');

        // Search by name or phone
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Filter by label
        if ($request->has('label_id')) {
            $query->whereHas('labels', function ($q) use ($request) {
                $q->where('labels.id', $request->label_id);
            });
        }

        $contacts = $query->paginate(25);

        return ContactResource::collection($contacts);
    }

    /**
     * Get a single contact.
     */
    public function show(Contact $contact): ContactResource
    {
        $contact->load('labels');

        return new ContactResource($contact);
    }

    /**
     * Update a contact.
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $contact->update($validated);

        return response()->json([
            'data' => new ContactResource($contact->fresh('labels')),
            'message' => 'Contact updated successfully.',
        ]);
    }

    /**
     * Sync labels for the contact.
     */
    public function syncLabels(SyncLabelsRequest $request, Contact $contact): JsonResponse
    {
        $contact->labels()->sync($request->validated('label_ids'));

        return response()->json([
            'data' => new ContactResource($contact->fresh('labels')),
            'message' => 'Labels updated successfully.',
        ]);
    }

    /**
     * Get all conversations for a contact.
     */
    public function conversations(Contact $contact): AnonymousResourceCollection
    {
        $conversations = $contact->conversations()
            ->with(['assignedUser', 'labels', 'lastMessage'])
            ->orderBy('last_message_at', 'desc')
            ->paginate(25);

        return ConversationResource::collection($conversations);
    }
}
