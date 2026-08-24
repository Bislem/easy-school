<?php
namespace App\Http\Controllers\Admin;
use App\Enums\BadgePermission;
use App\Enums\BadgeStatus;
use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\BadgeTemplate;
use App\Models\CompanySetting;
use App\Models\Staff;
use App\Models\Student;
use App\Services\BadgeQrCode;
use App\Services\Code39Barcode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BadgesController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(BadgePermission::VIEW->value);
        Badge::where('status','active')->whereDate('expiration_date','<',today())->update(['status'=>'expired','status_changed_at'=>now(),'status_reason'=>'Expiration automatique.']);
        $badges=Badge::with(['template','issuer:id,name'])->when($request->filled('type'),fn($q)=>$q->where('person_type',$request->string('type')))->when($request->filled('status'),fn($q)=>$q->where('status',$request->string('status')))->when($request->filled('search'),function($q)use($request){$s=$request->string('search');$q->where(fn($q)=>$q->where('card_number','like',"%{$s}%")->orWhere('first_name','like',"%{$s}%")->orWhere('last_name','like',"%{$s}%"));})->latest('issue_date')->paginate(20)->withQueryString();
        return Inertia::render('Admin/Badges/Index',['badges'=>$badges,'students'=>Student::orderBy('last_name')->get(['id','first_name','last_name']),'staff'=>Staff::with('employeeType:id,name')->orderBy('last_name')->get(),'templates'=>BadgeTemplate::orderByDesc('is_default')->get(),'statuses'=>collect(BadgeStatus::cases())->map(fn($s)=>$s->value),'filters'=>$request->only(['search','type','status'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(BadgePermission::MANAGE->value);
        $data=$request->validate(['person_type'=>['required',Rule::in(['student','staff'])],'person_id'=>['required','integer'],'badge_template_id'=>['nullable','exists:badge_templates,id'],'issue_date'=>['required','date'],'expiration_date'=>['nullable','date','after:issue_date'],'barcode_enabled'=>['boolean']]);
        $person=$data['person_type']==='student'?Student::findOrFail($data['person_id']):Staff::with('employeeType')->findOrFail($data['person_id']);
        abort_if($person->badges()->where('status','active')->where(fn($q)=>$q->whereNull('expiration_date')->orWhereDate('expiration_date','>=',today()))->exists(),422,'Cette personne possède déjà une carte active.');
        $badge=$this->makeBadge($person,$data,$request);
        return back()->with('success','Carte '.$badge->card_number.' générée.');
    }

    public function status(Request $request, Badge $badge): RedirectResponse
    {
        Gate::authorize(BadgePermission::MANAGE->value);
        $data=$request->validate(['status'=>['required',Rule::in(['active','suspended','lost','cancelled'])],'reason'=>['nullable','string','max:2000']]);
        $badge->update(['status'=>$data['status'],'status_reason'=>$data['reason']??null,'status_changed_by'=>$request->user()->id,'status_changed_at'=>now()]);
        return back()->with('success','Statut de la carte mis à jour.');
    }

    public function reissue(Request $request, Badge $badge): RedirectResponse
    {
        Gate::authorize(BadgePermission::REISSUE->value);
        $data=$request->validate(['issue_date'=>['required','date'],'expiration_date'=>['nullable','date','after:issue_date'],'reason'=>['required','string','max:2000']]);
        abort_if(in_array($badge->status,[BadgeStatus::REPLACED,BadgeStatus::CANCELLED],true),422);
        $badge->update(['status'=>'replaced','status_reason'=>$data['reason'],'status_changed_by'=>$request->user()->id,'status_changed_at'=>now()]);
        $person=$badge->badgeable; $new=$this->makeBadge($person,[...$data,'person_type'=>$badge->person_type,'badge_template_id'=>$badge->badge_template_id,'barcode_enabled'=>(bool)$badge->barcode_value],$request,$badge);
        return back()->with('success','Carte remplacée par '.$new->card_number.'. L’ancienne reste archivée.');
    }

    public function print(Badge $badge, BadgeQrCode $qr, Code39Barcode $barcode): HttpResponse
    {
        Gate::authorize(BadgePermission::PRINT->value);
        return $this->pdf($badge->newCollection([$badge]),$qr,$barcode,true)->download($badge->card_number.'.pdf');
    }

    public function batch(Request $request, BadgeQrCode $qr, Code39Barcode $barcode): HttpResponse
    {
        Gate::authorize(BadgePermission::PRINT->value);
        $ids=$request->validate(['ids'=>['required','array','min:1','max:100'],'ids.*'=>['integer','exists:badges,id']])['ids'];
        return $this->pdf(Badge::whereIn('id',$ids)->get(),$qr,$barcode)->download('badges-'.now()->format('Ymd-His').'.pdf');
    }

    private function makeBadge(Student|Staff $person,array $data,Request $request,?Badge $replaces=null): Badge
    {
        $student=$person instanceof Student; $enrollment=$student?$person->enrollments()->where('status','registered')->with('form.course')->latest('registered_at')->first():null;
        $templateId=$data['badge_template_id']??BadgeTemplate::where('is_default',true)->value('id'); $alphabet='0123456789ABCDEFGHIJKLMNOPQRST';$suffix='';for($i=0;$i<10;$i++)$suffix.=$alphabet[random_int(0,strlen($alphabet)-1)];$number='ESC-'.now()->format('y').'-'.$suffix;
        return $person->badges()->create(['badge_template_id'=>$templateId,'replaces_badge_id'=>$replaces?->id,'card_number'=>$number,'verification_token'=>hash('sha256',str()->uuid().str()->random(40)),'barcode_value'=>($data['barcode_enabled']??false)?$number:null,'issue_date'=>$data['issue_date'],'expiration_date'=>$data['expiration_date']??null,'status'=>'active','first_name'=>$person->first_name,'last_name'=>$person->last_name,'person_type'=>$student?'student':'staff','role_label'=>$student?'Étudiant':$person->employeeType->name,'formation_label'=>$enrollment?->form?->course?->title,'group_label'=>$enrollment?->group_number?'Groupe '.$enrollment->group_number:null,'photo_url_snapshot'=>$person->photo_url,'issued_by'=>$request->user()->id,'metadata'=>['source_id'=>$person->id]]);
    }

    private function pdf($badges,BadgeQrCode $qr,Code39Barcode $barcode,bool $single=false)
    {
        $badges->loadMissing('template');$qrCodes=$badges->mapWithKeys(fn($b)=>[$b->id=>'data:image/svg+xml;base64,'.base64_encode($qr->svg($b->verification_url,150))]);
        $barcodes=$badges->mapWithKeys(fn($b)=>[$b->id=>$b->barcode_value?'data:image/svg+xml;base64,'.base64_encode($barcode->svg($b->barcode_value,45)):null]);
        $photos=$badges->mapWithKeys(fn($b)=>[$b->id=>$this->localPhoto($b->photo_url_snapshot)]);$school=CompanySetting::current();
        $pdf=Pdf::loadView('admin.badges.print',['badges'=>$badges,'qrCodes'=>$qrCodes,'barcodes'=>$barcodes,'photos'=>$photos,'school'=>$school,'schoolLogo'=>$this->localImage($school->logo_url),'single'=>$single]);
        return $single ? $pdf->setPaper([0,0,242.65,153.01]) : $pdf->setPaper('a4');
    }

    private function localImage(?string $url): ?string
    {
        if(!$url)return null;$path=parse_url($url,PHP_URL_PATH);if(!str_starts_with((string)$path,'/storage/'))return null;$file=public_path(ltrim($path,'/'));if(!is_file($file))return null;
        return 'data:'.(mime_content_type($file)?:'image/jpeg').';base64,'.base64_encode(file_get_contents($file));
    }

    private function localPhoto(?string $url): ?array
    {
        if(!$url)return null;$path=parse_url($url,PHP_URL_PATH);if(!str_starts_with((string)$path,'/storage/'))return null;$file=public_path(ltrim($path,'/'));
        if(!is_file($file)||!($size=@getimagesize($file))||!$size[0]||!$size[1])return null;
        $aspect=$size[0]/$size[1];
        if($aspect>=18/25){$width=25*$aspect;$style='height:25mm;width:'.$width.'mm;margin-left:'.((18-$width)/2).'mm;';}
        else{$height=18/$aspect;$style='width:18mm;height:'.$height.'mm;margin-top:'.((25-$height)/2).'mm;';}
        return ['src'=>'data:'.(mime_content_type($file)?:'image/jpeg').';base64,'.base64_encode(file_get_contents($file)),'style'=>$style];
    }
}
