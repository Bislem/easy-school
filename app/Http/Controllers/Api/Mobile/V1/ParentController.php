<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\StudentResource;
use App\Models\Student;
use App\Models\StudentObservation;
use App\Services\Mobile\StudentMobileData;
use App\Services\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(private StudentMobileData $data) {}

    private function parent(Request $request)
    {
        $profileId = $request->attributes->get('mobile_membership')?->parent_id;

        return $profileId ? \App\Models\SchoolParent::find($profileId) : $request->user()->schoolParent;
    }

    private function child(Request $request, Student $student): Student
    {
        abort_unless($this->parent($request)?->students()->whereKey($student->id)->wherePivot('is_visible', true)->exists(), 403, 'Les données de cet enfant ne sont pas accessibles à votre compte.');

        return $student;
    }

    public function children(Request $request)
    {
        return StudentResource::collection($this->parent($request)?->students()->wherePivot('is_visible', true)->paginate(20) ?? Student::whereRaw('1=0')->paginate());
    }

    public function show(Request $request, Student $student): StudentResource
    {
        return new StudentResource($this->child($request, $student));
    }

    public function formations(Request $request, Student $student)
    {
        return $this->data->enrollments($this->child($request, $student));
    }

    public function planning(Request $request, Student $student)
    {
        return $this->data->sessions($this->child($request, $student));
    }

    public function attendance(Request $request, Student $student)
    {
        return $this->data->attendance($this->child($request, $student));
    }

    public function observations(Request $request, Student $student)
    {
        return $this->data->observations($this->child($request, $student));
    }

    public function replyToObservation(Request $request, Student $student, StudentObservation $observation, NotificationDispatcher $notifications): JsonResponse
    {
        $student = $this->child($request, $student);
        abort_unless($observation->student_id === $student->id && $observation->parent_id === null, 404);
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);
        $reply = $student->observations()->create([
            'author_id' => $request->user()->id,
            'parent_id' => $observation->id,
            'message' => $data['message'],
        ]);
        if ($observation->author) {
            $notifications->send($observation->author, 'observation.parent_replied', 'Réponse d’un parent', $request->user()->name.' a répondu à une observation concernant '.$student->full_name.'.', $reply, ['url' => '/portal/students/'.$student->id]);
        }

        return response()->json(['data' => [
            'id' => $reply->id,
            'message' => $reply->message,
            'author' => ['id' => $request->user()->id, 'name' => $request->user()->name, 'role' => 'parent'],
            'created_at' => $reply->created_at?->toIso8601String(),
        ]], 201);
    }

    public function grades(Request $request, Student $student): JsonResponse
    {
        $this->child($request, $student);

        return response()->json(['data' => [], 'meta' => ['supported' => false, 'message' => 'Aucun module de notes n’est disponible dans cette version.']]);
    }

    public function certificates(Request $request, Student $student): JsonResponse
    {
        $student = $this->child($request, $student);

        return response()->json(['data' => $student->certificates()->get()->map(fn ($c) => ['id' => $c->id, 'number' => $c->certificate_number, 'type' => $c->type?->value ?? $c->type, 'formation' => $c->formation_name, 'result' => $c->result, 'issue_date' => $c->issue_date?->format('Y-m-d'), 'verification_url' => $c->verification_url])]);
    }

    public function documents(Request $request, Student $student): JsonResponse
    {
        $student = $this->child($request, $student);

        return response()->json(['data' => $student->files()->get()->map(fn ($f) => ['id' => $f->id, 'name' => $f->original_name, 'mime_type' => $f->mime_type, 'size' => $f->size, 'url' => $f->url])]);
    }

    public function payments(Request $request, Student $student): JsonResponse
    {
        $student = $this->child($request, $student);

        return response()->json(['data' => $student->enrollments()->with(['form.course', 'trainingPlanGroup.plan.course', 'installments', 'payments'])->get()->map(fn ($e) => ['enrollment_id' => $e->id, 'formation' => $e->form?->course?->title ?? $e->trainingPlanGroup?->plan?->course?->title, 'price' => $e->final_price, 'paid' => $e->total_paid, 'remaining' => $e->remaining_balance, 'status' => $e->payment_status?->value ?? $e->payment_status, 'installments' => $e->installments->map->only(['id', 'amount', 'due_date', 'status']), 'payments' => $e->payments->map->only(['id', 'reference', 'amount', 'payment_date', 'payment_method', 'status'])])]);
    }
}
