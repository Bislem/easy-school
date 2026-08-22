<?php
namespace App\Http\Controllers\Admin;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class PortalAccountsController extends Controller
{
 public function student(Request $request,Student $student): RedirectResponse {abort_if($student->user_id,422);$data=$request->validate(['email'=>['required','email','unique:users,email'],'password'=>['required','string','min:8']]);DB::transaction(function()use($student,$data){$user=User::create(['name'=>$student->full_name,'email'=>$data['email'],'password'=>$data['password'],'role'=>UserRole::STUDENT,'is_active'=>true,'can_login'=>true,'email_verified_at'=>now()]);$student->update(['user_id'=>$user->id,'email'=>$student->email?:$data['email']]);});return back()->with('success','Accès étudiant créé.'); }
 public function parent(Request $request): RedirectResponse {$data=$request->validate(['first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],'email'=>['required','email','unique:users,email'],'phone'=>['nullable','string','max:50'],'relationship'=>['nullable','string','max:100'],'password'=>['required','string','min:8'],'student_ids'=>['required','array','min:1'],'student_ids.*'=>['integer','exists:students,id']]);DB::transaction(function()use($data){$user=User::create(['name'=>$data['first_name'].' '.$data['last_name'],'email'=>$data['email'],'password'=>$data['password'],'role'=>UserRole::PARENT,'is_active'=>true,'can_login'=>true,'email_verified_at'=>now()]);$parent=SchoolParent::create([...$data,'user_id'=>$user->id]);$parent->students()->sync($data['student_ids']);});return back()->with('success','Compte parent créé.');}
 public function children(Request $request,SchoolParent $parent): RedirectResponse {$ids=$request->validate(['student_ids'=>['required','array','min:1'],'student_ids.*'=>['integer','exists:students,id']])['student_ids'];$parent->students()->sync($ids);return back()->with('success','Enfants autorisés mis à jour.');}
}
