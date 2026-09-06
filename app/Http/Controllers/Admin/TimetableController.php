<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->user()->can('timetable.view') || abort(403);

        return Inertia::render('Admin/Timetable/Index');
    }
}
