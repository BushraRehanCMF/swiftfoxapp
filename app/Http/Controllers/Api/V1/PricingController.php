<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    /**
     * Get available pricing plans.
     */
    public function index(): JsonResponse
    {
        $plans = config('swiftfox.stripe.plans');

        // Transform plans into API response format
        $formattedPlans = collect($plans)->map(function ($plan, $key) {
            return [
                'id' => $key,
                'name' => $plan['name'],
                'price_id' => $plan['price_id'],
                'price' => $plan['price'],
                'currency' => $plan['currency'] ?? 'USD',
                'description' => $plan['description'],
                'conversation_limit' => $plan['conversation_limit'],
                'popular' => $plan['popular'] ?? false,
                'features' => $plan['features'],
            ];
        })->values();

        return response()->json([
            'data' => $formattedPlans,
        ]);
    }
}
