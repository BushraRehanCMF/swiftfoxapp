<?php

namespace App\Http\Resources;

use App\Models\AutomationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRuleResource extends JsonResource
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
            'name' => $this->name,
            'trigger_type' => $this->trigger_type,
            'trigger_label' => AutomationRule::TRIGGER_TYPES[$this->trigger_type] ?? $this->trigger_type,
            'conditions' => $this->conditions,
            'actions' => $this->actions,
            'is_enabled' => $this->is_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
