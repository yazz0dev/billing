<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;

class HomeController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('admin')) {
                return redirect()->route('admin.dashboard'); // Assuming you name this route
            } elseif ($user->hasRole('staff')) {
                return redirect()->route('staff.pos'); // Assuming you name this route
            }
            // Fallback for other authenticated roles or general dashboard
            return redirect()->route('dashboard');
        }
        return view('home', ['pageTitle' => 'Welcome']);
    }
}