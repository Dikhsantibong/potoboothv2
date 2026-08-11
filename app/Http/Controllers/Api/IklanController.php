<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Iklan;
use Illuminate\Http\Request;

class IklanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Iklan::query();

        if ($request->filled('is_active')) {
            $query->where('status', $request->is_active == 1 ? 'active' : 'inactive');
        }

        // Only show iklans that are currently active based on start and end dates
        $now = now();
        $query->where(function ($q) use ($now) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', $now);
        });

        $iklans = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $iklans,
            'base_url' => url('/')
        ]);
    }
}
