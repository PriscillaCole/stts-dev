<?php

namespace App\Admin\Controllers;

use App\Models\CropVariety;
use App\Models\MarketableSeed;
use App\Models\Utils;

Utils::start_session();

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Auth;


class OrderController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Order';


    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Order());

        if (!Admin::user()->isRole('admin')) 
        {
            $grid->model()
                ->where([
                    'administrator_id' => Admin::user()->id,
                ])
                ->orWhere([
                    'order_by' => Admin::user()->id,
                ]);
        };
        $grid->disableExport();
        $grid->disableBatchActions();
        $grid->disableTools();
        $grid->disableColumnSelector();
        $grid->disableCreateButton();

        //check the status of the order and disable the edit and delete actions
        $grid->actions(function ($actions) 
        {
            $status = ((int)(($actions->row['status'])));
            if (
                $status != 1) 
            {
                $actions->disableEdit();
                $actions->disableDelete();
            }else
            {
                if (Utils::check_order()) 
                    {
                       $actions->disableDelete();
                    
                    }
            }
        });

   

        $grid->column('created_at', __('Created'))->display(function ($t) 
        {
            return Carbon::parse($t)->toFormattedDateString();
        })->sortable();
        $grid->column('order_by', __('Order by'))
            ->display(function ($id) 
            {
                if ($id == Admin::user()->id) 
                {
                    return "Me";
                }
                $u = Administrator::find($id);
                if (!$u) 
                {
                    return "-";
                }
                return $u->name;
            })->sortable();
        $grid->column('crop_variety_id', __('Product'))
            ->display(function ($id) 
            {
                $u = CropVariety::find($id);
                if (!$u) {
                    return "-";
                }
                return $u->name;
            })->sortable();

        $grid->column('quantity', __('Quantity(metric tons)'))->sortable();
        $grid->column('total_price', __('Total price'))
            ->display(function ($id) 
            {
                return "UGX. " . number_format($id);
            })->sortable();

        $grid->column('status', __('Status'))
            ->display(function ($status) 
            {
                return  Utils::tell_order_status($status);
            })->sortable();
       if(Utils::check_order_status())
       {
                //confirm order button
                $grid->column('id', __('Confirm Order'))->display(function ($id) 
                {
                    $order = Order::findOrFail($id);
                    $confirmedClass = $order->status == 6 ? 'btn-primary' : 'btn-blue';
                    $confirmedText = $order->status == 6 ? 'Confirmed' : 'Confirm';
                    if($order->status != 3) 
                    {
                        return "<a id='confirm-order-{$id}' href='" . route('orders.confirm', ['id' => $id]) . "' class='btn btn-xs $confirmedClass confirm-order' data-id='{$id} ' disabled>$confirmedText</a>";
                    }
                    return "<a id='confirm-order-{$id}' href='" . route('orders.confirm', ['id' => $id]) . "' class='btn btn-xs $confirmedClass confirm-order' data-id='{$id}'>$confirmedText</a>";
                })->sortable();
                
                // css styling the button to blue initially
                Admin::style('.btn-blue {color: #fff; background-color: #0000FF; border-color: #0000FF;}');
                
                //Script to edit the form status field to 2 on click of the confirm order button
                Admin::script
                ('
                    $(".confirm-order").click(function(e) 
                    {
                        e.preventDefault();
                        var id = $(this).data("id");
                        var url = "' . route('orders.confirm', ['id' => ':id']) . '";
                        url = url.replace(":id", id);
                        var button = $("#confirm-order-" + id);
                        $.ajax(
                            {
                                url: url,
                                type: "PUT",
                                data: 
                                {
                                    _method: "PUT",
                                    _token: LA.token,
                                    status: 6,
                                },
                                success: function (data) 
                                {
                                    $.pjax.reload("#pjax-container");
                                    toastr.success("Order confirmed successfully");
                    
                                }
                            });
                    });
                ');
                        
            
        }
        
        return $grid;
    }

    public function confirm($id)
    {
        $order = Order::findOrFail($id);
        $order->status = 6; // or whatever status code you need
        $order->save();
        return response()->json(['status' => 'success']);
    }
    

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Order::findOrFail($id));
        //disable panel actions
        $show->panel()->tools(function ($tools) 
        {
            $tools->disableDelete();
        });
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('administrator_id', __('Administrator id'))->as(function ($id) 
        {
            $u = Administrator::find($id);
            if (!$u) 
            {
                return "-";
            }
            return $u->name;
        });
        $show->field('order_by', __('Order by'))->as(function ($id) 
        {
            $u = Administrator::find($id);
            if (!$u) 
            {
                return "-";
            }
            return $u->name;
        });
       
        $show->field('product_id', __('Product'))->as(function ($id) 
        {
            $u = Product::find($id);
            if (!$u) 
            {
                return "-";
            }
            return $u->name;
        });
        $show->field('quantity', __('Quantity'));
        $show->field('detail', __('Detail'));
        $show->field('payment_type', __('Payment type'));
        $show->field('receipt', __('Receipt'));
        $show->field('status', __('Status'))->unescape()->as(function ($status) 
        {
            return  Utils::tell_order_status($status);
        })->sortable();

        return $show;
    }


    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Order());

        //disable the delete tools
        $form->tools(function (Form\Tools $tools) 
        {
            $tools->disableDelete();
        });


        if ($form->isEditing()) 
        {

            $form->saved(function ($form) 
            {
                return redirect(admin_url('orders'));
            });

            $form->saving(function ($form) 
            {
                $id = request()->route()->parameters['order'];
                $order = $form->model()->find($id);
                if (!$order) 
                {
                    dd("Order not found");
                }

                $product = Product::find($order->product_id);
                if (!$product) 
                {
                    die("Product not found");
                }
                
                //convert $order->quantity from metric tons to kgs
                $order_quantity = $order->quantity * 1000;
                if ($order_quantity > $product->available_stock) 
                {
                    admin_error('Ooops', 'You have inadequate amount of product (' . $product->name . ") to proceed with this 
                    order ");
                    
                }


                if ($form->status == 3) 
                {
                    //update the available_stock in the products table by getting the quantity entered and subtracting it from the available stock
                    //and save it
                    $bought_quantity =  $form->quantity;
                    //convert the quantity from metric tons to kgs
                    $bought_quantity = $bought_quantity * 1000;
                    $new_quantity = $product->available_stock - $bought_quantity;
                    
                    $product->available_stock = $new_quantity;
                    $product->update();

                    //update marketable price stock in the marketable seed table
                    $marketable_seed = MarketableSeed::where('seed_lab_id', $product->seed_lab_id)->first();
                    if ($marketable_seed) 
                    {
                        $marketable_seed->quantity = $marketable_seed->quantity - intval($new_quantity/1000);
                        $marketable_seed->update();
                    }
                    
                }
            });

            $id = request()->route()->parameters['order'];
            $product = $form->model()->find($id);
            if (!$product) {
                dd("Order not found.");
            }
            $users = [];
            foreach (Administrator::all() as $key => $value) {
                $users[$value->id] = $value->name . " - " . $value->id;
            }

            $form->select('administrator_id', __('Seller'))
                ->options($users)
                ->value($product->administrator_id)
                ->readonly()
                ->default($product->administrator_id);

            $form->select('order_by', __('Buyer'))
                ->options($users)
                ->value(Admin::user()->id)
                ->readonly()
                ->default(Admin::user()->id);
            $product = Product::find($product->product_id);

            if ($product) {
                $form->select('crop_variety_id', __('Crop'))
                    ->options([
                        $product->crop_variety_id => $product->name
                    ])
                    ->value($product->crop_variety_id)
                    ->readonly()
                    ->default($product->crop_variety_id);
                $form->hidden('product_id', __('Product id'))->default($product->id);
            }


            // $form->display('quantity', __('Available quantity'))->default(
            //     number_format($product->available_stock) . " bags"
            // );
            if ($product) {
                $form->display('price', __('Unit price'))->default(
                    "UGX. " . number_format($product->price)
                );
                $form->text('quantity', __('Quantity Ordered'))->default(
                    number_format($product->quantity) . " bags"
                );
            }
        

            $form->divider();

            if (!$product) {
                $id = request()->route()->parameters['order'];
                $order = $form->model()->find($id);
                if (!$order) {
                    dd("Order not found");
                }
                $pros = Product::where([
                    'crop_variety_id' => $order->crop_variety_id,
                    'administrator_id' => Auth::user()->id,
                ])->get();

                $items = array();
                foreach ($pros as $key => $pro) {
                    $items[$pro->id] = $pro->name . ", QTY: " . $pro->quantity;
                }
                if (count($items) < 1) 
                {
                    $var  = CropVariety::find($order->crop_variety_id);
                    admin_warning("Warning", "You have insufficient amount of {$var->name} to supply.");

                    $form->footer(function ($footer) 
                    {

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
                    return $form;
                }
                $form->select('product_id', "Select for your products")
                    ->options($items)
                    ->required();
                 
               
            }
                if ($product->order_by != Admin::user()->id) {
                        
                        $form->radio('status', "Update order status")
                            ->options([
                                '5' => 'Processing',
                                '2' => 'Shipping',
                                '3' => 'Delivered',
                                '4' => 'Canceled',
                            ])
                            ->help("Once you mark this ordered as completed, you cannot reverse the process.")
                            ->required();
                } else {
                    admin_warning("Warning", "You cannot update status of your own order.");
                    
                }
           // }
        }

        if ($form->isCreating()) 
        {
            $form->saving(function ($new_order) {
                $id = $_SESSION['product_id'];
                $pro =  Product::find($id);
                //$new_order = new Order();
                $new_order->administrator_id = $_POST['administrator_id'];
                $new_order->crop_variety_id = $_POST['crop_variety_id'];
                $new_order->product_id = $_POST['product_id'];
                $new_order->quantity = $_POST['quantity'];
                $new_order->detail = $_POST['detail'];
                $new_order->payment_type = null;
                $new_order->status = 1;
                $new_order->total_price = ($new_order->quantity * 1000) * $pro->price;
                $new_order->order_by = Admin::user()->id;
                unset($_SESSION['product_id']);
                admin_success("Success", "Order submited successfully.");

                if($new_order->quantity > $pro->quantity){
                    return  response(' <p class="alert alert-warning"> You cannot make an order more than what is in stock. <a href="/admin/products"> Go Back </a></p> ');
                }
            });


            $id = 0;
            if (isset($_GET['id'])) {
                $id = (int)($_GET['id']);
                $_SESSION['product_id'] = $id;
                header("Location: " . admin_url('orders/create'));
                die();
            }


            if (isset($_SESSION['product_id'])) {
                $id = $_SESSION['product_id'];
            }


            if ($id < 1) {
                dd("pro not found");
                return ("Product ID not found. You have to create new order from market place.");
            }
            $pro =  Product::find($id);
            if (!$pro) {
                return "Product not found.";
            }

            if ($pro->administrator_id == Admin::user()->id) {
                return "You cannot order for your own product.";
            }

            if ($pro->quantity < 1) {
                return admin_error('Out of sock.', "This product is out of stock.");
            }

            $users = [];
            foreach (Administrator::all() as $key => $value) {
                $users[$value->id] = $value->name . " - " . $value->id;
            }

            $form->select('administrator_id', __('Seller'))
                ->options($users)
                ->value($pro->administrator_id)
                ->readonly()
                ->default($pro->administrator_id);

            $form->select('order_by', __('Buyer'))
                ->options($users)
                ->value(Admin::user()->id)
                ->readonly()
                ->default(Admin::user()->id);
            $form->select('crop_variety_id', __('Crop'))
                ->options([
                    $pro->crop_variety_id => $pro->name
                ])
                ->value($pro->crop_variety_id)
                ->readonly()
                ->default($pro->crop_variety_id);

            $form->hidden('product_id', __('Product id'))->default($pro->id);
            $form->display('quantity', __('Available stock'))->default(
                number_format($pro->quantity) . " metric tons "
            );
            $form->display('price', __('Unit price'))->default(
                "UGX. " . number_format($pro->price)
            );

            $form->divider();


            $form->number('quantity', __('Order quantity'))->required()
                ->value($pro->quantity)
                ->default($pro->quantity)
                ->help("MAX: " . number_format($pro->quantity) . " metric tons")
                ->attribute('type', 'number');

            $form->textarea('detail', __('Extra note'))
                ->help("Optional");
            $form->hidden('status', __('Status'))->default(1)->value(1);
            $form->hidden('total_price', __('total_price'))->default(1)->value(1);
        }



        $form->footer(function ($footer) {
            $footer->disableViewCheck();
            $footer->disableEditingCheck();
            $footer->disableCreatingCheck();
            $footer->disableReset();
        });
    

        return $form;
    }
}
