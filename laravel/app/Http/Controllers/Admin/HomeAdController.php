<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HomeAdController extends Controller
{
    public function index()
    {
        $ads = HomeAd::orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.home-ads.index', compact('ads'));
    }

    public function create()
    {
        return view('admin.home-ads.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateData($request, true);

        $validated['media'] = $request->file('media')->store('home-ads', 'public');
        $validated['is_active'] = $request->boolean('is_active');

        HomeAd::create($validated);

        return redirect()
            ->route('admin.home-ads.index')
            ->with('success', 'Publicité ajoutée avec succès.');
    }

    public function edit(HomeAd $homeAd)
    {
        return view('admin.home-ads.edit', compact('homeAd'));
    }

    public function update(Request $request, HomeAd $homeAd)
    {
        $validated = $this->validateData($request, false);

        if ($request->hasFile('media')) {
            if ($homeAd->media && Storage::disk('public')->exists($homeAd->media)) {
                Storage::disk('public')->delete($homeAd->media);
            }

            $validated['media'] = $request->file('media')->store('home-ads', 'public');
        }

        $validated['is_active'] = $request->boolean('is_active');

        $homeAd->update($validated);

        return redirect()
            ->route('admin.home-ads.index')
            ->with('success', 'Publicité mise à jour avec succès.');
    }

    public function destroy(HomeAd $homeAd)
    {
        if ($homeAd->media && Storage::disk('public')->exists($homeAd->media)) {
            Storage::disk('public')->delete($homeAd->media);
        }

        $homeAd->delete();

        return redirect()
            ->route('admin.home-ads.index')
            ->with('success', 'Publicité supprimée avec succès.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:home_ads,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['items'] as $item) {
            \App\Models\HomeAd::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordre des publicités mis à jour avec succès.',
        ]);
    }
    protected function validateData(Request $request, bool $isCreate): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['image', 'video'])],
            'placement' => ['nullable', Rule::in(['home_top', 'home_middle', 'home_bottom', 'season_sale'])],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'url', 'max:500'],
            'text' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url', 'max:500'],
            'duration' => ['required', 'integer', 'min:1000', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'media' => [
                $isCreate ? 'required' : 'nullable',
                'file',
                'max:51200', // 50 Mo
                function ($attribute, $value, $fail) use ($request) {
                    if (!$value) {
                        return;
                    }

                    $type = $request->input('type');

                    $imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
                    $videoMimes = ['video/mp4', 'video/webm', 'video/quicktime'];

                    $mime = $value->getMimeType();

                    if ($type === 'image' && !in_array($mime, $imageMimes, true)) {
                        $fail('Le fichier doit être une image JPG, PNG ou WEBP.');
                    }

                    if ($type === 'video' && !in_array($mime, $videoMimes, true)) {
                        $fail('Le fichier doit être une vidéo MP4, WEBM ou MOV.');
                    }
                },
            ],
        ]);
    }
}