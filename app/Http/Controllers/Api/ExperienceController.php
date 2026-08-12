<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use Illuminate\Http\JsonResponse;

class ExperienceController extends Controller
{
    public function index(): JsonResponse
    {
        $experiences = Experience::published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('category')
            ->get([
                'id',
                'title',
                'description',
                'photo_path',
                'category_id',
                'neighborhood',
                'neighbourhood_id',
                'latitude',
                'longitude',
            ])
            ->map(fn (Experience $experience): array => $experience->toArray());

        return response()->json($experiences);
    }
}
