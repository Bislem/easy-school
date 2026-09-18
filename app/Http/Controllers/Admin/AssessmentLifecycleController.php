<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\AssessmentLifecycleService;
use Illuminate\Http\Request;

class AssessmentLifecycleController extends Controller
{
    private function check(Assessment $assessment): void
    {
        abort_unless(
            (int) $assessment->tenant_id === (int) app(\App\Tenancy\TenantContext::class)->id(),
            404
        );
    }

    public function open(Assessment $assessment, AssessmentLifecycleService $service)
    {
        $this->check($assessment);
        $service->open($assessment, auth()->id());

        return back()->with('success', 'Évaluation ouverte pour la saisie des notes.');
    }

    public function complete(Assessment $assessment, AssessmentLifecycleService $service)
    {
        $this->check($assessment);
        $result = $service->complete($assessment, auth()->id());

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Évaluation terminée.' : 'Certaines notes sont invalides.'
        );
    }

    public function lock(Assessment $assessment, AssessmentLifecycleService $service)
    {
        $this->check($assessment);
        $service->lock($assessment, auth()->id());

        return back()->with('success', 'Évaluation verrouillée.');
    }

    public function publish(Assessment $assessment, AssessmentLifecycleService $service)
    {
        $this->check($assessment);
        $service->publish($assessment, auth()->id());

        return back()->with('success', 'Résultats publiés aux parents.');
    }

    public function reopen(Request $request, Assessment $assessment, AssessmentLifecycleService $service)
    {
        $this->check($assessment);
        $reason = $request->validate([
            'reason' => 'required|string|min:10|max:2000',
        ])['reason'];

        $service->reopen($assessment, auth()->id(), $reason);

        return back()->with('success', 'Évaluation rouverte.');
    }
}
