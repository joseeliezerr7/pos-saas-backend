<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Upload an image file
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();

            // Store in public disk
            $path = $image->storeAs('products', $filename, 'public');

            // Generate full URL with backend domain
            $url = url(Storage::url($path));

            return response()->json([
                'success' => true,
                'message' => 'Imagen subida exitosamente',
                'data' => [
                    'path' => $path,
                    'url' => $url,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se encontró ninguna imagen'
        ], 400);
    }

    /**
     * Delete an image file
     */
    public function deleteImage(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        if (Storage::disk('public')->exists($request->path)) {
            Storage::disk('public')->delete($request->path);

            return response()->json([
                'success' => true,
                'message' => 'Imagen eliminada exitosamente',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'La imagen no existe'
        ], 404);
    }
}
