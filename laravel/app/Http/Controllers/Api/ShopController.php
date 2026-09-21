<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

/**
 * Les boutiques ne constituent pas une ressource publique dans OVANIE.
 * Les routes d'administration utilisent un contrôleur séparé et protégé.
 */
class ShopController extends Controller
{
    public function index(Request $request)
    {
        abort(404);
    }

    public function show(Shop $shop)
    {
        abort(404);
    }
}
