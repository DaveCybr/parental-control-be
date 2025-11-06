<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Screenshot;
use App\Models\CapturedPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AlbumController extends Controller
{
    /**
     * Get combined album (screenshots + captured photos)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAlbum(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'type' => 'nullable|in:all,screenshot,photo',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deviceId = $request->device_id;
            $type = $request->input('type', 'all');
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            $albums = collect();

            // Ambil Screenshots
            if ($type === 'all' || $type === 'screenshot') {
                $screenshotsQuery = Screenshot::where('device_id', $deviceId);

                if ($startDate) {
                    $screenshotsQuery->whereDate('timestamp', '>=', $startDate);
                }
                if ($endDate) {
                    $screenshotsQuery->whereDate('timestamp', '<=', $endDate);
                }

                $screenshots = $screenshotsQuery->get()->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => 'screenshot',
                        'file_url' => $item->file_url,
                        'timestamp' => $item->timestamp,
                        'device_id' => $item->device_id,
                    ];
                });

                $albums = $albums->merge($screenshots);
            }

            // Ambil Captured Photos
            if ($type === 'all' || $type === 'photo') {
                $photosQuery = CapturedPhoto::where('device_id', $deviceId);

                if ($startDate) {
                    $photosQuery->whereDate('captured_at', '>=', $startDate);
                }
                if ($endDate) {
                    $photosQuery->whereDate('captured_at', '<=', $endDate);
                }

                $photos = $photosQuery->get()->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => 'photo',
                        'camera_type' => $item->camera_type,
                        'file_url' => $item->file_url,
                        'timestamp' => $item->captured_at,
                        'device_id' => $item->device_id,
                    ];
                });

                $albums = $albums->merge($photos);
            }

            // Sort by timestamp descending (terbaru dulu)
            $albums = $albums->sortByDesc('timestamp')->values();

            // Group by date untuk UI yang lebih baik
            $groupedByDate = $albums->groupBy(function ($item) {
                return Carbon::parse($item['timestamp'])->format('Y-m-d');
            })->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'formatted_date' => Carbon::parse($date)->format('d M Y'),
                    'items' => $items->values()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Album berhasil dimuat',
                'data' => [
                    'total' => $albums->count(),
                    'screenshots_count' => $albums->where('type', 'screenshot')->count(),
                    'photos_count' => $albums->where('type', 'photo')->count(),
                    'albums' => $albums,
                    'grouped_by_date' => $groupedByDate
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat album',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete album item (screenshot or photo)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAlbumItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:screenshot,photo',
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->type === 'screenshot') {
                $item = Screenshot::find($request->id);
            } else {
                $item = CapturedPhoto::find($request->id);
            }

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item tidak ditemukan'
                ], 404);
            }

            // Optional: Delete file from storage
            // Storage::delete($item->file_url);

            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus item',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
