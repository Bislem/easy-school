<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimetableSessionRequest;
use App\Http\Requests\TimetableChangeRequest;
use App\Http\Resources\TimetableSessionResource;
use App\Models\TimetableSession;
use App\Repositories\TimetableSessionRepository;
use App\Services\TimetableService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimetableSessionController extends Controller
{
    public function __construct(private TimetableSessionRepository $repository, private TimetableService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TimetableSession::class);
        $filters = $request->validate(['academic_period_id' => ['nullable', 'integer'], 'group_id' => ['nullable', 'integer'], 'teacher_id' => ['nullable', 'integer'], 'day' => ['nullable', 'integer', 'between:1,7'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
        return TimetableSessionResource::collection($this->repository->paginate($filters, $filters['per_page'] ?? 25));
    }

    public function calendar(Request $request)
    {
        $this->authorize('viewAny', TimetableSession::class);
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'group_id' => ['nullable', 'integer'], 'teacher_id' => ['nullable', 'integer']]);
        abort_if(\Carbon\Carbon::parse($data['from'])->diffInDays($data['to']) > 366, 422, 'The calendar range cannot exceed 366 days.');
        return ['data' => $this->repository->calendar($data['from'], $data['to'], $data)];
    }

    public function store(TimetableSessionRequest $request)
    {
        return (new TimetableSessionResource($this->service->create($request->validated())))->response()->setStatusCode(201);
    }

    public function show(TimetableSession $timetableSession): TimetableSessionResource
    {
        $this->authorize('view', $timetableSession);
        return new TimetableSessionResource($timetableSession->load(['group.level.cycle', 'subject', 'teacher', 'room', 'academicPeriod']));
    }

    public function update(TimetableSessionRequest $request, TimetableSession $timetableSession): TimetableSessionResource
    {
        $this->authorize('update', $timetableSession);
        $scope = $request->validate(['scope' => ['sometimes', 'in:one,all']])['scope'] ?? 'one';
        return new TimetableSessionResource($this->service->update($timetableSession, $request->validated(), $scope));
    }

    public function check(TimetableSessionRequest $request)
    {
        $data = $request->validated();
        $except = isset($data['except_session_id']) ? TimetableSession::findOrFail($data['except_session_id']) : null;
        unset($data['except_session_id']);
        return response()->json($this->service->check($data, $except)->toArray());
    }

    public function duplicate(TimetableChangeRequest $request, TimetableSession $timetableSession)
    {
        $this->authorize('create', TimetableSession::class);
        return (new TimetableSessionResource($this->service->duplicate($timetableSession, $request->validated())))->response()->setStatusCode(201);
    }

    public function move(TimetableChangeRequest $request, TimetableSession $timetableSession): TimetableSessionResource
    {
        return $this->change($request, $timetableSession, 'moved');
    }

    public function replaceTeacher(TimetableChangeRequest $request, TimetableSession $timetableSession): TimetableSessionResource
    {
        $request->validate(['teacher_id' => ['required']]);
        return $this->change($request, $timetableSession, 'teacher_replacement');
    }

    public function changeRoom(TimetableChangeRequest $request, TimetableSession $timetableSession): TimetableSessionResource
    {
        $request->validate(['classroom_id' => ['required'], 'temporary' => ['sometimes', 'boolean']]);
        return $this->change($request, $timetableSession, $request->boolean('temporary') ? 'temporary_room_change' : 'room_change');
    }

    public function cancel(Request $request, TimetableSession $timetableSession): TimetableSessionResource
    {
        $this->authorize('update', $timetableSession);
        $data = $request->validate(['effective_date' => ['nullable', 'date'], 'scope' => ['sometimes', 'in:one,all']]);
        return new TimetableSessionResource($this->service->cancel($timetableSession, $data['effective_date'] ?? null, $data['scope'] ?? 'one'));
    }

    private function change(TimetableChangeRequest $request, TimetableSession $session, string $type): TimetableSessionResource
    {
        $this->authorize('update', $session);
        $data = $request->validated();
        $scope = $data['scope'] ?? 'one';
        unset($data['scope'], $data['temporary']);
        $data['change_type'] = $type;
        return new TimetableSessionResource($this->service->update($session, $data, $scope));
    }

    public function destroy(TimetableSession $timetableSession): Response
    {
        $this->authorize('delete', $timetableSession);
        $timetableSession->delete();
        return response()->noContent();
    }
}
