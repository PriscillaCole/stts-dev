<?php

namespace App\Admin\Controllers;

use App\Models\Crop;
use App\Models\CropVariety;
use App\Models\FormSr4;
use App\Models\FormSr6;
use App\Models\ImportExportPermit;
use App\Models\Utils;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Admin\Actions\Post\Renew;


class ImportExportPermitController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Import Permit';


    /**
     * Make a grid builder.
     *  
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ImportExportPermit());
          //organize the grid in descending order of created_at
          $grid->model()->orderBy('created_at', 'desc');

        //disable batch delete
        $grid->batchActions(function ($batch) 
        {
            $batch->disableDelete();
        });

        //customise filter
        $grid->filter(function($filter){

            // Remove the default id filter
            $filter->disableIdFilter();
        
            // Add a column filter
            $filter->like('name', 'Name');
            
        
            //... additional filter options
        });

        $grid->disableExport();
        $grid->model()->where('is_import', '=', 1);

        //check if the role is an inspector and has been assigned that form
        if (Admin::user()->isRole('inspector')) 
        {
            $grid->model()->where('inspector', '=', Admin::user()->id);
            //return an empty table if the inspector has not been assigned any forms
            if (ImportExportPermit::where('inspector', '=', Admin::user()->id)->count() == 0) 
            { 
                //return an empty table if the inspector has not been assigned an
                $grid->model(0);
                   
            }
        }

        if (Admin::user()->isRole('basic-user')) 
        {
            $grid->model()->where('administrator_id', '=', Admin::user()->id);
            $grid->actions(function ($actions)
            {
                $status = ((int)(($actions->row['status'])));
                if (
                    $status == 2 ||
                    $status == 5 ||
                    $status == 4 ||
                    $status == 3 ||
                    $status == 6
                ) {
                    $actions->disableEdit();
                    $actions->disableDelete();
                }
                if (
                    $status == 3
                ){
                    $actions->disableDelete();
                }
                if(Utils::check_expiration_date('ImportExportPermit',$this->getKey())){
                        
                    $actions->add(new Renew(request()->segment(count(request()->segments()))));
                
                };
            });
        } 
        else if (Admin::user()->isRole('inspector')|| Admin::user()->isRole('admin')) 
        {
            $grid->disableCreateButton();
            $grid->actions(function ($actions) {
                $status = ((int)(($actions->row['status'])));
                $actions->disableDelete();
                $actions->disableEdit();
               
            });
            $grid->filter(function ($search_param) {
                $search_param->disableIdfilter();
                $search_param->like('name', __("Search by Name"));
                $search_param->like('status', __("Search by Status"));
            });

        } 
        else
        {
            $grid->disableCreateButton();
        }

        $grid->column('created_at', __('Created'))
            ->display(function ($item) 
            {
                return Carbon::parse($item)->diffForHumans();
            })->sortable();
        $grid->column('name', __('Name'));
        $grid->column('telephone', __('Telephone'));
        $grid->column('quantiry_of_seed', __('Quantity of seed'));
        $grid->column('ista_certificate', __('Type Of Certificate'))->sortable();


        if(!Admin::user()->isRole('basic-user'))
        {
            $grid->column('inspector', __('Inspector'))->display(function ($userId) 
            {
                $u = Administrator::find($userId);
                if (!$u)
                    return "Not assigned";
                return $u->name;
            })->sortable();
        }

       //check if the status is expired or not
        $grid->column('status', __('Status'))->display(function ($status) 
        {
            // check expiration date
             if (Utils::check_expiration_date('ImportExportPermit',$this->getKey())) 
             {
                 return Utils::tell_status(6);
             } else{
                 return Utils::tell_status($status);
             }
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
        $import_permit = ImportExportPermit::findOrFail($id);
        //delete the notification from the database once a user views the form
        if(Admin::user()->isRole('basic-user') )
        {
            if($import_permit->status == 2 || $import_permit->status == 3 || $import_permit->status == 4 || $import_permit->status == 5)
            {
                \App\Models\MyNotification::where(['receiver_id' => Admin::user()->id, 'model_id' => $id, 'model' => 'ImportExportPermit'])->delete();
            }
        }

        $show = new Show(ImportExportPermit::findOrFail($id));
        $show->panel()
            ->tools(function ($tools) 
            {
                $tools->disableEdit();
                $tools->disableDelete();
            });

        $show->field('created_at', __('Created'))
            ->as(function ($item) 
            {
                if (!$item) 
                {
                    return "-";
                }
                return Carbon::parse($item)->diffForHumans();
            });
        $show->field('administrator_id', __('Created by id'))
            ->as(function ($userId) 
            {
                $u = Administrator::find($userId);
                if (!$u)
                    return "-";
                return $u->name;
            });

        $show->field('type', __('Permit category'));
        $show->field('name', __('Name'));
        $show->field('address', __('Address'));
        $show->field('telephone', __('Telephone'));
        $show->field('national_seed_board_reg_num', __('National seed board reg num'));
        $show->field('store_location', __('Store location'));
        $show->field('quantiry_of_seed', __('Quantity of seed'));
        $show->field('name_address_of_origin', __('Name address of origin'));

        //show table of crops associated with an import permit
    

        $show->field('import_export_permits_has_crops', __('Crops'))
            ->unescape()
            ->as(function ($item) 
            {
                if (!$this->import_export_permits_has_crops) 
                {
                    return "None";
                }

                $headers = ['Crop',  'Category', 'Weight'];
                $rows = array();
                foreach ($this->import_export_permits_has_crops as $key => $val) 
                {
                    $row['crop'] = $val->variety->crop->name;
                    $row['variety'] = $val->variety->name;
                    $row['weight'] = $val->weight;
                    $rows[] = $row;
                }

                $table = new Table($headers, $rows);
                return $table;
            });
        
        $show->field('ista_certificate', __('Ista certificate'));
        if($import_permit->permit_number != null)
        {
           $show->field('permit_number', __('Permit number'));
        }
    
        $show->field('status', __('Status'))->unescape()->as(function ($status) 
        {

            return Utils::tell_status($status);
        });

        $show->field('status_comment', __('Comment'));

         //check if valid_from , valid_until are empty,if they are then dont show them
         if ($import_permit->valid_from != null) 
         {
            $show->field('valid_from', __('Valid from'));
        }
        if ($import_permit->valid_until != null) {
            $show->field('valid_until', __('Valid until'));
        }

        if (!Admin::user()->isRole('basic-user'))
        {
            //button link to the show-details form
            //check the status of the form being shown
            if($import_permit->status == 1 || $import_permit->status == 2 || $import_permit->status == null)
            {
            $show->field('id','Action')->unescape()->as(function ($id) 
            {
                return "<a href='/admin/import-export-permits/$id/edit' class='btn btn-primary'>Take Action</a>";
            });
            }
        }
    
        if (Admin::user()->isRole('basic-user')) 
        {
            if(Utils::is_form_halted('ImportExportPermit'))
            {
                $show->field('id','Action')->unescape()->as(function ($id)
                {
                    return "<a href='/admin/import-export-permits/$id/edit' class='btn btn-primary'>Take Action</a>";
                });
            }
        }

        return $show;
    }


    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ImportExportPermit());

        // callback after save to return to the table view
        $form->saved(function (Form $form) 
        {
            return redirect(admin_url('import-export-permits'));
        });

        if($form->isEditing())
        {
            //find the import permit id, if its status is not pending, block an inspector from editing and disable form actions
            $import_export_permit = ImportExportPermit::find(request()->route()->parameters()['import_export_permit']);
            if(Admin::user()->isRole('inspector')){
                if($import_export_permit->status != 2){
                   $form->html('<div class="alert alert-danger">You cannot edit this form, please commit the commissioner to make any changes. </div>');
                   $form->footer(function ($footer) {

                       // disable reset btn
                       $footer->disableReset();

                       // disable submit btn
                       $footer->disableSubmit();

                       // disable `View` checkbox
                       $footer->disableViewCheck();

                       // disable `Continue editing` checkbox
                       $footer->disableEditingCheck();

                       // disable `Continue Creating` checkbox
                       $footer->disableCreatingCheck();

                   });
                }
            }
        }

        if ($form->isCreating()) 
        {
          //check the status of the form before allowing a user to create a new one
            if (!Utils::can_create_import()) 
            {
                return admin_warning("Warning", "You cannot create a new import permit request form  while still having a PENDING one.");
                
            }
            if (Utils::can_renew_iform('ImportExportPermit')) 
            {
                return admin_warning("Warning", "You cannot create a new import form  while still having a valid one.");

            }

         
        }
         // callback after save
        $form->saved(function (Form $form) 
        {
        //return to table view controller after saving the form data 
            return redirect(admin_url('import-export-permits'));
        });


      

        //customize the form features

            $form->setWidth(8, 4);
            $form->disableCreatingCheck();
            $form->tools(function (Form\Tools $tools) 
            {
                $tools->disableDelete();
                $tools->disableView();
            });

            $form->footer(function ($footer) 
            {
                $footer->disableReset();
                $footer->disableViewCheck();
                $footer->disableEditingCheck();
                $footer->disableCreatingCheck();
            });

            $user = Auth::user();
            $form->hidden('is_import', __('is_import'))->default(1)->value(1)->required();

            if ($form->isCreating()) 
            {
                $form->hidden('administrator_id', __('Administrator id'))->value($user->id);
            } 
            else 
            {
                $form->hidden('administrator_id', __('Administrator id'));
            }
             
        
        //basic-user forms
        if (Admin::user()->isRole('basic-user')) 
        {
            $application_category = Utils::check_application_category();

            $form->radio('type', __('Application Category?'))
                ->options([
                    'Seed Merchant' => 'Seed Merchant',
                    'Seed Producer' => 'Seed Producer',
                    'Seed Stockist' => 'Seed Stockist',
                    'Seed Importer' => 'Seed Importer',
                    'Seed Exporter' => 'Seed Exporter',
                    'Seed Processor' => 'Seed Processor',
                    'Researchers' => 'Researchers',

                ])
                ->required()->default($application_category)
                ->help('Which SR4 type are you applying for?')

            // 1.check if there exists a valid SR4
            // 2.check if the application category in the sr4 matches the selected one
            // 3.render the form in show_fields function
            
            ->when('Seed Producer', function (Form $form) 
            {
                
                $sr4 = Utils::has_valid_sr4();
                $application_category = Utils::check_application_category();
                $user = Auth::user();
                if ($sr4 != null) 
                {
                    if($application_category == 'Seed Producer')
                    {
                        $this->show_fields($form,$user,$sr4);

                    }
                    else
                    {

                        $form->html('<div class="alert alert-danger">The selected application category doesn\'t match the one in your Sr4 form.Please clarify that </div>');
                    }
                }
                else
                {
                    $form->html('<div class="alert alert-danger">You cannot create a new import permit request if don\'t have a valid SR4 form </div>');
                }
            })


            ->when('Seed Merchant', function (Form $form) 
            {
                
                $sr4 = Utils::has_valid_sr4();
                $application_category = Utils::check_application_category();
                $user = Auth::user();
                if ($sr4 != null) 
                {
                    if($application_category == 'Seed Merchant')
                    {
                        $this->show_fields($form,$user,$sr4);
                
                    }
                    else
                    {
                        $form->html('<div class="alert alert-danger">The selected application category doesn\'t match the one in your Sr4 form.Please clarify that </div>');
                    }
                }
                else
                {
                        $form->html('<div class="alert alert-danger">You cannot create a new import permit request if don\'t have a valid SR4 form </div>');
                }
            })


            ->when('Seed Stockist', function (Form $form) 
            {

                $sr4 = Utils::has_valid_sr4();
                $application_category = Utils::check_application_category();
                $user = Auth::user();
                if ($sr4 != null) 
                {
                        if($application_category == 'Seed Stockist')
                        {
                            $this->show_fields($form,$user,$sr4);
                    
                        }
                        else
                        {
                            $form->html('<div class="alert alert-danger">The selected application category doesn\'t match the one in your Sr4 form.Please clarify that </div>');
                        }
                }
                else
                {          
                    $form->html('<div class="alert alert-danger">You cannot create a new import permit request if don\'t have a valid SR4 form </div>');
                    
                }
            })


            ->when('Seed Importer', function (Form $form) 
            {
            
                $sr4 = Utils::has_valid_sr4();
                $application_category = Utils::check_application_category();
                $user = Auth::user();
                
                if ($sr4 != null) 
                {
                    if($application_category == 'Seed Importer')
                    {
                         $this->show_fields($form,$user,$sr4);

                    }
                    else
                    {

                         $form->html('<div class="alert alert-danger">The selected application category doesn\'t match the one in your Sr4 form.Please clarify that </div>');
                    }
                }
                else
                {
                    $form->html('<div class="alert alert-danger">You cannot create a new import permit request if don\'t have a valid SR4 form </div>');
                }
            })


            ->when('Seed Exporter', function (Form $form) 
            {
                $sr4 = Utils::has_valid_sr4();
                $application_category = Utils::check_application_category();
                $user = Auth::user();
                if ($sr4 != null) 
                {
                    if($application_category == 'Seed Exporter')
                    {
                        $this->show_fields($form,$user,$sr4);
                    }
                    else
                    {
                        $form->html('<div class="alert alert-danger">The selected application category doesn\'t match the one in your Sr4 form.Please clarify that </div>');
                    }
                }
                else
                {
                    $form->html('<div class="alert alert-danger">You cannot create a new import permit request if don\'t have a valid SR4 form </div>');
                }
            })
                        

            ->when('Seed Processor', function (Form $form) 
            {
                $sr4 = Utils::has_valid_sr4();
                $application_category = Utils::check_application_category();
                $user = Auth::user();
                if ($sr4 != null) 
                {
                    if($application_category == 'Seed Processor')
                    {
                    $this->show_fields($form,$user,$sr4);

                    }
                    else
                    {

                    $form->html('<div class="alert alert-danger">The selected application category doesn\'t match the one in your Sr4 form.Please clarify that </div>');
                    }
                }
                else
                {
                    $form->html('<div class="alert alert-danger">You cannot create a new import permit request if don\'t have a valid SR4 form </div>');
                }
            })

                            
            ->when('Researchers', function (Form $form) 
            {
                $sr4 = Utils::has_valid_sr4();
                $user = Auth::user();
                if ($sr4 == null) 
                {
                        $this->show_fields($form,$user,$sr4);

                }
                else
                {

                    $form->html('<div class="alert alert-danger">Seems you have a valid SR4,please choose the application category in the form. </div>');
                }   
            });
                            

        } 

        //admin form fields
        if (Admin::user()->isRole('admin')) 
        {
            //$form->file('ista_certificate', __('Ista certificate'))->required();
            $form->text('name', __('Name of applicant'))->default($user->name)->readonly();
            $form->text('telephone', __('Telephone'))->readonly();
            $form->text('address', __('Address'))->readonly();
            $form->text('store_location', __('Store location'))->readonly();
            $form->divider();
            $form->radio('status', __('Action'))
                ->options([
                    '2' => 'Assign inspector',
                ])
                ->required()
                ->when('2', function (Form $form) 
                {
                    $items = Administrator::all();
                    $_items = [];

                    foreach ($items as $key => $item) 
                    {
                        if (!Utils::has_role($item, "inspector")) 
                        {
                            continue;
                        }
                        $_items[$item->id] = $item->name . " - " . $item->id;
                    }
                    $form->select('inspector', __('Inspector'))
                        ->options($_items)
                        ->help('Please select inspector')
                        ->rules('required');
                });
                
        }

        //inspector form fields
        if (Admin::user()->isRole('inspector')) 
        {

            $form->text('name', __('Name of applicant'))->default($user->name)->readonly();
            $form->text('telephone', __('Telephone'))->readonly();
            $form->text('address', __('Address'))->readonly();
            $form->text('store_location', __('Store location'))->readonly();
            $form->text('other_varieties', __('Other varieties'))->readonly();
            $form->divider();

            $form->radio('status', __('Status'))
                ->options([
                    '3' => 'Halted',
                    '4' => 'Rejected',
                    '5' => 'Accepted',
                ])
                ->required()
            
                ->when('in', [3, 4], function (Form $form) 
                {
                    $form->textarea('status_comment', 'Enter status comment (Remarks)')
                        ->help("Please specify with a comment");
                })
                ->when('5', function (Form $form) 
                {
                    $form->text('permit_number', __('Permit number'))
                        ->help("Please Enter Permit number")
                        ->default("Import" ."/". date('Y') ."/". mt_rand(10000000, 99999999))->readonly();
                    $form->date('valid_from', 'Valid from date?');
                    $form->date('valid_until', 'Valid until date?');
                });

        }


            return $form;
    }

    //form function
    public function show_fields($form, $user, $sr4)
    {
        
        $form->text('name', __('Applicant Name'))->default($user->name)->readonly();
        $form->text('address', __('Postal Address'))->required();
        $form->text('telephone', __('Phone Number'))->required();  
        if ($sr4->seed_board_registration_number != null) 
        {
            $seed_board_registration_number = $sr4->seed_board_registration_number;
            $form->text("national_seed_board_reg_num", __('National Seed Board Registration Number'))
                ->default($seed_board_registration_number)
                ->readonly(); 
        }      
        $form->text('store_location', __('Location of the store'))->required();
        $form->number( 'quantiry_of_seed', __('Quantity of seed of the same variety held in stock') )
            ->help("(metric tons)")
            ->required();
        $form->text('name_address_of_origin', __('Name and address of origin'))
            ->required();

        $form->tags('ista_certificate', __('Type Of Certificate'))
            ->required()
            ->options(['ISTA certificate', 'Phytosanitary certificate']);

        $form->html('<h3>I or We wish to apply for a license to import seed as indicated below:</h3>');

        $form->radio('crop_category', __('Category'))
            ->options
            ([
                'Commercial' => 'Commercial',
                'Research' => 'Research',
                'Own use' => 'Own use',
            ])->stacked()
            ->required();

        $form->hasMany('import_export_permits_has_crops', __('Click on "New" to Add Crop varieties '), function (NestedForm $form) 
        {
            //access the crop varities in the variety table
            $_items = [];

            foreach (CropVariety::all() as $key => $item) 
            {
                $_items[$item->id] = "CROP: " . $item->crop->name . ", VARIETY: " . $item->name;
            }

            $form->select('crop_variety_id', 'Add Crop Variety')->options($_items)
                ->required();
                
            $form->textarea('other_varieties', __('Specify other varieties if any.') )
            ->help('If varieties you are applying for were not listed');
            $form->radio('measure', __('Weight Measurement'))
                ->options
                ([
                    'Kgs' => 'Kgs',
                    'Metric Tons' => 'Metric Tons',
                ])
                ->required();
                $form->number('weight','Weight')
                ->required();

            
                   
        });
        return $form;
    }

    
}
