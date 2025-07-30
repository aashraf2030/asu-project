<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AppointmentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:users,id',
            'appointment_time' => 'required|date_format:Y-m-d H:i:s|after:now',
        ]);

        // التحقق إذا كان الميعاد محجوز بالفعل لنفس الدكتور
        $existing = Appointment::where('doctor_id', $request->doctor_id)
            ->where('appointment_time', $request->appointment_time)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This appointment slot is already booked.'
            ], 409);
        }

        // إنشاء الحجز
        $appointment = Appointment::create([
            'user_id' => Auth::id(),
            'doctor_id' => $request->doctor_id,
            'appointment_time' => $request->appointment_time,
        ]);

        return response()->json([
            'message' => 'Appointment booked successfully.',
            'data' => $appointment
        ], 201);
    }

    public function availableSlots(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:users,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
        ]);

        $doctorId = $request->doctor_id;
        $date = Carbon::parse($request->date);
        $start = $date->copy()->setTime(9, 0);
        $end = $date->copy()->setTime(17, 0);

        $existingAppointments = Appointment::where('doctor_id', $doctorId)
            ->whereDate('appointment_time', $date)
            ->pluck('appointment_time')
            ->map(fn($dt) => Carbon::parse($dt)->format('H:i'));

        $slots = [];
        $period = CarbonPeriod::create($start, '30 minutes', $end->subMinutes(30)); // لتجنب وقت بعد 5

        foreach ($period as $slot) {
            if (!$existingAppointments->contains($slot->format('H:i'))) {
                $slots[] = $slot->format('Y-m-d H:i:s');
            }
        }

        return response()->json([
            'message' => 'Available slots retrieved.',
            'data' => $slots
        ]);
    }

    public function myAppointment()
    {
        $user = Auth::user();

        $appointment = $user->appointmentsAsPatient()
            ->where('status', 'booked')
            ->where('appointment_time', '>=', now())
            ->orderBy('appointment_time', 'asc')
            ->first();

        if (!$appointment) {
            return response()->json([
                'message' => 'No upcoming appointment found.'
            ], 404);
        }

        return response()->json([
            'message' => 'Appointment retrieved successfully.',
            'data' => [
                'id' => $appointment->id,
                'doctor_name' => $appointment->doctor->name,
                'specialty' => $appointment->doctor->specialty,
                'appointment_time' => $appointment->appointment_time,
                'status' => $appointment->status
            ]
        ]);
    }
}
