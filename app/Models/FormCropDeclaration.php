<?php

namespace App\Models;
use Carbon\Carbon;
use Encore\Admin\Facades\Admin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPUnit\Framework\isEmpty;

class FormCropDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'administrator_id',
        'field_size',
        'source_of_seed',
        'seed_rate',
        'amount',
        'status',
        'payment_receipt',
        'form_qd_id',
    ];

    public static function boot()
    {
        parent::boot(); 

        self::creating(function($model){
            
        });
 
        self::updating(function($model){
        });

        self::created(function ($model) 
        {

            Utils::send_notification($model, 'SeedLab', request()->segment(count(request()->segments())));

            
            
        });


        self::updated(function ($model) 
        {

            Utils::update_notification($model, 'SeedLab', request()->segment(count(request()->segments())-1));  
            
            // if (Admin::user()->isRole('inspector')) 
            // {
            //    $model->crop_varieties->each(function ($crop_variety) use ($model) {
            //     if ($crop_variety->crop->crop_inspection_types != null) 
            //     {
            //         foreach ($crop_variety->crop->crop_inspection_types as $key => $inspection) 
            //         {
            //             FormSr10::insertOrIgnore([
            //                 'qds_declaration_id' => $model->id,
            //                 'stage' => $inspection->inspection_stage
            //             ],
            //             [
            //                 'stage' => $inspection->inspection_stage,
            //                 'farmer_id' => $model->administrator_id,
            //                 'status' => '1',
            //                 'is_active' => 1,
            //                 'is_done' => 0,
            //                 'is_initialized' => false,
            //                 'status_comment' => "",
            //                 'qds_declaration_id' => $model->id,
            //                 'administrator_id' => $model->administrator_id,
            //                 'inspector' =>  Admin::user()->id,
            //                 'min_date' => Carbon::parse($inspection->date_planted)->addDays($inspection->period_after_planting)->toDateString(),
            //             ]);
                        
            //         }
            //     }
            //     else
            //     {
            //         $model->status = 5;
            //         $model->save();
            //     }
            //    });

            // }  
         
            
            
            

        });
 
    }

    
    public function form_crop_declarations_has_crop_varieties()
    {
        return $this->hasMany(FormCropDeclarationsHasCropVariety::class);
    }

    public function crop_varieties()
    {
        return $this->belongsToMany(CropVariety::class, 'form_crop_declarations_has_crop_varieties');
    }
}
