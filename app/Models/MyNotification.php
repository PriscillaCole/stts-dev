<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

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
            if($m->group_type == 'Individual'){
                $sql = "SELECT * FROM admin_role_users
                INNER JOIN admin_users ON admin_role_users.user_id = admin_users.id
                INNER JOIN my_notifications ON my_notifications.receiver_id = admin_users.id
                WHERE admin_role_users.role_id = 3";
                $users = DB::select($sql);
                $emails = [];
                foreach($users as $r){
                    array_push($emails, $r->email);
                }
       
                Mail::send('email_view',['msg' => $m->message,'link' => $m->link], function ($m) use ($emails) {
                    $m->from("info@8technologies.store", 'STTS formS');
                    $m->to($emails)->subject('FORM STATUS UPDATE ');
                }); 
                dd('hi');
            } 
        });

    }
}
