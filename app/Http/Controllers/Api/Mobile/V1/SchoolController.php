<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __invoke(Request $request): JsonResponse { $tenant = Tenant::findOrFail(app(TenantContext::class)->id()); $settings = CompanySetting::current(); return response()->json(['data' => ['id' => $tenant->id, 'name' => $settings->trading_name ?: $tenant->name, 'legal_name' => $settings->legal_name, 'logo_url' => $settings->logo_url ?: $tenant->logo_url, 'email' => $settings->email ?: $tenant->email, 'phone' => $settings->phone ?: $tenant->phone, 'secondary_phone' => $settings->secondary_phone, 'address' => trim(implode(', ', array_filter([$settings->address_line_1 ?: $tenant->address, $settings->city ?: $tenant->commune, $tenant->wilaya]))), 'website' => $settings->website]]); }
}
