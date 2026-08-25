<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ParentsController extends Controller
{
    public function index(Request $request): Response
    {
        $parents=SchoolParent::with(['user:id,name,email,phone,is_active,can_login','students:id,first_name,last_name,photo_path,school_level,status'])
            ->when($request->string('search')->trim()->toString(),fn($query,$search)=>$query->where(fn($query)=>$query->where('first_name','like',"%{$search}%")->orWhere('last_name','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")->orWhereHas('user',fn($user)=>$user->where('email','like',"%{$search}%"))->orWhereHas('students',fn($student)=>$student->where('first_name','like',"%{$search}%")->orWhere('last_name','like',"%{$search}%"))))
            ->latest()->paginate(15)->withQueryString();
        return Inertia::render('Admin/Parents/Index',['parents'=>$parents,'students'=>Student::orderBy('last_name')->orderBy('first_name')->get(['id','first_name','last_name','email','phone','photo_path','school_level']),'filters'=>$request->only('search')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validated($request);
        DB::transaction(function()use($data){$user=User::create(['name'=>trim($data['first_name'].' '.$data['last_name']),'email'=>$data['email'],'phone'=>$data['phone']??null,'password'=>$data['password'],'role'=>UserRole::PARENT,'is_active'=>true,'can_login'=>true]);$user->forceFill(['email_verified_at'=>now()])->save();$parent=SchoolParent::create(['user_id'=>$user->id,'first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'phone'=>$data['phone']??null,'relationship'=>$data['relationship']??null]);$parent->students()->sync($data['student_ids']);foreach(Student::whereIn('id',$data['student_ids'])->get() as $student)app(NotificationDispatcher::class)->send($user,'parent.child_associated','Enfant associé à votre compte',$student->full_name.' a été associé(e) à votre espace parent.',$student,['url'=>'/portal/children/'.$student->id]);});
        return back()->with('success','Compte parent créé et enfants associés.');
    }

    public function update(Request $request,SchoolParent $parent): RedirectResponse
    {
        $data=$this->validated($request,$parent);
        $added=collect($data['student_ids'])->diff($parent->students()->pluck('students.id'));
        DB::transaction(function()use($data,$parent,$added){$parent->update(['first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'phone'=>$data['phone']??null,'relationship'=>$data['relationship']??null]);$payload=['name'=>trim($data['first_name'].' '.$data['last_name']),'email'=>$data['email'],'phone'=>$data['phone']??null];if(filled($data['password']??null))$payload['password']=$data['password'];$parent->user->update($payload);$parent->students()->sync($data['student_ids']);foreach(Student::whereIn('id',$added)->get() as $student)app(NotificationDispatcher::class)->send($parent->user,'parent.child_associated','Enfant associé à votre compte',$student->full_name.' a été associé(e) à votre espace parent.',$student,['url'=>'/portal/children/'.$student->id]);});
        return back()->with('success','Compte parent mis à jour.');
    }

    public function toggle(SchoolParent $parent): RedirectResponse
    {
        $parent->user->update(['is_active'=>!$parent->user->is_active]);
        return back()->with('success',$parent->user->is_active?'Compte parent activé.':'Compte parent désactivé.');
    }

    private function validated(Request $request,?SchoolParent $parent=null):array
    {
        return $request->validate(['first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],'email'=>['required','email',Rule::unique('users')->ignore($parent?->user_id)],'phone'=>['nullable','string','max:50'],'relationship'=>['nullable','string','max:100'],'password'=>[$parent?'nullable':'required','confirmed',Password::defaults()],'student_ids'=>['required','array','min:1'],'student_ids.*'=>['integer','distinct','exists:students,id']]);
    }
}
