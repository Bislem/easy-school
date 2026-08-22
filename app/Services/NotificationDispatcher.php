<?php
namespace App\Services;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class NotificationDispatcher
{
 public function send(User|int $recipient,string $type,string $title,string $message,?Model $related=null,array $data=[]): PortalNotification
 {
  $id=$recipient instanceof User?$recipient->id:$recipient;$channels=config('school_notifications.default_channels',['in_app']);
  return PortalNotification::create(['recipient_id'=>$id,'type'=>$type,'title'=>$title,'message'=>$message,'related_type'=>$related?->getMorphClass(),'related_id'=>$related?->getKey(),'data'=>$data,'channels'=>$channels,'delivery_state'=>collect($channels)->mapWithKeys(fn($c)=>[$c=>$c==='in_app'?'delivered':'pending'])->all(),'occurred_at'=>now()]);
 }
}
