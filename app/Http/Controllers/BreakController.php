<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Break;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BreakController extends Controller
{
    /**
     * Display a listing of available breaks.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->current_team_id;

        $breaks = Break::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNull('redeemed_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $breaks,
        ]);
    }

    /**
     * Display a listing of redeemed breaks.
     */
    public function redeemed(): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->current_team_id;

        $breaks = Break::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNotNull('redeemed_at')
            ->orderBy('redeemed_at', 'desc')
            ->get();

        return response()->json([
            'data' => $breaks,
        ]);
    }

    /**
     * Store a newly created break.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'points_cost' => 'required|integer|min:1',
            'duration_minutes' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $organizationId = $user->current_team_id;

        $break = new Break();
        $break->description = $validated['description'];
        $break->points_cost = $validated['points_cost'];
        $break->duration_minutes = $validated['duration_minutes'];
        $break->user_id = $user->id;
        $break->organization_id = $organizationId;
        $break->save();

        return response()->json([
            'data' => $break,
        ], 201);
    }

    /**
     * Redeem a break.
     */
    public function redeem(string $id): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->current_team_id;

        $break = Break::where('id', $id)
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNull('redeemed_at')
            ->firstOrFail();

        // Calculate total available points
        $totalPoints = TimeEntry::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNotNull('end')
            ->sum('points');

        // Calculate points already spent on breaks
        $spentPoints = Break::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNotNull('redeemed_at')
            ->sum('points_cost');

        $availablePoints = $totalPoints - $spentPoints;

        if ($availablePoints < $break->points_cost) {
            throw ValidationException::withMessages([
                'points' => ['Not enough points to redeem this break.'],
            ]);
        }

        DB::transaction(function () use ($break) {
            $break->redeemed_at = now();
            $break->save();
        });

        return response()->json([
            'data' => $break,
            'available_points' => $availablePoints - $break->points_cost,
        ]);
    }

    /**
     * Get the total available points.
     */
    public function points(): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->current_team_id;

        // Calculate total earned points
        $totalPoints = TimeEntry::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNotNull('end')
            ->sum('points');

        // Calculate points already spent on breaks
        $spentPoints = Break::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNotNull('redeemed_at')
            ->sum('points_cost');

        $availablePoints = $totalPoints - $spentPoints;

        return response()->json([
            'total_points' => $totalPoints,
            'spent_points' => $spentPoints,
            'available_points' => $availablePoints,
        ]);
    }
}
