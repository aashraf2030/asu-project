<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;


class UserController extends Controller
{
    public function listDoctors()
    {
        $doctors = User::where('role', 'doctor')
            ->select('id', 'name', 'specialty')
            ->get();

        return response()->json([
            'message' => 'Doctors retrieved successfully.',
            'data' => $doctors
        ]);
    }
}
