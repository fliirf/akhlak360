<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $request->user()->load(['employee.department', 'employee.position', 'employee.supervisor']);

        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }
}
