<?php

// app/Http/Controllers/ServiceController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service; // ton modèle Service

class ServiceController extends Controller
{
    public function index()
    {
        // Retourne tous les services actifs depuis la base
        $services = Service::orderBy('name')->get(['name']);
        return response()->json($services);
    }
}
