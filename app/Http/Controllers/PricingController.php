<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Public/Pricing', ['plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get()]);
    }
}
