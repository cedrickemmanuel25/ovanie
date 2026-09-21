<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\Submission;

class ContactController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key');

        return view('contact', [
            'settings' => $settings,
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        // Enregistrer la suggestion / message
        Submission::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'message' => "Sujet: {$validated['subject']} \nMessage: {$validated['message']}"
        ]);

        return redirect()->back()->with('success', 'Merci pour votre message, nous vous répondrons bientôt.');
    }
}
