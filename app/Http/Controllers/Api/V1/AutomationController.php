<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AutomationRuleResource;
use App\Models\AutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutomationController extends Controller
{
    /**
     * List all automation rules for the account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rules = AutomationRule::orderBy('name', 'asc')->get();

        return AutomationRuleResource::collection($rules);
    }

    /**
     * Create a new automation rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRule($request);
        $validated['account_id'] = $request->user()->account_id;

        $rule = AutomationRule::create($validated);

        return response()->json([
            'data' => new AutomationRuleResource($rule),
            'message' => 'Automation rule created successfully.',
        ], 201);
    }

    /**
     * Get a single automation rule.
     */
    public function show(Request $request, AutomationRule $automation): AutomationRuleResource
    {
        return new AutomationRuleResource($automation);
    }

    /**
     * Update an automation rule.
     */
    public function update(Request $request, AutomationRule $automation): JsonResponse
    {
        $validated = $this->validateRule($request, true);

        $automation->update($validated);

        return response()->json([
            'data' => new AutomationRuleResource($automation->refresh()),
            'message' => 'Automation rule updated successfully.',
        ]);
    }

    /**
     * Delete an automation rule.
     */
    public function destroy(AutomationRule $automation): JsonResponse
    {
        $automation->delete();

        return response()->json([
            'message' => 'Automation rule deleted successfully.',
        ]);
    }

    /**
     * Toggle automation rule enabled status.
     */
    public function toggle(AutomationRule $automation): JsonResponse
    {
        $automation->update([
            'is_enabled' => !$automation->is_enabled,
        ]);

        return response()->json([
            'data' => new AutomationRuleResource($automation->refresh()),
            'message' => $automation->is_enabled
                ? 'Automation rule enabled.'
                : 'Automation rule disabled.',
        ]);
    }

    /**
     * Get available trigger types.
     */
    public function triggers(): JsonResponse
    {
        $triggers = collect(AutomationRule::TRIGGER_TYPES)->map(function ($label, $key) {
            return [
                'value' => $key,
                'label' => $label,
            ];
        })->values();

        return response()->json([
            'data' => $triggers,
        ]);
    }

    /**
     * Get available action types.
     */
    public function actions(): JsonResponse
    {
        $actions = collect(AutomationRule::ACTION_TYPES)->map(function ($label, $key) {
            return [
                'value' => $key,
                'label' => $label,
            ];
        })->values();

        return response()->json([
            'data' => $actions,
        ]);
    }

    /**
     * Validate automation rule request.
     */
    protected function validateRule(Request $request, bool $isUpdate = false): array
    {
        $triggerTypes = array_keys(AutomationRule::TRIGGER_TYPES);
        $actionTypes = array_keys(AutomationRule::ACTION_TYPES);

        $rules = [
            'name' => [($isUpdate ? 'sometimes' : 'required'), 'string', 'max:255'],
            'trigger_type' => [($isUpdate ? 'sometimes' : 'required'), 'string', 'in:' . implode(',', $triggerTypes)],
            'conditions' => ['sometimes', 'nullable', 'array'],
            'conditions.*.field' => ['required_with:conditions', 'string'],
            'conditions.*.operator' => ['required_with:conditions', 'string', 'in:equals,not_equals,contains,not_contains,starts_with,ends_with'],
            'conditions.*.value' => ['required_with:conditions', 'string'],
            'actions' => [($isUpdate ? 'sometimes' : 'required'), 'array', 'min:1'],
            'actions.*.type' => ['required', 'string', 'in:' . implode(',', $actionTypes)],
            'actions.*.value' => ['required'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
