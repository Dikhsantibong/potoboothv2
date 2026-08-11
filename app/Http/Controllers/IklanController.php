<?php

namespace App\Http\Controllers;

use App\Models\Iklan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IklanController extends Controller
{
    /**
     * Display a listing of the iklans.
     */
    public function index(Request $request): Response
    {
        $query = Iklan::latest();

        // Search title
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Filter status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return Inertia::render('iklans/index', [
            'iklans' => $query->paginate(18)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Store a newly created iklan in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120', // 5MB
            'link' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('iklans', 'public');
            $validated['image_path'] = $path;
        }

        Iklan::create($validated);

        return to_route('iklans.index')->with('status', 'iklan-created');
    }

    /**
     * Update the specified iklan in storage.
     */
    public function update(Request $request, Iklan $iklan): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'link' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($iklan->image_path) {
                Storage::disk('public')->delete($iklan->image_path);
            }
            $path = $request->file('image')->store('iklans', 'public');
            $validated['image_path'] = $path;
        }

        $iklan->update($validated);

        return to_route('iklans.index')->with('status', 'iklan-updated');
    }

    /**
     * Remove the specified iklan from storage.
     */
    public function destroy(Iklan $iklan): RedirectResponse
    {
        if ($iklan->image_path) {
            Storage::disk('public')->delete($iklan->image_path);
        }

        $iklan->delete();

        return to_route('iklans.index')->with('status', 'iklan-deleted');
    }
}
