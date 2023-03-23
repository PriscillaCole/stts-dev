<?php

namespace App\Models;

use Carbon\Carbon;
use Encore\Admin\Facades\Admin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Encore\Admin\Auth\Database\Administrator;
use App\Mail\Notification;


class ImportExportPermit extends Model
{
    use HasFactory;

    protected $fillable = [
        'administrator_id',
        'name', 
        'address',
        'telephone',
        'type',
        'store_location',
        'quantiry_of_seed',
        'name_address_of_origin',
        'ista_certificate', 
        'phytosanitary_certificate',
        'import_form_certificate_type',
        'crop_category',
        'national_seed_board_reg_num',
        'is_import',
    ];
        //function to send mail
        public static function sendMail($not)
        {
            if($not->group_type == 'Individual'){
                $receivers = Utils::get_users_by_role_notify($not->role_id);
                $emails = [];
                foreach($receivers as $r){
                    $emails[] = $r->email;
                } 
                Mail::to($emails)
                        ->queue(new Notification($not->message, $not->link));
                   
            } 
        }

    public function import_export_permits_has_crops()
    {
        return $this->hasMany(ImportExportPermitsHasCrops::class);
    }

    public static function boot()
    {
        parent::boot(); 
        self::creating(function($model){
            // ... code here
        });
 
        self::updating(function($model){
            if(
                Admin::user()->isRole('basic-user')
            ){
                $model->status = 1;
                return $model;
            }
            if(Admin::user()->isRole('inspector')){
                if($model->status == 5){    
                    if(
                        $model->valid_from == null ||
                        strlen($model->valid_from) < 4 ||
                        strlen($model->valid_until) < 4 
                    ){
                        $model->valid_from =  Carbon::now();
                        $model->valid_until =  Carbon::now()->addYear();   
                        return $model;   
                    }
                }
            }

        }); 

        self::created(function ($model) {
            //accessing the last parameter of the url
            $model_url = request()->segment(count(request()->segments()));
            $not = new MyNotification($model_url);
            $not->role_id = 2; 
            $not->message = 'New import form has been added by '.Admin::user()->name.' '; 
            $not->link = admin_url("{$model_url}/{$model->id}"); 
            $not->status = 'Unread'; 
            $not->model = 'ImportExportPermit';
            $not->model_id = $model->id; 
            $not->group_type = 'Group'; 
            $not->action_status_to_make_done = '[]'; 
            $not->save();  
           
  
        });

        self::updated(function ($m) {
            $model_url = request()->segment(count(request()->segments())-1);
            $notifications = MyNotification::where('model', 'ImportExportPermit')
            ->where('model_id', $m->id) 
            ->get();
            foreach($notifications as $n){ 
                $n->delete();
            }
 
            //assigned status
            if($m->status == 1){
                $not = new MyNotification($model_url);
                $not->role_id = 2;
                $not->message = 'Import/export form has been edited by '.Admin::user()->name.' ';
                $not->link = admin_url("{$model_url}/{$m->id}"); 
                $not->status = 'Unread'; 
                $not->model = 'ImportExportPermit';
                $not->model_id = $m->id; 
                $not->group_type = 'Group'; 
                $not->action_status_to_make_done = '[]'; 
                $not->save();     
        }
            if($m->status == 2){
                $inspector  = Administrator::find($m->inspector);
                if($inspector != null){
                    $not = new MyNotification($model_url);
                    $not->receiver_id = $inspector->id; 
                    $not->message = "Dear {$inspector->name}, you have been assigned to inspect import/export form #{$m->id}."; 
                    $not->link = admin_url("{$model_url}/{$m->id}"); 
                    $not->status = 'Unread'; 
                    $not->model = 'ImportExportPermit';
                    $not->model_id = $m->id; 
                    $not->group_type = 'Individual_i'; 
                    $not->action_status_to_make_done = '[]'; 
                    $not->save();  
                } 
                $farmer  = Administrator::find($m->administrator_id);
                if($farmer != null){
                    $not = new MyNotification($model_url);
                    $not->receiver_id = $farmer->id;
                    $not->role_id = 3; 
                    $not->message = "Dear {$farmer->name}, your import/export form #{$m->id} is now under inspection."; 
                    $not->link = admin_url("{$model_url}/{$m->id}"); 
                    $not->status = 'Unread'; 
                    $not->model = 'ImportExportPermit';
                    $not->model_id = $m->id; 
                    $not->group_type = 'Individual'; 
                    $not->action_status_to_make_done = '[]'; 
                    $not->save(); 
                    
                    self::sendMail($not);
                }
            }

            //halted status for farmer
            if($m->status == 3){
                $farmer  = Administrator::find($m->administrator_id);
                if($farmer != null){
                    $not = new MyNotification($model_url);
                    $not->receiver_id = $farmer->id; 
                    $not->role_id = 3;
                    $not->message = "Dear {$farmer->name}, your import/export form #{$m->id} has been halted by the inspector."; 
                    $not->link = admin_url("{$model_url}/{$m->id}"); 
                    $not->status = 'Unread'; 
                    $not->model = 'ImportExportPermit';
                    $not->model_id = $m->id; 
                    $not->group_type = 'Individual'; 
                    $not->action_status_to_make_done = '[]'; 
                    $not->save(); 
                    
                    self::sendMail($not);
                }
            }

            //rejected status for farmer
            if($m->status == 4){
                $farmer  = Administrator::find($m->administrator_id);
                if($farmer != null){
                    $not = new MyNotification($model_url);
                    $not->receiver_id = $farmer->id;
                    $not->role_id = 3; 
                    $not->message = "Dear {$farmer->name}, your import/export form #{$m->id} has been rejected by the inspector."; 
                    $not->link = admin_url("{$model_url}/{$m->id}"); 
                    $not->status = 'Unread'; 
                    $not->model = 'ImportExportPermit';
                    $not->model_id = $m->id; 
                    $not->group_type = 'Individual'; 
                    $not->action_status_to_make_done = '[]'; 
                    $not->save();  

                    self::sendMail($not);
                }
            }

            //approved status for farmer
            if($m->status == 5){
                $farmer  = Administrator::find($m->administrator_id);
                if($farmer != null){
                    $not = new MyNotification($model_url);
                    $not->receiver_id = $farmer->id;
                    $not->role_id = 3; 
                    $not->message = "Dear {$farmer->name}, your import/export form #{$m->id}/n has been approved by the inspector."; 
                    $not->link = admin_url("{$model_url}/{$m->id}"); 
                    $not->status = 'Unread'; 
                    $not->model = 'ImportExportPermit';
                    $not->model_id = $m->id; 
                    $not->group_type = 'Individual'; 
                    $not->action_status_to_make_done = '[]'; 
                    $not->save();  

                    self::sendMail($not);
                }
            }

            

        });

        self::deleting(function ($model) {
            // ... code here
        });

        self::deleted(function ($model) {
            // ... code here
        });
    } 
    //relationship with comments
    public function comments()
    {
        return $this->morphMany(Comment::class,'commentable');
    }

}
