<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class SalariesController extends Controller
{
    public function __construct(private FilePondService $filePondService) {}

    public function index(Request $request): Response
    {
        $query = Expense::query()
            ->where('category', 'Salaire')
            ->with(['employee:id,name,email,phone,role,job_title', 'creator:id,name', 'files'])
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(fn ($query) => $query
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")));
            })
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('period'), fn ($query) => $query->whereDate('salary_period', $request->string('period').'-01'));

        return Inertia::render('Admin/Salaries/Index', [
            'salaries' => $query->latest('salary_period')->latest('expense_date')->paginate(15)->withQueryString(),
            'total' => (float) (clone $query)->sum('amount'),
            'employees' => User::query()
                ->whereIn('role', [UserRole::TEACHER->value, UserRole::EMPLOYEE->value])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'job_title']),
            'filters' => $request->only(['search', 'employee_id', 'period']),
            'currency' => ['symbol' => config('app.currency_symbol'), 'code' => config('app.currency_code')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $employee = User::findOrFail($validated['employee_id']);
        $expense = Expense::create($this->expenseData($validated, $employee, $request->user()->id));
        $this->syncReceipt($request, $expense);

        return back()->with('success', 'Salaire enregistré avec succès. Il apparaît également dans les dépenses.');
    }

    public function update(Request $request, Expense $salary): RedirectResponse
    {
        abort_unless($salary->category === 'Salaire', 404);
        $validated = $this->validated($request);
        $employee = User::findOrFail($validated['employee_id']);
        $salary->update($this->expenseData($validated, $employee, $salary->created_by));
        $this->syncReceipt($request, $salary);

        return back()->with('success', 'Salaire mis à jour avec succès.');
    }

    public function destroy(Expense $salary): RedirectResponse
    {
        abort_unless($salary->category === 'Salaire', 404);
        $salary->delete();

        return back()->with('success', 'Salaire supprimé des salaires et des dépenses.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', Rule::exists('users', 'id')->whereIn('role', [UserRole::TEACHER->value, UserRole::EMPLOYEE->value])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'salary_period' => ['required', 'date_format:Y-m'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'receipt_temp_folders' => ['array'],
            'receipt_temp_folders.*' => ['string'],
            'receipt_removed_files' => ['array'],
            'receipt_removed_files.*' => ['integer'],
        ]);
    }

    private function expenseData(array $data, User $employee, ?int $creator): array
    {
        return [
            'created_by' => $creator,
            'employee_id' => $employee->id,
            'type' => 'school',
            'category' => 'Salaire',
            'title' => 'Salaire — '.$employee->name.' — '.$data['salary_period'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'salary_period' => $data['salary_period'].'-01',
            'vendor' => $employee->name,
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
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
