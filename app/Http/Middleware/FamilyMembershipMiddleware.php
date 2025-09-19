<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\FamilyMember;

class FamilyMembershipMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        $familyMember = FamilyMember::where('user_id', $user->id)->first();

        if (!$familyMember) {
            return response()->json([
                'success' => false,
                'message' => 'User must be part of a family to access this resource'
            ], 403);
        }

        // Attach family info to request
        $request->merge(['family_member' => $familyMember]);

        return $next($request);
    }
}
