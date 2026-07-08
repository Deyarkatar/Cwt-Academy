<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PendingVerificationController extends Controller
{
    public function index(): View
    {
        return view('auth.verify-email');
    }
}
