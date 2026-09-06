<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactRequestsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['new', 'in_progress', 'resolved', 'archived'])], 'search' => ['nullable', 'string', 'max:100']]);
        return Inertia::render('SuperAdmin/ContactRequests/Index', [
            'requests' => ContactRequest::with('handler:id,name')->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('organization', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%")))->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'counts' => ContactRequest::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function update(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'in_progress', 'resolved', 'archived'])], 'internal_note' => ['nullable', 'string', 'max:3000']]);
        $contactRequest->update([...$data, 'read_at' => $contactRequest->read_at ?? now(), 'resolved_at' => $data['status'] === 'resolved' ? now() : null, 'handled_by' => Auth::guard('super_admin')->id()]);
        return back()->with('success', 'Demande de contact mise à jour.');
    }
}
