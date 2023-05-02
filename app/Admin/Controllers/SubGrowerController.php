<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\SubGrower\BatchReplicate;
use App\Models\Crop;
use App\Models\CropVariety;
use App\Models\SubGrower;
use App\Models\Utils;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;


class SubGrowerController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Planting Return - Growers';

    /**
     * 
     * 
     

     * Make a grid builder.
     *
     * @return Grid 
     */
    protected function grid()
    {
 
        
       
        $grid = new Grid(new SubGrower());
        $grid->disableExport();

        //as an inspector, view only subgrowers assigned to you
          //check if the role is an inspector and has been assigned that form
          if (Admin::user()->isRole('inspector'))  
          {
              $grid->model()->where('inspector', '=', Admin::user()->id);
              //return an empty table if the inspector has not been assigned any forms
              if (Subgrower::where('inspector', '=', Admin::user()->id)->count() == 0) 
              { 
                  //return an empty table if the inspector has not been assigned an
                  $grid->model(0);       
              }
          }
      

        if (Admin::user()->isRole('admin')) {
            $grid->batchActions(function ($batch) {
                //disable batch delete
                $batch->disableDelete();
                $batch->add(new BatchReplicate()); 
            });
            $grid->actions(function ($actions) {
                    $actions->disableDelete();
                    $actions->disableEdit();    
        
            });
            $grid->disableCreateButton();
        }

        if (Admin::user()->isRole('inspector')) {
            $grid->disableCreateButton();
            $grid->disableBatchActions();
        }
        
  


        if (Admin::user()->isRole('basic-user')) {
            $grid->model()->where('administrator_id', '=', Admin::user()->id);
            $grid->actions(function ($actions) {
                $status = ((int)(($actions->row['status'])));
                if ($status == 4) {
                    $actions->disableDelete();
                    $actions->disableEdit();
                }
                if (
                    $status != 1
                ) {
                    $actions->disableDelete();
                    $actions->disableEdit();
                }
            });
        } else if (Admin::user()->isRole('inspector') || Admin::user()->isRole('admin')) {
           // $grid->model()->where('inspector', '=', Admin::user()->id);
            $grid->actions(function ($actions) {
                $status = ((int)(($actions->row['status'])));
            
                    $actions->disableDelete();
                    $actions->disableEdit();
                

            }); 
        } else if (Admin::user()->isRole('basic-user')) {
            $grid->actions(function ($actions) {

                $status = ((int)(($actions->row['status'])));
                if ($status == 4) {
                    $actions->disableDelete();
                    $actions->disableEdit();
                }
                if (
                    $status != 1
                ) {
                    $actions->disableDelete();
                    $actions->disableEdit();
                }
            });
        }  



        $grid->column('created_at', __('Created'))->display(function ($item) {
            return Carbon::parse($item)->diffForHumans();
        })->sortable();


        $grid->column('administrator_id', __('Applicant'))->display(function ($user) {
            $_user = Administrator::find($user);
            if (!$_user) {
                return "-";
            }
            return $_user->name;
        });
        
        

        $grid->column('field_name', __('Field Name'))->sortable();
        $grid->column('name', __('Person responisble'))->sortable();
        $grid->column('size', __('Size'))->sortable();
        $grid->column('crop', __('Crop'))->display(function(){
            return $this->get_crop_name();
        })->sortable();
        $grid->column('variety', __('variety'))->sortable();
        $grid->column('district', __('District'))->sortable();
        $grid->column('subcourty', __('Subcounty'))->sortable();
        $grid->column('quantity_planted', __('Quantity planted'))->sortable();
        $grid->column('expected_yield', __('Expected yield'))->hide();
        $grid->column('phone_number', __('Phone number'))->hide();
        $grid->column('gps_latitude', __('Gps latitude'))->hide();
        $grid->column('gps_longitude', __('Gps longitude'))->hide();
        $grid->column('detail', __('Detail'))->hide();

        $grid->column('status_comment', __('Status comment'));

        $grid->column('inspector', __('Inspector'))->display(function ($userId) {
            if (Admin::user()->isRole('basic-user')) {
                return "-";
            }
            $u = Administrator::find($userId);
            if (!$u)
                return "Not assigned";
            return $u->name;
        })->sortable();

        $grid->column('status_comment', __('Status comment'))->hide();

        $grid->column('status', __('Status'))->display(function ($status) {
            return Utils::tell_status($status);
        })->sortable();

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(SubGrower::findOrFail($id));
        //remove delete from show panels
        $show->panel()
            ->tools(function ($tools) use($id) 
            {
            
                $tools->disableDelete();
            
            });
        $subgrower = SubGrower::findOrFail($id);
        if(Admin::user()->isRole('basic-user') ){
            if($subgrower->status == 2 || $subgrower->status == 3 || $subgrower->status == 4 || $subgrower->status == 16){
                \App\Models\MyNotification::where(['receiver_id' => Admin::user()->id, 'model_id' => $id, 'model' => 'SubGrower'])->delete();
            }
        }
        $show->field('created_at', __('Created at'))->as(function ($item) {
            return Carbon::parse($item)->diffForHumans();
        })->sortable();
        $show->field('administrator_id', __('Created by'))->as(function ($user) {
            $_user = Administrator::find($user);
            if (!$_user) {
                return "-";
            }
            return $_user->name;
        });
        $show->field('name', __('Name'));
        $show->field('size', __('Size'));
        $show->field('crop', __('Crop'))->as(function ($crop) {
            return $this->get_crop_name();
        });
        $show->field('district', __('District'));
        $show->field('subcourty', __('Subcouty'));
        $show->field('planting_date', __('Planting date'));
        $show->field('quantity_planted', __('Quantity planted'));
        $show->field('expected_yield', __('Expected yield'));
        $show->field('phone_number', __('Phone number'));
        $show->field('gps_latitude', __('Gps latitude'));
        $show->field('gps_longitude', __('Gps longitude'));
        if($subgrower->detail != null){
        $show->field('detail', __('Detail'));
        }
        $show->field('status', __('Status'))->unescape()->as(function ($status) {
            return Utils::tell_status($status);
        });
        $show->field('inspector', __('Inspector'))->as(function ($userId) {
            if (Admin::user()->isRole('basic-user')) {
                return "-";
            }
            $u = Administrator::find($userId);
            if (!$u)
                return "Not assigned";
            return $u->name;
        });

        $show->field('status_comment', __('Status comment'))->as(function ($comment) {
            if ($comment == null) {
                return "No comment";
            }
            return $comment;
        });
 
        if (Admin::user()->isRole('inspector')){
            //button link to the show-details form
            $show->field('id','Action')->unescape()->as(function ($id) {
                return "<a href='/admin/sub-growers/$id/edit' class='btn btn-primary'>Take Action</a>";
            });
        }

        //disable edit button
        $show->panel()
            ->tools(function ($tools) {
                $tools->disableEdit();
            });
        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new SubGrower());

        //disable delete button
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
        });

        $user = Admin::user();
        $sr4 = Utils::has_valid_sr6();
        if ($form->isCreating()) {
            if (!$sr4) {
                return admin_error("Alert", "You need to be a registered and approved seed grower to apply for field inspection.");
                // return redirect(admin_url('planting-returns'));
            }
        }

        //callback to return to table after form has been saved
        $form->saved(function (Form $form) {
            return redirect(admin_url('sub-growers'));
        });

        if ($form->isCreating()) {
            $form->hidden('administrator_id')->default($user->id);
        };

        if (Admin::user()->isRole('basic-user')) 
        {

            $form->text('name', __('Name'))->default($user->name)->readonly();
            $form->text('size', __('Garden Size (in Accre)'))->required();
            $form->select('variety', 'Crop Variety')->options(CropVariety::all()->pluck('name', 'name'))
                ->required();
            $form->text('field_name', __('Field name'))->required();
            $form->text('district', __('District'))->required();
            $form->text('subcourty', __('Subcourty'))->required();
            $form->text('village', __('Village'))->required();
            $form->text('planting_date', __('Planting date'))->required();
            $form->text('quantity_planted', __('Quantity planted'));
            $form->text('expected_yield', __('Expected yield'));
            $form->text('phone_number', __('Phone number'))->required();
            $form->text('gps_latitude', __('Gps latitude'))->required();
            $form->text('gps_longitude', __('Gps longitude'))->required();
            $form->textarea('detail', __('Detail'));
        }

        if (Admin::user()->isRole('inspector')) 
        {
            $form->saving(function (Form $form) {
                $form->status = 16;
            });

            $id = request()->route()->parameters['sub_grower'];
            $model = $form->model()->find($id);
            $u = Administrator::find($model->administrator_id);
            $form->html('<h3>Initialize inspection</h3>');
            $form->html('<p class="alert alert-info">This inspection form (SR10) has not been inizilized yet. 
            Select initialize below and submit to start inspection process.</p>');

            $form->display('', __('Applicant'))->default($u->name)->readonly();
            $form->display('', __('Person responsible'))->default($model->name)->readonly();
            $form->display('', __('Field name'))->default($model->field_name)->readonly();
            $form->display('', __('District'))->default($model->district)->readonly();
            $form->display('', __('Subcourty'))->default($model->subcourty)->readonly();
            $form->display('', __('Village'))->default($model->village)->readonly();
            $form->display('', __('Crop'))->default($model->crop)->readonly();
            $form->display('', __('Variety'))->default($model->variety)->readonly();
            $form->divider();

            $form->select('seed_class', 'Select Seed Class')->options([
                'Pre-Basic' => 'Pre-Basic',
                'Certified seed' => 'Certified seed',
                'Basic seed' => 'Basic seed',
            ])
                ->required();


            $_items = [];
            $crop_val = "";
            foreach (CropVariety::all() as $key => $item) {
                $_items[$item->id] = "CROP: " . $item->crop->name.", Variety: ".$item->name;
                if ($model->crop == $item->name) {
                    $crop_val = $item->id;
                }
            }



            $form->select('crop', 'Select crop variety')->options($_items)->value($crop_val)
                ->default($crop_val)
                ->required();

                $form->hidden('status', 'Initialize this form');

                $form->html('<input type="checkbox" name="status" value="16" required> Initialize form');

        }
         //footer disable 
            $form->footer(function ($footer) 
            {
                $footer->disableViewCheck();
                $footer->disableEditingCheck();
                $footer->disableCreatingCheck();
                $footer->disableReset();
            });
        return $form;
    }
}
