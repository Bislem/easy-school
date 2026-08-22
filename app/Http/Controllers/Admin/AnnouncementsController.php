<?php
namespace App\Http\Controllers\Admin;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class AnnouncementsController extends Controller
{
 public function store(Request $request,NotificationDispatcher $notifications): RedirectResponse {$data=$request->validate(['audience'=>['required',Rule::in(['all','parents','students','staff'])],'title'=>['required','string','max:255'],'message'=>['required','string','max:5000']]);$users=User::where('is_active',true)->when($data['audience']==='parents',fn($q)=>$q->where('role',UserRole::PARENT))->when($data['audience']==='students',fn($q)=>$q->where('role',UserRole::STUDENT))->when($data['audience']==='staff',fn($q)=>$q->whereIn('role',[UserRole::TEACHER,UserRole::EMPLOYEE]))->get();foreach($users as $user)$notifications->send($user,'announcement.new',$data['title'],$data['message'],null,['audience'=>$data['audience']]);return back()->with('success','Annonce envoyée à '.$users->count().' destinataire(s).');}
}
