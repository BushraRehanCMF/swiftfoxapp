<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'last_message_at' => $this->last_message_at,
            'conversation_started_at' => $this->conversation_started_at,
            'is_within_messaging_window' => $this->isWithinMessagingWindow(),
            'created_at' => $this->created_at,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'last_message' => new MessageResource($this->whenLoaded('lastMessage')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'unread_count' => $this->when(isset($this->unread_count), $this->unread_count),
        ];
    }
}
