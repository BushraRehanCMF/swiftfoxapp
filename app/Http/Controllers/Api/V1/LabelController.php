<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LabelResource;
use App\Models\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LabelController extends Controller
{
    /**
     * List all labels for the account.
     */
    public function index(): AnonymousResourceCollection
    {
        $labels = Label::orderBy('name', 'asc')->get();

        return LabelResource::collection($labels);
    }

    /**
     * Create a new label.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $validated['account_id'] = $request->user()->account_id;

        $label = Label::create($validated);

        return response()->json([
            'data' => new LabelResource($label),
            'message' => 'Label created successfully.',
        ], 201);
    }

    /**
     * Get a single label.
     */
    public function show(Label $label): LabelResource
    {
        return new LabelResource($label);
    }

    /**
     * Update a label.
     */
    public function update(Request $request, Label $label): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $label->update($validated);

        return response()->json([
            'data' => new LabelResource($label),
            'message' => 'Label updated successfully.',
        ]);
    }

    /**
     * Delete a label.
     */
    public function destroy(Label $label): JsonResponse
    {
        // Detach from all contacts and conversations first
        $label->contacts()->detach();
        $label->conversations()->detach();

        $label->delete();

        return response()->json([
            'message' => 'Label deleted successfully.',
        ]);
    }
}
