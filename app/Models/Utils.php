<?php

namespace App\Models;

use Hamcrest\Arrays\IsArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Zebra_Image;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\Mail;
use Encore\Admin\Auth\Database\Administrator;
use App\Mail\Notification;


class Utils
{

    public static function get_notifications($u){
            if($u == null){
            return [];
        }
        $data1 = [];
        $done_ids = [];
        foreach (MyNotification::where('receiver_id',$u->id)->where('status','Unread')
        ->orderBy('id','desc')
        ->get() as $key => $value) { 
            if(in_array($value->id,$done_ids)){
                continue;
            }
            $done_ids[] = $value->id;
            $data1[] = $value;
            # code...
        }
        
        foreach($u->roles as $r){
            $data2 = MyNotification::where('role_id',$r->id)->where('status','Unread')
            ->orderBy('id','desc')
            ->get();
            foreach($data2 as $value){
                if(in_array($value->id,$done_ids)){
                    continue;
                }
                $done_ids[] = $value->id;
                $data1[] = $value;
            } 

        }
        return $data1;
    }
//get users by role
    public static function get_users_by_role($role_id){
        $sql = "SELECT * FROM admin_role_users,admin_users 
            where admin_role_users.user_id = admin_users.id
            AND admin_role_users.role_id = {$role_id}";
            return DB::select($sql);

    }

//get users by role notify
    public static function get_users_by_role_notify($role_id){
        $sql = "SELECT * FROM admin_role_users
        INNER JOIN admin_users ON admin_role_users.user_id = admin_users.id
        INNER JOIN my_notifications ON my_notifications.receiver_id = admin_users.id
        WHERE admin_role_users.role_id = {$role_id}";
        return DB::select($sql);

    }


// //random number generator
//     public static function generate_unique_id() {
//         $prefix = "SG";
//         $current_year = date("Y");
//         $random_number = mt_rand(10000000, 99999999);
//         $unique_id = $prefix . $current_year . $random_number;
//         return $unique_id;
//     }

    public static function create_default_tables()
    {
        $crop = Crop::find(1);
        if ($crop == null) {
            $cro = new Crop();
            $cro->name = "Default crop";
            $cro->number_of_inspection = 0;
            $cro->crop_id = 1;
            $cro->number_of_days_before_submision = 0;
            $cro->save();
        }

        $var = CropVariety::find(1);
        if ($var == null) {
            $var = new CropVariety();
            $var->name = "Default crop variety";
            $var->id = 1;
            $var->crop_id = 1;
            $var->save();
        }
    }

    public static function get_stock_balance($user_id, $crop_id)
    {
        $records = FormStockExaminationRequest::where([
            'administrator_id' => $user_id,
            'import_export_permit_id' => $crop_id
        ])->get();
        $tot = 0;
        foreach ($records as $key => $value) {
            $tot += $value->quantity;
        }
        return $tot;
    }


    public static function sr10_inialized(SubGrower $sr10)
    {
        if (!$sr10) {
            return false;
        }
        $forms = FormSr10::where('planting_return_id', $sr10->id)->get();
        if (count($forms) < 1) {
            return false;
        } else {
            return true;
        }
    }


    public static function start_session()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function alert($msg,$type)
    {
        Utils::start_session();
        $_SESSION['message'] = $msg;
        $_SESSION['type'] = $type;
    }


    public static function has_valid_sr6()
    {
        $recs = FormSr6::where('administrator_id',  Admin::user()->id)->get();
        foreach ($recs as $key => $value) {
            if (!$value->valid_from) {
                return null;
            }
            if (!$value->valid_until) {
                return null;
            }

            $now = time();
            $then = strtotime($value->valid_until);
            if ($now < $then) {
                return $value;
            }
        }
        return null;
    }

    public static function has_valid_sr4()
    {
        $recs = FormSr4::where('administrator_id',  Admin::user()->id)->get();
        foreach ($recs as $key => $value) {
            if (!$value->valid_from) {
                return null;
            }
            if (!$value->valid_until) {
                return null;
            }
            $now = time();
            $then = strtotime($value->valid_until);
            if ($now < $then) {
                return $value;
            }
        }
        return null;
    }


    // public static function has_valid_qds()
    // {
    //     $recs = FormQds::where('administrator_id',  Admin::user()->id)->get();
    //     foreach ($recs as $key => $value) {
    //         if (!$value->valid_from) {
    //             return null;
    //         }
    //         if (!$value->valid_until) {
    //             return null;
    //         }
    //         $now = time();
    //         $then = strtotime($value->valid_until);
    //         if ($now < $then) {
    //             return $value;
    //         }
    //     }
    //     return null;
    // }


    public static function has_role($item, $role)
    {
        $roles = $item->roles()->get();
        foreach ($roles as $r) {
            if ($r->slug == $role) {
                return true;
            }
        }
        return false;
    }

    public static function can_create_qds()
    {
        $recs = FormQds::where('administrator_id',  Admin::user()->id)->get();
        foreach ($recs as $key => $value) {

            if ($value->status == 4) {
                continue;
            }

            if (!$value->valid_from) {
                return false;
            }
            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);
            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    public static function can_create_qds_declaration()
    {
        $recs = FormCropDeclaration::where('administrator_id',  Admin::user()->id)->get();
        foreach ($recs as $key => $value) {

            if ($value->status == 4) {
                continue;
            }

            if (!$value->valid_from) {
                return false;
            }
            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);
            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    // public static function can_create_export_form_old()
    // {
    //     $recs = ImportExportPermit::where('administrator_id',  Admin::user()->id)->get();
    //     foreach ($recs as $key => $value) {
    //         if ($value->is_import) {
    //             continue;
    //         }

    //         if ($value->status == 4) {
    //             continue;
    //         }

    //         if (!$value->valid_from) {
    //             return false;
    //         }
    //         if (!$value->valid_until) {
    //             return false;
    //         }

    //         $now = time();
    //         $then = strtotime($value->valid_until);
    //         if ($now < $then) {
    //             return true;
    //         } else {
    //             return false;
    //         }
    //     }

    //     return true;
    // }



    public static function can_create_import_form()
    {
        $sr4 =  FormSr4::where([
            'administrator_id' => Admin::user()->id,
            'status' => 5
        ])->first();

        if ($sr4 == null) {
            return false;
        }

        return true;
    }


    public static function can_create_export_form()
    {
        $recs = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
            ->where('is_import', '!=', 1)
            ->get();

        foreach ($recs as $key => $value) {

            if ($value->status == 4) {  // if rejected, you cant apply again
                continue;
            }

            if (!$value->valid_from) {
                return false;
            }

            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);
            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }

        $recs_sr4 = FormSr4::where('administrator_id',  Admin::user()->id)->get();

        if (count($recs_sr4) == 0) {    // if no sr4 belongs to current user
            return false;
        }

        foreach ($recs_sr4 as $key => $value_sr4) {
            if (!($value_sr4->status == 5)) {
                return false;
            }
        }

        return true;
    }





    /*
    public static function can_create_export_form_old()
    {
        
        // $recs2 = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
        // ->whereNull('is_import');

        // $recs = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
        // ->where('is_import', '!=', 1)
        // ->union($recs2)
        // ->get();
        


        $recs = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
        ->where('is_import', '!=', 1)
        ->get();

        foreach ($recs as $key => $value) {

            if (!$value->status == 4) {
                continue;
            }

            if (!$value->valid_from) {
                return false;
            }
            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);
            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }

        $recs_sr4 = FormSr4::where('administrator_id',  Admin::user()->id)->get();
        
        if (count($recs_sr4) == 0) {    // if no sr4 belongs to current user
            return false;
        }
        
        foreach ($recs_sr4 as $key => $value_sr4) {
            if (!($value_sr4->status == 5)) {   // if sr4 is not accepted
                return false;
            }
        }

        return true;
    }

    */



    public static function previous_import_form_not_accepted()
    {
        $recs = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
            ->where('is_import', '=', 1)
            ->get();

        foreach ($recs as $key => $value) {
            if (!$value->status == 5) {   // if status is not 'Accepted'
                return false;
            }
        }

        return true;
    }



    public static function previous_export_form_not_accepted()
    {
        $recs = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
            ->where('is_import', '!=', 1)
            ->get();

        foreach ($recs as $key => $value) {
            if (!$value->status == 5) {
                return false;
            }
        }

        return true;
    }



    public static function can_create_sr6()
    {
        $recs = FormSr6::where('administrator_id',  Admin::user()->id)->get();
        foreach ($recs as $key => $value) {

            if ($value->status == 4) {
                continue;
            }

            if (!$value->valid_from) {
                return false;
            }
            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);
            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    public static function can_create_sr4()
    {
        $recs = FormSr4::where('administrator_id',  Admin::user()->id)->get();

        foreach ($recs as $key => $value) {

            // if (!$value->status == 1) {
            //     return false;
            // }

            if (!$value->valid_from) {
                return false;
            }

            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);

            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }
    public static function can_create_import()
    {
        $recs = ImportExportPermit::where('administrator_id',  Admin::user()->id)->get();

        foreach ($recs as $key => $value) {

            // if (!$value->status == 1) {
            //     return false;
            // }
    if ($value->is_import == 1 ) {
            if ($value->status == 4) {
                return true;
            }
            if (!$value->valid_from) {
                return false;
            }

            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);

            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
    }

        return true;
    }

    public static function can_create_export()
    {
        $recs = ImportExportPermit::where('administrator_id',  Admin::user()->id)->get();

        foreach ($recs as $key => $value) {

            // if (!$value->status == 1) {
            //     return false;
            // }
    if ($value->is_import == 0) {
            if ($value->status == 4) {
                return true;
            }
            if (!$value->valid_from) {
                return false;
            }

            if (!$value->valid_until) {
                return false;
            }

            $now = time();
            $then = strtotime($value->valid_until);

            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
    }

        return true;
    }
//disable new button
    public static function can_create_form($model_name){
        $model = "App\\Models\\" . ucfirst($model_name);
        $form = $model::where('administrator_id',  Admin::user()->id)->get();
        //check if the form for that user exists
        if (count($form) == 0) {
            return true;
        } else {
                    
            return false;
                
                }
        }

//function to send notifications
public static function send_notification($m, $model_name, $entity){
   // $model = "App\\Models\\" . ucfirst($model_name);
    $not = new MyNotification();
    $not->role_id = 2;
    $not->message = "New {$entity} has been submitted by ".Admin::user()->name.' ';
    $not->link = admin_url("auth/login"); 
    $not->form_link = admin_url("{$entity}/{$m->id}");
    $not->status = 'Unread'; 
    $not->model = $model_name;
    $not->model_id = $m->id; 
    $not->group_type = 'Group'; 
    $not->action_status_to_make_done = '[]';
    $not->save();

    self::sendMail($not);
}




//function to update notifications
public static function update_notification($m, $model_name, $entity){
    //$model = "App\\Models\\" . ucfirst($model_name);
    $notifications = MyNotification::where('model', $model_name)
    ->where('model_id', $m->id) 
    ->get();
    foreach($notifications as $n){ 
        $n->delete();
    }

    //assigned status
    if($m->status == 1){
            $not = new MyNotification();
            $not->role_id = 2;
            $not->message = "{$entity} has been edited by '.Admin::user()->name.' ";
            $not->link = admin_url("auth/login"); 
            $not->form_link = admin_url("{$entity}/{$m->id}");
            $not->status = 'Unread'; 
            $not->model = $model_name;
            $not->model_id = $m->id; 
            $not->group_type = 'Group'; 
            $not->action_status_to_make_done = '[]'; 
            $not->save();  
            
            self::sendMail($not);
    }

    if($m->status == 2){
        $inspector  = Administrator::find($m->inspector);
        if($inspector != null){
            $not = new MyNotification();
            $not->receiver_id = $inspector->id; 
            $not->message = "Dear {$inspector->name}, you have been assigned to inspect this {$entity}  #{$m->id}."; 
            $not->link = admin_url("auth/login"); 
            $not->form_link = admin_url("{$entity}/{$m->id}"); 
            $not->status = 'Unread'; 
            $not->model = $model_name;
            $not->model_id = $m->id; 
            $not->group_type = 'Individual_i'; 
            $not->action_status_to_make_done = '[]'; 
            $not->save();  

            self::sendMail($not);
        } 
        $farmer  = Administrator::find($m->administrator_id);
        if($farmer != null){
            //check if the notification has been sent before 
            $check = MyNotification::where('model', $model_name)
            ->where('model_id', $m->id)
            ->where('receiver_id', $farmer->id)
            ->where('role_id', 3)
            ->where('message', "Dear {$farmer->name}, your {$entity} is now under inspection.")
            ->first();
            if($check != null){
                return;
            }
            $not = new MyNotification();
            $not->receiver_id = $farmer->id; 
            $not->role_id = 3;
            $not->message = "Dear {$farmer->name}, your {$entity}  is now under inspection."; 
            $not->link = admin_url("auth/login"); 
            $not->form_link = admin_url("{$entity}/{$m->id}");
            $not->status = 'Unread'; 
            $not->model = $model_name;
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
            $not = new MyNotification();
            $not->receiver_id = $farmer->id;
            $not->role_id = 3;
            $not->message = "Dear {$farmer->name}, your {$entity} has been halted by the inspector."; 
            $not->link = admin_url("auth/login"); 
            $not->form_link = admin_url("{$entity}/{$m->id}"); 
            $not->status = 'Unread'; 
            $not->model = $model_name;
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
            $not = new MyNotification();
            $not->receiver_id = $farmer->id; 
            $not->role_id = 3;
            $not->message = "Dear {$farmer->name}, your {$entity} has been rejected by the inspector."; 
            $not->link = admin_url("auth/login"); 
            $not->form_link = admin_url("{$entity}/{$m->id}"); 
            $not->status = 'Unread'; 
            $not->model = $model_name;
            $not->model_id = $m->id; 
            $not->group_type = 'Individual'; 
            $not->action_status_to_make_done = '[]'; +
            $not->save();  

            self::sendMail($not);
        }
    }

    //approved status for farmer
    if($m->status == 5){
        $farmer  = Administrator::find($m->administrator_id);
        if($farmer != null){
            $not = new MyNotification();
            $not->receiver_id = $farmer->id; 
            $not->role_id = 3;
            $not->message = "Dear {$farmer->name}, your {$entity} has been approved by the inspector."; 
            $not->link = admin_url("auth/login"); 
            $not->form_link = admin_url("{$entity}/{$m->id}"); 
            $not->status = 'Unread'; 
            $not->model = $model_name;
            $not->model_id = $m->id; 
            $not->group_type = 'Individual'; 
            $not->action_status_to_make_done = '[]'; 
            $not->save();  

            self::sendMail($not);
        }


        }
    }
    public static function sendMail($not)
    {
        if($not->group_type == 'Individual'){
            $receivers = Utils::get_users_by_role_notify($not->role_id);
            $emails = [];
            foreach($receivers as $r){
                $emails[] = $r->email;
            } 
            Mail::to($emails)
                    ->send(new Notification($not->message, $not->link));
               
        } 
    }





//renew or create a new form after its expired
public static function can_renew_form($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    $recs = $model::where('administrator_id',  Admin::user()->id)->get();

    foreach ($recs as $key => $value) {

        if ($value->status == 5) {
    
        $now = time();
        $then = strtotime($value->valid_until);

            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
    return true;
}
}

public static function can_renew_eform($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    $recs = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
    ->where('is_import', '!=', 1)
    ->get();

    foreach ($recs as $key => $value) {

        if ($value->status == 5) {
    
        $now = time();
        $then = strtotime($value->valid_until);

            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
     }
      return false;
   }



public static function can_renew_iform($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    $recs = ImportExportPermit::where('administrator_id', '=',  Admin::user()->id)
    ->where('is_import', '!=', 0)
    ->get();

    foreach ($recs as $key => $value) {

        if ($value->status == 5) {
    
        $now = time();
        $then = strtotime($value->valid_until);

            if ($now < $then) {
                return true;
            } else {
                return false;
            }
        }
     }
      return false;
   }



 //check expiration date for all Forms
    public static function check_expiration_date($model_name,$id){
        $model = "App\\Models\\" . ucfirst($model_name);
        $form= $model::find($id);
            if ($form->status == 5){

                if ($form->valid_until != null && $form->valid_from != null ) {
        
                $now = time();
                $then = strtotime($form->valid_until);

                if ($now < $then) {
                        return false;
                } 
                else {
                    return true;
                }
            }
        }
    return false; 
}
//check if form is rejected
public static function is_form_rejected($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    $recs = $model::where('administrator_id',  Admin::user()->id)->get();
    foreach ($recs as $key => $value) {
        //check if the status is rejected or halted
        if($value->status == 4 || $value->status == 3){
            return true;
        }
    }
}

//check is form accepted
public static function is_form_accepted($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    $recs = $model::where('administrator_id',  Admin::user()->id)->get();
    foreach ($recs as $key => $value) {
        if($value->status == 5){
            return true;
        }
  }
}

//check if the recommendation has been updated
public static function is_recommendation_updated($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    $recs = $model::where('administrator_id',  Admin::user()->id)->get();
    foreach ($recs as $key => $value) {
        if($value->report_recommendation == 5){
            return true;
        }
  }
}

//check if the form is pending or approved or null
public static function form_status($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    $recs = $model::where('administrator_id',  Admin::user()->id)->get();
    foreach ($recs as $key => $value) {
        if($value->status == 3 || $value->status == 4){
            return true;
        }
  }
}

//check if comments exist in the table for a particular model
public static function check_comments($model_name){
    $model = "App\\Models\\" . ucfirst($model_name);
    //get the id of the current  model 

    $recs = Comment::where('commentable_type', $model)
    ->get();
    foreach ($recs as $key => $value){ 
        if ($value->comment != null) {
            return true;
        }
    }
}


//check if inspector remarks exist
public static function check_inspector_remarks(){
    $recs = FormSr6::where('administrator_id',  Admin::user()->id)->get();
    foreach ($recs as $key => $value){ 
        if ($value->inspector_remarks != null) {
            return true;
        }
    }
}

//check the selected  formSr4 application category selected by a user
public static function check_application_category(){

    $recs = FormSr4::where('administrator_id',  Admin::user()->id)->get();
    foreach ($recs as $key => $value) {
        if($value->type != null){
            $application_category = $value->type;
            return $application_category;
        
    }
}
}


    public static function tell_order_status($status)
    {
        if (!$status)
            return '<span class="badge badge-warning">Pending</span>';
        if ($status == 1)
            return '<span class="badge badge-warning">Pending</span>';
        if ($status == 2)
            return '<span class="badge badge-warning">Shipping</span>';
        if ($status == 3)
            return '<span class="badge badge-success">Completed</span>';
        if ($status == 4)
            return '<span class="badge badge-danger">Canceled</span>';
        if ($status == 5)
            return '<span class="badge badge-warning">Processing</span>';
    }

    public static function tell_status($status)
    {
        if (!$status)
            return '<span class="badge badge-warning">Pending</span>';
        if ($status == 1)
            return '<span class="badge badge-warning">Pending</span>';
        if ($status == 2)
            return '<span class="badge badge-warning">Inspection assigned</span>';
        if ($status == 3)
            return '<span class="badge badge-warning">Halted</span>';
        if ($status == 4)
            return '<span class="badge badge-danger">Rejected</span>';
        if ($status == 5)
            return '<span class="badge badge-success">Accepted</span>';
        if ($status == 6)
            return '<span class="badge badge-danger">Expired</span>';
        if ($status == 7)
            return '<span class="badge badge-warning">Provisional</span>';
        if ($status == 8)
            return '<span class="badge badge-warning">Used</span>';
        if ($status == 9)
            return '<span class="badge badge-warning">Lab inspection</span>';
        if ($status == 10)
            return '<span class="badge badge-warning">Lab test pending</span>';
        if ($status == 11)
            return '<span class="badge badge-success">Marketable</span>';
        if ($status == 12)
            return '<span class="badge badge-danger">Not Marketable</span>';
        if ($status == 13)
            return '<span class="badge badge-warning">Pending for labeling</span>';
        if ($status == 14)
            return '<span class="badge badge-success">Labels Printed</span>';
        if ($status == 15)
            return '<span class="badge badge-danger">Declined</span>';
        if ($status == 16)
            return '<span class="badge badge-success">Initialized</span>';
        if ($status == 17)
            return '<span class="badge badge-warning">Skipped</span>';
        return $status;
    }

    public static function can_be_deleted_by_user($status)
    {
        if (!$status)
            return true;
        if ($status == 1)
            return true;
        if ($status == 2)
            return false;
        if ($status == 3)
            return true;
        if ($status == 4)
            return true;
        if ($status == 5)
            return false;
        if ($status == 6)
            return false;
        if ($status == 7)
            return false;
        if ($status == 8)
            return false;
        if ($status == 9)
            return false;
        if ($status == 10)
            return false;
        if ($status == 11)
            return false;
        if ($status == 12)
            return false;
        if ($status == 13)
            return false;
        if ($status == 14)
            return false;
        if ($status == 15)
            return true;
        if ($status == 16)
            return false;
        if ($status == 17)
            return false;
        return $status;
    }

    public static function show_response($status = 0, $code = 0, $body = "")
    {
        $d['status'] = $status;
        $d['code'] = $code;
        $d['body'] = $body;
        print_r(json_encode($d));
        die();
    }
    public static function get_chat_threads($user_id)
    {

        $threads = Chat::where(
            "sender",
            $user_id
        )
            ->orWhere('receiver', $user_id)
            ->orderByDesc('id')
            ->get();

        $done_ids = array();
        $ready_threads = array();
        foreach ($threads as $key => $value) {
            if (in_array($value->thread, $done_ids)) {
                continue;
            }
            $done_ids[] = $value->thread;
            $ready_threads[] = $value;
        }
        return $ready_threads;
    }


    public static function get_chat_thread($sender, $receiver, $product)
    {
        if ($sender == $receiver) {
            return null;
        }
        $thread = $sender . "-" . $receiver . "-" . $product;

        $results = DB::select(
            'select * from chats where 
                (sender = :sender AND
                receiver = :receiver AND
                product_id = :product)
                OR 
                (sender = :_receiver AND
                receiver = :_sender AND
                product_id = :_product) 
                ',
            [
                'sender' => $sender,
                'receiver' => $receiver,
                'product' => $product,
                '_sender' => $sender,
                '_receiver' => $receiver,
                '_product' => $product
            ]
        );
        if (
            $results != null &&
            !empty($results)
        ) {
            $thread = $results[0]->thread;
        }
        return $thread;
    }

    public static function get_file_url($name)
    {
        // $url = url("storage/uploads");
        if ($name == null || (strlen($name) < 2)) {
            $url = url('storage/uploads/default.png');
        // } else if (file_exists(public_path('storage/uploads' . $name))) {
        //     $url = url("storage/uploads/" . $name);
        } else {
            $url = url("storage/uploads/" . $name);
        }
        return $url;
    }

    //function to get and display the uploaded file



    public static function make_slug($str)
    {
        $slug =  strtolower(Str::slug($str, "-"));

        if (
            (!empty(Product::where("slug", $slug)->First())) ||
            (!empty(Profile::where("username", $slug)->First()))
        ) {
            $slug .= rand(100, 1000);
        }

        return $slug;
    }





    public static function upload_images_1($files, $is_single_file = false)
    {

        ini_set('memory_limit', '-1');
        if ($files == null || empty($files)) {
            return $is_single_file ? "" : [];
        }
        $uploaded_images = array();
        foreach ($files as $file) {

            if (
                isset($file['name']) &&
                isset($file['type']) &&
                isset($file['tmp_name']) &&
                isset($file['error']) &&
                isset($file['size'])
            ) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $file_name = time() . "-" . rand(100000, 1000000) . "." . $ext;
                $destination = 'public/storage/uploads/' . $file_name;

                $res = move_uploaded_file($file['tmp_name'], $destination);
                if (!$res) {
                    continue;
                }
                //$uploaded_images[] = $destination;
                $uploaded_images[] = $file_name;
            }
        }

        $single_file = "";
        if (isset($uploaded_images[0])) {
            $single_file = $uploaded_images[0];
        }


        return $is_single_file ? $single_file : $uploaded_images;
    }




    public static function upload_images($files)
    {
        if ($files == null || empty($files)) {
            return [];
        }
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [];
        }

        $uploaded_images = array();
        if (isset($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if (
                    isset($files['name'][$i]) &&
                    isset($files['type'][$i]) &&
                    isset($files['tmp_name'][$i]) &&
                    isset($files['error'][$i]) &&
                    isset($files['size'][$i])
                ) {
                    $img['name'] = $files['name'][$i];
                    $img['type'] = $files['type'][$i];
                    $img['tmp_name'] = $files['tmp_name'][$i];
                    $img['error'] = $files['error'][$i];
                    $img['size'] = $files['size'][$i];


                    $path = Storage::putFile('/', $img['tmp_name']);



                    $path_not_optimized =  "storage/" . $path;
                    $path_optimized = "storage/thumb_" . $path;
                    $thumbnail = Utils::create_thumbail(
                        array(
                            "source" => $path_not_optimized,
                            "target" => $path_optimized,
                        )
                    );

                    if (strlen($thumbnail) > 3) {
                        $thumbnail = str_replace("storage/", "", $thumbnail);
                        $thumbnail = str_replace("/storage", "", $thumbnail);
                        $thumbnail = str_replace("storage", "", $thumbnail);
                        $thumbnail = str_replace("/", "", $thumbnail);
                    } else {
                        $thumbnail = $path;
                    }

                    $ready_image['src'] = $path;
                    $ready_image['thumbnail'] = $thumbnail;

                    $ready_image['user_id'] = Auth::id();
                    if (!$ready_image['user_id']) {
                        $ready_image['user_id'] = 1;
                    }

                    $_ready_image = new Image($ready_image);
                    $_ready_image->save();
                    $uploaded_images[] = $ready_image;
                }
            }
        }

        return $uploaded_images;
    }

    public static function create_thumbail($params = array())
    {
        if (
            !isset($params['source']) ||
            !isset($params['target'])
        ) {
            return [];
        }

        /*

        $p = 'http://127.0.0.1:8000/storage/'.$path;
                    echo '<h1>'.$p.'</h1>';
                    echo '<h1>'.$path.'</h1>';
                    echo '<img src="'.$p.'" alt="">';
                    die();
                    //dd("");
        */

        $image = new Zebra_Image();

        $image->auto_handle_exif_orientation = false;
        $image->source_path = $params['source'];
        $image->target_path = $params['target'];



        $image->jpeg_quality = 75;
        if (isset($params['quality'])) {
            $image->jpeg_quality = $params['quality'];
        }

        $image->preserve_aspect_ratio = true;
        $image->enlarge_smaller_images = true;
        $image->preserve_time = true;
        $image->handle_exif_orientation_tag = true;

        $width = 380;
        $heigt = 220;
        if (isset($params['width'])) {
            $width = $params['width'];
        }
        if (isset($params['heigt'])) {
            $width = $params['heigt'];
        }


        if (!$image->resize($width, $heigt, ZEBRA_IMAGE_CROP_CENTER)) { 
            return $image->source_path;
        } else {
            return $image->target_path;
        }
    }

}