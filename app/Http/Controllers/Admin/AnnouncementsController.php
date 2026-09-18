<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\MobileMembership;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolCycle;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class AnnouncementsController extends Controller
{
    public function __construct(private FilePondService $filePond) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Announcements/Index', [
            'cycles' => SchoolCycle::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'code']),
            'announcements' => SchoolAnnouncement::with(['creator:id,name', 'files'])
                ->withCount([
                    'notifications as account_recipient_count',
                    'notifications as seen_count' => fn ($query) => $query->whereNotNull('read_at'),
                ])->latest('created_at')->paginate(12),
        ]);
    }

    public function store(Request $request, NotificationDispatcher $notifications): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'message' => ['required', 'string', 'max:5000'],
            'delivery' => ['required', Rule::in(['account', 'email', 'both'])],
            'target' => ['required', Rule::in(['all', 'cycles'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'cycle_ids' => ['exclude_unless:target,cycles', 'required', 'array', 'min:1'],
            'cycle_ids.*' => ['integer', 'distinct', TenantRule::exists('school_cycles')],
            'poster_temp_folders' => ['array', 'max:1'], 'poster_temp_folders.*' => ['string'],
        ]);
        $announcement = SchoolAnnouncement::create([
            'created_by' => $request->user()->id, 'title' => $data['title'], 'message' => $data['message'],
            'delivery' => $data['delivery'], 'target' => $data['target'], 'status' => 'draft',
            'cycle_ids' => $data['target'] === 'cycles' ? array_map('intval', $data['cycle_ids']) : [],
        ]);
        $this->filePond->handleFileUpdates($announcement, $data['poster_temp_folders'] ?? [], [], 'poster');
        if ($data['status'] === 'published') $this->publish($announcement->fresh('files'), $request, $notifications);

        return back()->with('success', $data['status'] === 'published' ? 'Annonce publiée et envoyée aux parents.' : 'Brouillon enregistré.');
    }

    public function status(Request $request, SchoolAnnouncement $announcement, NotificationDispatcher $notifications): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'unpublished'])]]);
        if ($data['status'] === 'published') $this->publish($announcement->load('files'), $request, $notifications);
        else $announcement->update(['status' => 'unpublished']);

        return back()->with('success', $data['status'] === 'published' ? 'Annonce publiée.' : 'Annonce dépubliée et masquée aux parents.');
    }

    public function destroy(SchoolAnnouncement $announcement): RedirectResponse
    {
        foreach ($announcement->files as $file) $file->delete();
        $announcement->delete();
        return back()->with('success', 'Annonce supprimée.');
    }

    private function publish(SchoolAnnouncement $announcement, Request $request, NotificationDispatcher $notifications): void
    {
        $users = $this->recipients($announcement);
        $firstPublication = ! $announcement->notified_at;
        $announcement->update([
            'status' => 'published', 'published_at' => now(),
            'recipient_count' => $users->count(), 'notified_at' => $announcement->notified_at ?: now(),
        ]);
        if (! $firstPublication) return;
        $tenantId = app(TenantContext::class)->id();
        if (in_array($announcement->delivery, ['account', 'both'], true)) foreach ($users as $user) {
            $notifications->send($user, 'announcement.new', $announcement->title, $announcement->message, $announcement, [
                'tenant_id' => $tenantId, 'announcement_id' => $announcement->id,
                'poster_url' => $announcement->poster_url, 'url' => '/parent/announcements',
            ]);
        }
        if (in_array($announcement->delivery, ['email', 'both'], true)) foreach ($users->whereNotNull('email') as $user) {
            rescue(fn () => Mail::html(view('emails.announcement', ['announcement' => $announcement, 'school' => $request->user()->tenant])->render(),
                fn ($mail) => $mail->to($user->email, $user->name)->subject($announcement->title)), report: true);
        }
    }

    private function recipients(SchoolAnnouncement $announcement)
    {
        $cycleIds = $announcement->target === 'cycles' ? ($announcement->cycle_ids ?? []) : [];
        $activeYearId = AcademicYear::where('status', 'active')->value('id');
        $ids = MobileMembership::query()->where('mobile_memberships.tenant_id', app(TenantContext::class)->id())
            ->where('mobile_memberships.role', 'parent')->where('mobile_memberships.is_active', true)
            ->when($cycleIds, fn ($query) => $query->whereExists(fn ($children) => $children->selectRaw('1')->from('parent_student')
                ->join('student_academic_enrollments', 'student_academic_enrollments.student_id', '=', 'parent_student.student_id')
                ->join('school_levels', 'school_levels.id', '=', 'student_academic_enrollments.school_level_id')
                ->whereColumn('parent_student.parent_id', 'mobile_memberships.parent_id')->whereIn('school_levels.school_cycle_id', $cycleIds)
                ->where('student_academic_enrollments.status', 'enrolled')->when($activeYearId, fn ($q) => $q->where('student_academic_enrollments.academic_year_id', $activeYearId))))
            ->pluck('user_id')->unique();
        return User::withoutGlobalScopes()->whereIn('id', $ids)->where('is_active', true)->get();
    }
}
