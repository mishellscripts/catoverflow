<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\OriginalVideo;
use App\Http\Resources\OriginalVideo as OriginalVideoResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $videos = OriginalVideo::where('user_id', Auth::user()->id)->get();

        return view('home', [
            'token' => Auth::user()->api_token,
            'videos' => OriginalVideoResource::collection($videos),
        ]);
    }
}
