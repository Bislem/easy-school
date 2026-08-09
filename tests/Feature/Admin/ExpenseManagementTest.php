<?php

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;

test('administrators can create update and delete school expenses', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)->post(route('admin.expenses.store'), [
        'category' => 'Fournitures scolaires',
        'title' => 'Cahiers et stylos',
        'amount' => 12500,
        'expense_date' => '2026-08-09',
        'vendor' => 'Papeterie Centrale',
        'payment_method' => 'cash',
        'reference' => 'FAC-104',
        'notes' => 'Fournitures pour les salles de cours.',
        'receipt_temp_folders' => [],
        'receipt_removed_files' => [],
    ])->assertSessionHasNoErrors();

    $expense = Expense::where('reference', 'FAC-104')->firstOrFail();
    expect($expense->type)->toBe('school')
        ->and($expense->created_by)->toBe($admin->id);

    $this->actingAs($admin)->put(route('admin.expenses.update', $expense), [
        'category' => 'Équipement',
        'title' => 'Cahiers, stylos et tableaux',
        'amount' => 18500,
        'expense_date' => '2026-08-09',
        'vendor' => 'Papeterie Centrale',
        'payment_method' => 'bank_transfer',
        'reference' => 'FAC-104',
        'notes' => null,
        'receipt_temp_folders' => [],
        'receipt_removed_files' => [],
    ])->assertSessionHasNoErrors();

    expect($expense->refresh()->payment_method)->toBe('bank_transfer')
        ->and($expense->category)->toBe('Équipement');

    $this->actingAs($admin)
        ->delete(route('admin.expenses.destroy', $expense))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($expense);
});

test('teachers cannot access expense management', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);

    $this->actingAs($teacher)
        ->get(route('admin.expenses.index'))
        ->assertForbidden();
});
