<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolCycle;
use App\Models\SchoolSubject;
use App\Models\User;
use App\Services\AlgerianCurriculum2026;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubjectsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'level_id' => ['nullable', 'integer'], 'teacher_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $subjects = SchoolSubject::query()->with(['schoolLevels.cycle:id,name', 'teachers:id,name,email'])->withCount('timetableSessions')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($filters['level_id'] ?? null, fn ($q, $id) => $q->whereHas('schoolLevels', fn ($q) => $q->whereKey($id)))
            ->when($filters['teacher_id'] ?? null, fn ($q, $id) => $q->whereHas('teachers', fn ($q) => $q->whereKey($id)))
            ->when(isset($filters['status']) && $filters['status'] !== null, fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->orderBy('title')->paginate(18)->withQueryString();

        return Inertia::render('Admin/Subjects/Index', [
            'subjects' => $subjects,
            'cycles' => SchoolCycle::with(['levels' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])->orderBy('sort_order')->get(),
            'teachers' => User::where('role', UserRole::TEACHER)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'roomTypes' => array_map(fn (RoomType $type) => ['value' => $type->value, 'label' => $type->label()], RoomType::cases()),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $levels = $data['school_level_ids'];
            $teachers = $data['teacher_ids'];
            unset($data['school_level_ids'], $data['teacher_ids']);
            $subject = SchoolSubject::create([...$data, 'duration_hours' => max(1, (int) ceil((float) $data['weekly_hours'])), 'price' => 0, 'is_certified' => false]);
            $this->syncCustomLevels($subject, $levels);
            $subject->teachers()->sync($teachers);
        });

        return back()->with('success', 'Matière créée avec succès.');
    }

    public function update(Request $request, SchoolSubject $subject): RedirectResponse
    {
        $data = $this->validated($request, $subject);
        DB::transaction(function () use ($data, $subject) {
            $levels = $data['school_level_ids'];
            $teachers = $data['teacher_ids'];
            unset($data['school_level_ids'], $data['teacher_ids']);
            $subject->update([...$data, 'duration_hours' => max(1, (int) ceil((float) $data['weekly_hours']))]);
            $this->syncCustomLevels($subject, $levels);
            $subject->teachers()->sync($teachers);
        });

        return back()->with('success', 'Matière mise à jour.');
    }

    public function destroy(SchoolSubject $subject): RedirectResponse
    {
        if ($subject->timetableSessions()->exists() || $subject->levels()->whereHas('trainingPlans')->exists()) {
            throw ValidationException::withMessages(['subject' => 'Cette matière est utilisée par des séances ou planifications. Désactivez-la au lieu de la supprimer.']);
        }
        $subject->delete();

        return back()->with('success', 'Matière supprimée.');
    }

    public function toggle(SchoolSubject $subject): RedirectResponse
    {
        $subject->update(['is_active' => ! $subject->is_active]);

        return back()->with('success', $subject->is_active ? 'Matière activée.' : 'Matière désactivée.');
    }

    public function loadDefaultCurriculum(AlgerianCurriculum2026 $curriculum): RedirectResponse
    {
        $result = $curriculum->initialize();

        return back()->with('success', $result['created_assignments']
            ? "Programme algérien {$result['curriculum_code']} chargé ({$result['created_assignments']} affectations ajoutées)."
            : "Le programme algérien {$result['curriculum_code']} est déjà à jour.");
    }

    private function validated(Request $request, ?SchoolSubject $subject = null): array
    {
        $request->merge([
            'school_level_ids' => array_values(array_unique(array_map('intval', $request->input('school_level_ids', [])))),
            'teacher_ids' => array_values(array_unique(array_map('intval', $request->input('teacher_ids', [])))),
            'required_room_types' => array_values(array_unique($request->input('required_room_types', []))),
        ]);

        return $request->validate([
            'title' => ['required', 'string', 'max:150'], 'title_ar' => ['nullable', 'string', 'max:150'], 'code' => ['required', 'string', 'max:50', TenantRule::unique('courses', 'code')->where('entity_type', 'subject')->ignore($subject)],
            'category' => ['nullable', 'string', 'max:100'], 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'weekly_hours' => ['required', 'numeric', 'between:0.5,50'],
            'description' => ['nullable', 'string', 'max:3000'], 'is_specialized' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
            'required_room_types' => ['present', 'array'], 'required_room_types.*' => ['string', Rule::enum(RoomType::class), 'distinct'],
            'school_level_ids' => ['required', 'array', 'min:1'], 'school_level_ids.*' => ['integer', 'distinct', TenantRule::exists('school_levels')->where('is_active', true)],
            'teacher_ids' => ['present', 'array'], 'teacher_ids.*' => ['integer', 'distinct', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)->where('is_active', true)],
        ]);
    }

    private function syncCustomLevels(SchoolSubject $subject, array $levelIds): void
    {
        $curriculumLevelIds = DB::table('course_school_level')
            ->where('course_id', $subject->id)
            ->whereNotNull('curriculum_code')
            ->pluck('school_level_id')
            ->map(fn ($id) => (int) $id)
            ->unique();
        DB::table('course_school_level')->where('course_id', $subject->id)->whereNotNull('curriculum_code')
            ->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('course_school_level')->where('course_id', $subject->id)->whereNotNull('curriculum_code')
            ->whereIn('school_level_id', $levelIds)->update(['is_active' => true, 'updated_at' => now()]);
        DB::table('course_school_level')->where('course_id', $subject->id)->whereNull('curriculum_code')->delete();
        foreach (array_values(array_diff(array_unique(array_map('intval', $levelIds)), $curriculumLevelIds->all())) as $order => $levelId) {
            DB::table('course_school_level')->insert([
                'tenant_id' => app(\App\Tenancy\TenantContext::class)->id(), 'course_id' => $subject->id,
                'school_level_id' => $levelId, 'display_order' => $order + 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
