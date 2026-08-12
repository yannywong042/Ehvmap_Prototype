<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function home(): View
    {
        $experiences = Experience::approved()->with('category')->latest()->take(3)->get();
        $totalCount = Experience::approved()->count();
        $mapPoints = Experience::published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        return view('pages.home', compact('experiences', 'totalCount', 'mapPoints'));
    }

    public function map(): View
    {
        return view('pages.map');
    }

    public function about(): View
    {
        return view('pages.about');
    }

    public function submit(): View
    {
        return view('pages.submit');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }
}
