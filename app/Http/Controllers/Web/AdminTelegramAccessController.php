<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TelegramAccessGrant;
use Illuminate\View\View;

class AdminTelegramAccessController extends Controller
{
    public function index(): View
    {
        $grants = TelegramAccessGrant::with(['course', 'courseRequest'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.telegram-access', ['grants' => $grants]);
    }
}
