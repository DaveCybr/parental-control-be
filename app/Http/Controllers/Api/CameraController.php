<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Capture;
use App\Models\User;
use App\Models\FamilyMember;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraController extends Controller
{
    /**
     * Simpan hasil foto/video untuk child tertentu
     */
    public function store(Request $request)
    {
        $request->validate([
            'child_id' => 'required|exists:users,id',
            'type'     => 'required|in:photo,video',
            'file'     => 'required|file|mimes:jpg,jpeg,png,mp4,mov,avi|max:51200'
        ]);

        $parentId = $request->user()->id;
        $childId  = $request->child_id;

        // Cek hubungan parent-child
        if (!$this->verifyParentChildRelationship($parentId, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Simpan file
        $path = $request->file('file')->store("captures/{$childId}", 'public');

        $capture = Capture::create([
            'user_id'   => $childId, // disimpan atas nama child
            'type'      => $request->type,
            'file_path' => $path,
            'file_url'  => Storage::url($path),
            'file_size' => $request->file('file')->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Capture saved successfully',
            'data'    => $capture
        ]);
    }

    /**
     * List semua capture milik child (hanya bisa dilihat parent)
     */
    public function listByChild($childId, Request $request)
    {
        $parentId = $request->user()->id;

        $captures = Capture::where('user_id', $childId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($capture) use ($childId) {
                return array_merge(
                    $capture->toArray(),
                    [
                        'file_url'   => Storage::url($capture->file_path),
                        'stream_url' => $capture->type === 'video'
                            ? url("api/camera/child/{$childId}/{$capture->id}/stream")
                            : null,
                    ]
                );
            });

        if ($captures->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No captures found'
            ], 404);
        }

        $child = User::find($childId);

        return response()->json([
            'success' => true,
            'child'   => [
                'id'   => $child->id,
                'name' => $child->name,
            ],
            'captures' => $captures
        ]);
    }

    /**
     * Detail capture milik child (hanya bisa dilihat parent)
     */
    public function show($childId, $captureId, Request $request)
    {
        $parentId = $request->user()->id;

        if (!$this->verifyParentChildRelationship($parentId, $childId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $capture = Capture::where('user_id', $childId)
            ->where('id', $captureId)
            ->first();

        if (!$capture) {
            return response()->json([
                'success' => false,
                'message' => 'Capture not found'
            ], 404);
        }

        $child = User::find($childId);

        return response()->json([
            'success' => true,
            'child'   => [
                'id'   => $child->id,
                'name' => $child->name,
            ],
            'capture' => array_merge(
                $capture->toArray(),
                [
                    'file_url'   => Storage::url($capture->file_path),
                    'stream_url' => $capture->type === 'video'
                        ? url("api/camera/child/{$childId}/{$capture->id}/stream")
                        : null,
                ]
            )
        ]);
    }

    public function stream($childId, $captureId, Request $request)
    {
        // pastikan parent-child relationship
        $parentId = $request->user()->id;
        if (!$this->verifyParentChildRelationship($parentId, $childId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $capture = Capture::where('user_id', $childId)
            ->where('id', $captureId)
            ->firstOrFail();

        $path = storage_path('app/public/' . $capture->file_path);

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->file($path, [
            'Content-Type' => $capture->type === 'video' ? 'video/mp4' : 'application/octet-stream',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Verifikasi hubungan parent-child (sama seperti LocationController)
     */
    private function verifyParentChildRelationship($parentId, $childId): bool
    {
        $childMember = FamilyMember::where('user_id', $childId)
            ->where('role', 'child')
            ->first();

        if (!$childMember) return false;

        $parentMember = FamilyMember::where('user_id', $parentId)
            ->where('family_id', $childMember->family_id)
            ->where('role', 'parent')
            ->first();

        return $parentMember !== null;
    }
}
