<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/Contact', ['contact' => ['email' => config('saas.support_email'), 'phone' => config('saas.support_phone'), 'hours' => config('saas.support_hours'), 'address' => config('saas.support_address')]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'], 'organization' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', Rule::in(['information', 'pricing', 'partnership', 'support', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);
        ContactRequest::create($data + ['status' => 'new', 'ip_address' => $request->ip()]);
        return back()->with('success', 'Merci ! Votre message a bien été transmis à notre équipe.');
    }
}
