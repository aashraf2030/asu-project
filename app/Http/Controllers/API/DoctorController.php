<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\MedicalNote;
use App\Models\Ray;
use App\Models\PatientStatus;


class DoctorController extends Controller
{
    public function listPatients()
    {
        $doctorId = Auth::id();

        $patients = User::whereHas('appointmentsAsPatient', function ($query) use ($doctorId) {
            $query->where('doctor_id', $doctorId);
        })->with([
                    'rays',
                    'statuses' => function ($q) use ($doctorId) {
                        $q->where('doctor_id', $doctorId);
                    }
                ])->get();

        return response()->json([
            'message' => 'Patients retrieved successfully.',
            'data' => $patients
        ]);
    }

    public function addNote(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:users,id',
            'note' => 'required|string',
            'ray_id' => 'nullable|exists:rays,id',
        ]);

        $doctorId = Auth::id();

        $note = MedicalNote::create([
            'doctor_id' => $doctorId,
            'patient_id' => $request->input('patient_id'),
            'note' => $request->input('note'),
            'ray_id' => $request->input('ray_id'),
        ]);

        return response()->json([
            'message' => 'Note added successfully.',
            'data' => $note
        ], 201);
    }

    public function updateNote(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string',
        ]);

        $note = MedicalNote::where('id', $id)
            ->where('doctor_id', Auth::id())
            ->first();

        if (!$note) {
            return response()->json(['message' => 'Note not found or not yours.'], 404);
        }

        $note->update([
            'note' => $request->input('note')
        ]);

        return response()->json([
            'message' => 'Note updated successfully.',
            'data' => $note
        ]);
    }

    public function deleteNote($id)
    {
        $note = MedicalNote::where('id', $id)
            ->where('doctor_id', Auth::id())
            ->first();

        if (!$note) {
            return response()->json(['message' => 'Note not found or not yours.'], 404);
        }

        $note->delete();

        return response()->json([
            'message' => 'Note deleted successfully.'
        ]);
    }

    public function getPatientNotes($id)
    {
        $notes = MedicalNote::with('ray')
            ->where('doctor_id', Auth::id())
            ->where('patient_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Notes retrieved successfully.',
            'data' => $notes
        ]);
    }

    public function showRayAI($id)
    {
        $ray = Ray::with(['user'])->find($id);

        if (!$ray) {
            return response()->json(['message' => 'Ray not found.'], 404);
        }

        return response()->json([
            'message' => 'Ray AI result retrieved.',
            'data' => [
                'ray_id' => $ray->id,
                'image_url' => asset('storage/' . $ray->image_path),
                'uploaded_by' => $ray->user->name,
                'temperature' => $ray->temperature,
                'systolic_bp' => $ray->systolic_bp,
                'heart_rate' => $ray->heart_rate,
                'has_cough' => $ray->has_cough,
                'has_headaches' => $ray->has_headaches,
                'can_smell_taste' => $ray->can_smell_taste,
                'ai_status' => $ray->ai_status,
                'ai_summary' => $ray->ai_summary,
                'ai_confidence' => $ray->ai_confidence,
                'differential_diagnosis' => $ray->differential_diagnosis,
                'uploaded_at' => $ray->created_at->toDateTimeString()
            ]
        ]);
    }

    public function setPatientStatus(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:users,id',
            'status' => 'required|in:New,Regular,Follow-up,Critical',
        ]);

        $doctorId = Auth::id();

        $status = PatientStatus::updateOrCreate(
            [
                'doctor_id' => $doctorId,
                'patient_id' => $request->patient_id,
            ],
            [
                'status' => $request->status
            ]
        );

        return response()->json([
            'message' => 'Patient status updated successfully.',
            'data' => $status
        ]);
    }
    public function myNotes(Request $request)
{
    $doctorId = auth()->id();

    $notes = DoctorNote::where('doctor_id', $doctorId)->get();

    return response()->json([
        'message' => 'Doctor notes fetched successfully.',
        'data' => $notes
    ]);
}

}
