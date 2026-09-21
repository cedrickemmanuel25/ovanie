<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    // Liste toutes les bannières
    public function index()
    {
        $banners = Banner::orderBy('position')->get();
        return view('admin.banners.index', compact('banners'));
    }

    // Formulaire pour créer une bannière
    public function create()
    {
        return view('admin.banners.create');
    }

    // Stocke une nouvelle bannière
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
            'link' => 'nullable|string|max:500',
            'zone' => 'required|string',
            'position' => 'nullable|integer'
        ]);

        $path = $request->file('image')->store('banners', 'public');

        Banner::create([
            'image' => $path,
            'link' => $request->link,
            'zone' => $request->zone,
            'position' => $request->position ?? 1,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()
            ->route('admin.banners.index')
            ->with('success', 'Bannière ajoutée');
    }
    // ✅ Supprime une bannière puis retourne correctement à la liste
    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);

        // Supprimer le fichier image du storage
        if ($banner->image && \Storage::disk('public')->exists($banner->image)) {
            \Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return redirect()
            ->route('admin.banners.index')
            ->with('success', 'Bannière supprimée avec succès');
    }
}
