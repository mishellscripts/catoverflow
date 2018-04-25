<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users and validates new
    | user information.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'username'   => 'required|string|max:20|unique:users',
            'first_name' => 'string|max:50',
            'last_name'  => 'string|max:50',
            'email'      => 'required|string|email|max:100|unique:users',
            'password'   => 'required|string|min:6|confirmed',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        Session::flash('success', 'Registration Successful!');

        //TODO: check api_token
        return User::create([
            'username'        => $data['username'],
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'last_login_date' => date('Y-m-d H:i:s'),
            'last_login_ip'   => \Request::ip(),
            'api_token'       => str_random(60),
        ]);
    }

    /**
     * Override register() method in RegistersUsers to prevent auto-login after register.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return function
     */
    public function register(\Illuminate\Http\Request $request)
    {
        $this->validator($request->all())->validate();

        $this->create($request->all());

        return redirect()->route('login');
    }
}
