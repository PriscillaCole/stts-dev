<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class MyNotification extends Model
{
    use HasFactory;

    private $entity;
    //create constructor with parameter entity
    public function __construct($entity = null)
    {
        $this->entity = $entity;
    }

    public static function boot()
    {
        parent::boot(); 
        self::created(function($m){
            if($m->group_type == 'Group'){
                $basic_user = $m->role_id == 3;
                $receivers = Utils::get_users_by_role($basic_user);
                $emails = [];
                foreach($receivers as $r){
                    $emails[] = $r->email;
                }
                  
                Mail::send('email_view',['msg' => $m->message,'link' => $m->link], function ($m) use ($emails) {
                    $m->from("info@8technologies.store", 'STTS formS');
                    $m->to($emails)->subject('FORM STATUS UPDATE ');
                }); 
            } 
        });

    }
}
