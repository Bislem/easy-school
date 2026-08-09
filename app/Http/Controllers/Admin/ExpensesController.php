<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpensesController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Expense::query()
            ->with(['car:id,make,model,license_plate', 'creator:id,name'])
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('car', fn ($car) => $car
                            ->where('make', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('car_id'), fn ($query) => $query->where('car_id', $request->integer('car_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('expense_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('expense_date', '<=', $request->date('date_to')));

        $total = (clone $query)->sum('amount');

        return Inertia::render('Admin/Expenses/Index', [
            'expenses' => $query->latest('expense_date')->latest('id')->paginate(15)->withQueryString(),
            'total' => (float) $total,
            'categories' => Expense::query()->distinct()->orderBy('category')->pluck('category'),
            'cars' => Car::query()->orderBy('make')->orderBy('model')->get(['id', 'make', 'model', 'license_plate']),
            'filters' => $request->only(['search', 'type', 'category', 'car_id', 'date_from', 'date_to']),
            'currency' => ['symbol' => config('app.currency_symbol'), 'code' => config('app.currency_code')],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateExpense($request);
        $validated['created_by'] = $request->user()->id;
        Expense::create($validated);

        return back()->with('success', 'Dépense enregistrée avec succès.');
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validateExpense($request));

        return back()->with('success', 'Dépense mise à jour avec succès.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Dépense supprimée avec succès.');
    }

    private function validateExpense(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['agency', 'maintenance'])],
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'car_id' => ['nullable', 'exists:cars,id'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'next_service_date' => ['nullable', 'date', 'after_or_equal:expense_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validated['type'] === 'maintenance' && empty($validated['car_id'])) {
            $request->validate(['car_id' => ['required']], ['car_id.required' => 'Le véhicule est obligatoire pour un entretien.']);
        }

        return $validated;
    }
}
