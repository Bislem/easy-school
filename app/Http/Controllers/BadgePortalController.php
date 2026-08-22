<?php
namespace App\Http\Controllers;
use App\Enums\BadgeStatus;
use App\Models\Badge;
use App\Models\CompanySetting;
use App\Services\BadgeQrCode;
use App\Services\Code39Barcode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class BadgePortalController extends Controller
{
    public function mine(Request $request): Response { $owner=$request->user()->student?:$request->user()->staff; abort_unless($owner,404);$badge=$owner->badges()->with('template')->where('status',BadgeStatus::ACTIVE)->where(fn($q)=>$q->whereNull('expiration_date')->orWhereDate('expiration_date','>=',today()))->first();return Inertia::render('Badge/Mine',['badge'=>$badge,'school'=>CompanySetting::current()]); }
    public function verify(string $token): Response { $badge=Badge::where('verification_token',$token)->firstOrFail();return Inertia::render('Badge/Verify',['badge'=>$badge->only(['card_number','first_name','last_name','person_type','role_label','formation_label','group_label','issue_date','expiration_date','display_status']),'school'=>CompanySetting::current()->only(['trading_name','logo_url'])]); }
    public function qr(string $token,BadgeQrCode $qr) { $badge=Badge::where('verification_token',$token)->firstOrFail();return response($qr->svg($badge->verification_url))->header('Content-Type','image/svg+xml')->header('Cache-Control','public, max-age=86400'); }
    public function barcode(string $token,Code39Barcode $barcode) { $badge=Badge::where('verification_token',$token)->whereNotNull('barcode_value')->firstOrFail();return response($barcode->svg($badge->barcode_value))->header('Content-Type','image/svg+xml')->header('Cache-Control','public, max-age=86400'); }
}
