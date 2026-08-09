<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class ExpensesController extends Controller
{
    public function __construct(private FilePondService $filePondService) {}

    public function index(Request $request): Response
    {
        $query = Expense::query()
            ->with(['creator:id,name', 'files'])
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('payment_method'), fn ($query) => $query->where('payment_method', $request->string('payment_method')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('expense_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('expense_date', '<=', $request->date('date_to')));

        $total = (clone $query)->sum('amount');

        return Inertia::render('Admin/Expenses/Index', [
            'expenses' => $query->latest('expense_date')->latest('id')->paginate(15)->withQueryString(),
            'total' => (float) $total,
            'categories' => Expense::query()->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only(['search', 'category', 'payment_method', 'date_from', 'date_to']),
            'currency' => ['symbol' => config('app.currency_symbol'), 'code' => config('app.currency_code')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateExpense($request);
        $validated['created_by'] = $request->user()->id;
        $expense = Expense::create($validated);
        $this->syncReceipt($request, $expense);

        return back()->with('success', 'Dépense enregistrée avec succès.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validateExpense($request));
        $this->syncReceipt($request, $expense);

        return back()->with('success', 'Dépense mise à jour avec succès.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('success', 'Dépense supprimée avec succès.');
    }

    private function validateExpense(Request $request): array
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'receipt_temp_folders' => ['array'],
            'receipt_temp_folders.*' => ['string'],
            'receipt_removed_files' => ['array'],
            'receipt_removed_files.*' => ['integer'],
        ]);

        $validated['type'] = 'school';

        return collect($validated)->except(['receipt_temp_folders', 'receipt_removed_files'])->all();
    }

    private function syncReceipt(Request $request, Expense $expense): void
    {
        $tempFolders = $request->input('receipt_temp_folders', []);
        $removedIds = $request->input('receipt_removed_files', []);
        if ($tempFolders !== []) {
            $removedIds = array_values(array_unique(array_merge(
                $removedIds,
                $expense->files()->where('collection', 'receipt')->pluck('id')->all(),
            )));
        }

        $this->filePondService->handleFileUpdates($expense, $tempFolders, $removedIds, 'receipt');
    }
}
