<?php

namespace App\Http\Controllers\Author;

use App\Domain\References\CrossReferenceRegistry;
use App\Http\Controllers\Controller;
use App\Models\Encyclopedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function search(Request $request, CrossReferenceRegistry $references): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'exclude' => ['nullable', 'string', 'max:120'],
        ]);

        $excludeId = null;
        if (! empty($data['exclude'])) {
            $excludeId = Encyclopedia::where('slug', $data['exclude'])->value('id');
        }

        return response()->json([
            'results' => $references->search($data['q'], $excludeId),
        ]);
    }
}
