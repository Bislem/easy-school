<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DemoRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/DemoRequest');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'], 'school_type' => ['required', Rule::in(['private_school', 'training_center', 'language_school', 'other'])],
            'contact_name' => ['required', 'string', 'max:150'], 'contact_role' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'], 'wilaya' => ['required', 'string', 'max:100'], 'commune' => ['required', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'], 'students_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'teachers_count' => ['required', 'integer', 'min:0', 'max:10000'], 'staff_count' => ['required', 'integer', 'min:0', 'max:10000'],
            'sites_count' => ['required', 'integer', 'min:1', 'max:1000'], 'requested_days' => ['required', 'integer', 'min:1', 'max:15'],
            'needs' => ['nullable', 'string', 'max:3000'],
            'modules' => ['nullable', 'array', 'max:12'],
            'modules.*' => ['string', Rule::in(['students', 'hr', 'multi_sites', 'courses', 'planning', 'certificates', 'badges', 'mobile', 'finance', 'reports'])],
        ]);
        if (DemoRequest::where('email', $data['email'])->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['email' => 'Une demande est déjà en cours pour cette adresse.']);
        }
        DemoRequest::create($data + ['status' => 'pending']);

        return redirect()->route('demo.requested');
    }

    public function requested(): Response
    {
        return Inertia::render('Public/DemoRequested');
    }
}
