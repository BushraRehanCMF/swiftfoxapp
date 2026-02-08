<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        try {
            $conversation = $this->message->conversation;
            $contact = $conversation->contact;

            // Send via WhatsApp API
            $result = $whatsAppService->sendTextMessage(
                $contact->phone_number,
                $this->message->content
            );

            // Update message with WhatsApp message ID
            $this->message->update([
                'whatsapp_message_id' => $result['message_id'] ?? null,
                'status' => Message::STATUS_SENT,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp message', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
            ]);

            $this->message->update([
                'status' => Message::STATUS_FAILED,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp message job failed permanently', [
            'message_id' => $this->message->id,
            'error' => $exception->getMessage(),
        ]);

        $this->message->update([
            'status' => Message::STATUS_FAILED,
        ]);
    }
}
