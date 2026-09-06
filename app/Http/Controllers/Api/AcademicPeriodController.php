<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicPeriodRequest;
use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicPeriodController extends Controller
{
    public function index(Request $request)
    {
        $request->user()->can('timetable.view') || abort(403);
        return AcademicPeriod::orderByDesc('starts_on')->get();
    }
    public function store(AcademicPeriodRequest $request)
    {
        return response()->json($this->persist(new AcademicPeriod, $request->validated()), 201);
    }
    public function show(AcademicPeriod $academicPeriod)
    {
        request()->user()->can('timetable.view') || abort(403);
        return $academicPeriod;
    }
    public function update(AcademicPeriodRequest $request, AcademicPeriod $academicPeriod)
    {
        return $this->persist($academicPeriod, $request->validated());
    }
    public function destroy(AcademicPeriod $academicPeriod)
    {
        request()->user()->can('timetable.manage') || abort(403);
        abort_if($academicPeriod->timetableSessions()->exists(), 409, 'This academic period is used by timetable sessions.');
        $academicPeriod->delete();
        return response()->noContent();
    }
    private function persist(AcademicPeriod $period, array $data): AcademicPeriod
    {
        return DB::transaction(function () use ($period, $data) {
            if ($data['is_current'] ?? false) AcademicPeriod::query()->update(['is_current' => false]);
            $period->fill($data)->save();
            return $period->refresh();
        });
    }
}
