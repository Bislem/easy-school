<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcademicYearManager
{
    public function create(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $periods = $data['periods'] ?? $this->defaultPeriods($data['start_date'], $data['end_date']);
            unset($data['periods']);
            $year = AcademicYear::create([...$data, 'status' => AcademicYearStatus::DRAFT]);
            $this->validatePeriods($year, $periods);
            foreach ($periods as $index => $period) {
                $year->periods()->create(['name' => $period['name'] ?? 'Trimestre '.($index + 1), 'number' => $index + 1, 'academic_year' => $year->name, 'starts_on' => $period['start_date'], 'ends_on' => $period['end_date'], 'status' => 'draft']);
            }

            return $year->load('periods');
        });
    }

    public function update(AcademicYear $year, array $data): AcademicYear
    {
        $this->ensureWritable($year);

        return DB::transaction(function () use ($year, $data) {
            $periods = $data['periods'] ?? $year->periods()->orderBy('number')->get()->map(fn ($period) => [
                'name' => $period->name,
                'start_date' => $period->starts_on->toDateString(),
                'end_date' => $period->ends_on->toDateString(),
            ])->all();
            unset($data['periods']);
            $candidate = clone $year;
            $candidate->fill($data);
            $this->validatePeriods($candidate, $periods);
            $year->update($data);
            foreach ($periods as $index => $period) {
                $year->periods()->updateOrCreate(['number' => $index + 1], ['name' => $period['name'], 'academic_year' => $year->name, 'starts_on' => $period['start_date'], 'ends_on' => $period['end_date']]);
            }

            return $year->refresh()->load('periods');
        });
    }

    public function activate(AcademicYear $year): void
    {
        DB::transaction(function () use ($year) {
            Tenant::whereKey(app(TenantContext::class)->id())->lockForUpdate()->firstOrFail();
            $locked = AcademicYear::lockForUpdate()->findOrFail($year->id);
            if ($locked->status !== AcademicYearStatus::DRAFT) {
                $this->invalid('Seule une année en brouillon peut être activée.');
            }
            if (AcademicYear::where('status', AcademicYearStatus::ACTIVE)->whereKeyNot($locked->id)->exists()) {
                $this->invalid("Une autre année scolaire est active. Fermez-la avant d'activer celle-ci.");
            }
            $this->validatePeriods($locked, $locked->periods->map(fn ($p) => ['name' => $p->name, 'start_date' => $p->starts_on->toDateString(), 'end_date' => $p->ends_on->toDateString()])->all());
            $locked->update(['status' => AcademicYearStatus::ACTIVE]);
        });
    }

    public function close(AcademicYear $year): void
    {
        if ($year->status !== AcademicYearStatus::ACTIVE) {
            $this->invalid('Seule une année active peut être clôturée.');
        }
        $year->update(['status' => AcademicYearStatus::CLOSED]);
    }

    public function archive(AcademicYear $year): void
    {
        if ($year->status !== AcademicYearStatus::CLOSED) {
            $this->invalid('Clôturez cette année avant de l’archiver.');
        }
        $year->update(['status' => AcademicYearStatus::ARCHIVED]);
    }

    public function ensureWritable(AcademicYear $year): void
    {
        if (! $year->isWritable()) {
            $this->invalid('Cette année scolaire est clôturée et ne peut plus être modifiée.');
        }
    }

    private function validatePeriods(AcademicYear $year, array $periods): void
    {
        if (count($periods) !== 3) {
            $this->invalid('Trois trimestres sont requis.');
        }
        $previousEnd = null;
        foreach ($periods as $period) {
            $start = \Carbon\Carbon::parse($period['start_date']);
            $end = \Carbon\Carbon::parse($period['end_date']);
            if ($start->lt($year->start_date) || $end->gt($year->end_date) || ! $start->lt($end)) {
                $this->invalid("Les dates des trimestres doivent appartenir à l'année scolaire.");
            }
            if ($previousEnd && $start->lte($previousEnd)) {
                $this->invalid('Les trimestres ne peuvent pas se chevaucher.');
            }
            $previousEnd = $end;
        }
    }

    private function defaultPeriods(string $start, string $end): array
    {
        $from = \Carbon\Carbon::parse($start);
        $to = \Carbon\Carbon::parse($end);
        $days = $from->diffInDays($to);
        $firstEnd = $from->copy()->addDays(intdiv($days, 3));
        $secondEnd = $from->copy()->addDays(intdiv($days * 2, 3));

        return [['name' => 'Trimestre 1', 'start_date' => $from->toDateString(), 'end_date' => $firstEnd->toDateString()], ['name' => 'Trimestre 2', 'start_date' => $firstEnd->copy()->addDay()->toDateString(), 'end_date' => $secondEnd->toDateString()], ['name' => 'Trimestre 3', 'start_date' => $secondEnd->copy()->addDay()->toDateString(), 'end_date' => $to->toDateString()]];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['academic_year' => $message]);
    }
}
