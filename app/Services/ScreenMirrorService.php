<?php

namespace App\Services;

use App\Models\ScreenSession;
use App\Models\FamilyMember;
use Illuminate\Support\Str;
use App\Events\ScreenSessionStarted;
use App\Events\ScreenSessionEnded;

class ScreenMirrorService
{
    public function startSession($parentId, $childId)
    {
        // Verify parent-child relationship
        if (!$this->verifyRelationship($parentId, $childId)) {
            throw new \Exception('Invalid parent-child relationship');
        }

        // End any existing active sessions for this child
        $this->endActiveSessionsForChild($childId);

        // Create new session
        $session = ScreenSession::create([
            'child_user_id' => $childId,
            'parent_user_id' => $parentId,
            'session_token' => Str::random(64),
            'started_at' => now()
        ]);

        // Broadcast to child device
        broadcast(new ScreenSessionStarted($session));

        return $session;
    }

    public function endSession($sessionToken, $parentId)
    {
        $session = ScreenSession::where('session_token', $sessionToken)
            ->where('parent_user_id', $parentId)
            ->where('is_active', true)
            ->first();

        if (!$session) {
            throw new \Exception('Session not found or already ended');
        }

        $session->update([
            'is_active' => false,
            'ended_at' => now()
        ]);

        // Broadcast session end to child
        broadcast(new ScreenSessionEnded($session->child_user_id, $sessionToken));

        return $session;
    }

    private function endActiveSessionsForChild($childId)
    {
        ScreenSession::where('child_user_id', $childId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'ended_at' => now()
            ]);
    }

    private function verifyRelationship($parentId, $childId)
    {
        $parentMember = FamilyMember::where('user_id', $parentId)
            ->where('role', 'parent')
            ->first();

        if (!$parentMember) return false;

        $childMember = FamilyMember::where('user_id', $childId)
            ->where('family_id', $parentMember->family_id)
            ->where('role', 'child')
            ->first();

        return $childMember !== null;
    }
}
