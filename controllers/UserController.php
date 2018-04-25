<?php

namespace App\Http\Controllers;

use Auth;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | User Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the API requests for viewing user information,
    | updating user information, and deletion of user.
    |
    */

    public function index() 
    {
        $user = Auth::user();

        return view('edit', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);

        // Load view and pass the user
        return view('edit', compact('user'));
    }

    /**
     * Get a validator for an incoming create or update requests.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $input, $id)
    {
        return Validator::make($input, [
            'first_name' => 'string|max:50',
            'last_name' => 'string|max:50',
            'email' => [
                'string',
                'email',
                'max:100',
                Rule::unique('users')->ignore($id),
            ],
            'password' => 'nullable|string|min:6|confirmed',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $input = $request->except(['password_confirmation', '_token', '_method']);
        $this->validator($request->all(), $id)->validate();
        $user = User::find($id);

        foreach ($input as $key => $value) {
            // Change filled out inputs that are different values from before
            if ($value && $user->key != $value) {
                $user->$key = $key=='password' ? Hash::make($value) : $value;
            }
        }
        $user->save();
        Session::flash('success', 'Settings changed successfully!');

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        $user->delete();

        return response()->json(User::all());
    }
}
