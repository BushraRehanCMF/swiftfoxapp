<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AutomationRule;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    public function __construct(
        protected MessageService $messageService
    ) {}

    /**
     * Process automations for a given trigger on a conversation.
     */
    public function processTrigger(
        string $triggerType,
        Conversation $conversation,
        ?Message $message = null
    ): array {
        $account = $conversation->account;
        $executedActions = [];

        // Get all enabled automation rules for this trigger
        $rules = AutomationRule::where('account_id', $account->id)
            ->where('trigger_type', $triggerType)
            ->where('is_enabled', true)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($rules as $rule) {
            // Check if conditions match
            if ($this->conditionsMatch($rule, $conversation, $message)) {
                $actions = $this->executeActions($rule, $conversation, $message);
                $executedActions[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'actions' => $actions,
                ];
            }
        }

        return $executedActions;
    }

    /**
     * Check if automation rule conditions match.
     */
    protected function conditionsMatch(
        AutomationRule $rule,
        Conversation $conversation,
        ?Message $message = null
    ): bool {
        $conditions = $rule->conditions;

        // If no conditions, rule always matches
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $conversation, $message)) {
                return false; // All conditions must match
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(
        array $condition,
        Conversation $conversation,
        ?Message $message = null
    ): bool {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? '';

        // Get the actual value based on the field
        $actualValue = $this->getFieldValue($field, $conversation, $message);

        return match ($operator) {
            'equals' => strtolower($actualValue) === strtolower($expectedValue),
            'not_equals' => strtolower($actualValue) !== strtolower($expectedValue),
            'contains' => str_contains(strtolower($actualValue), strtolower($expectedValue)),
            'not_contains' => !str_contains(strtolower($actualValue), strtolower($expectedValue)),
            'starts_with' => str_starts_with(strtolower($actualValue), strtolower($expectedValue)),
            'ends_with' => str_ends_with(strtolower($actualValue), strtolower($expectedValue)),
            default => false,
        };
    }

    /**
     * Get the value of a field for condition evaluation.
     */
    protected function getFieldValue(
        string $field,
        Conversation $conversation,
        ?Message $message = null
    ): string {
        return match ($field) {
            'message_content', 'message.content' => $message?->content ?? '',
            'contact_name', 'contact.name' => $conversation->contact?->name ?? '',
            'contact_phone', 'contact.phone_number' => $conversation->contact?->phone_number ?? '',
            'conversation_status', 'conversation.status' => $conversation->status ?? '',
            default => '',
        };
    }

    /**
     * Execute all actions for an automation rule.
     */
    protected function executeActions(
        AutomationRule $rule,
        Conversation $conversation,
        ?Message $message = null
    ): array {
        $executed = [];
        $actions = $rule->actions ?? [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? '';

            try {
                $result = $this->executeAction($type, $value, $conversation, $message);
                $executed[] = [
                    'type' => $type,
                    'success' => true,
                    'result' => $result,
                ];
            } catch (\Exception $e) {
                Log::error('Automation action failed', [
                    'rule_id' => $rule->id,
                    'action_type' => $type,
                    'error' => $e->getMessage(),
                ]);
                $executed[] = [
                    'type' => $type,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $executed;
    }

    /**
     * Execute a single action.
     */
    protected function executeAction(
        string $type,
        mixed $value,
        Conversation $conversation,
        ?Message $message = null
    ): ?string {
        return match ($type) {
            AutomationRule::ACTION_ASSIGN_USER => $this->assignUser($conversation, $value),
            AutomationRule::ACTION_ADD_LABEL => $this->addLabel($conversation, $value),
            AutomationRule::ACTION_SEND_REPLY => $this->sendReply($conversation, $value),
            default => null,
        };
    }

    /**
     * Assign conversation to a user.
     */
    protected function assignUser(Conversation $conversation, mixed $userId): string
    {
        $conversation->update(['assigned_user_id' => $userId]);
        return "Assigned to user {$userId}";
    }

    /**
     * Add a label to the conversation.
     */
    protected function addLabel(Conversation $conversation, mixed $labelId): string
    {
        // Only attach if not already attached
        if (!$conversation->labels()->where('labels.id', $labelId)->exists()) {
            $conversation->labels()->attach($labelId);
        }
        return "Added label {$labelId}";
    }

    /**
     * Send an auto-reply message.
     *
     * Note: This counts towards conversation limits during trial.
     */
    protected function sendReply(Conversation $conversation, string $content): string
    {
        $account = $conversation->account;

        // Check if account can send messages (trial limits)
        if (!$account->canSendMessages()) {
            throw new \Exception('Account has reached message limit');
        }

        // Use MessageService to send the reply
        $this->messageService->sendMessage($conversation, $content);

        return "Sent reply: {$content}";
    }

    /**
     * Check if current time is within business hours.
     */
    public function isWithinBusinessHours(Account $account): bool
    {
        $timezone = $account->timezone ?? 'UTC';
        $now = now()->setTimezone($timezone);
        $dayOfWeek = $now->dayOfWeek; // 0 = Sunday, 6 = Saturday

        $businessHour = $account->businessHours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_enabled', true)
            ->first();

        if (!$businessHour) {
            return false;
        }

        $currentTime = $now->format('H:i:s');
        return $currentTime >= $businessHour->start_time
            && $currentTime <= $businessHour->end_time;
    }
}
