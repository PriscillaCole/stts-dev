<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\Request;
use App\Models\Utils;

class RegisterController extends Controller
{
       /**
     * Display register page.
     * 
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('registration.register');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
  
    /**
     * Handle account registration request
     * 
     * 
     * 
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request) 
    {
        $this->validate( $request, [
            'email' => 'required|email:rfc,dns|unique:admin_users,email',
            'username' => 'required|unique:admin_users,username',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|same:password'
         ]);

        $user = Registration::create([
            'name' => $request->fname . ' ' . $request->lname, 
            'email' => $request->email,
            'username' => $request->username,
            'password' => bcrypt($request->password)
        ]);
         
        Utils::add_role($user->id);

        return redirect('/admin/auth/login')->with('success', "Account successfully registered.");
    }
}
