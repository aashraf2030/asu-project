<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ray;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RayController extends Controller
{
    /**
     * Display a listing of the user's rays.
     */
    public function index(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $rays = Ray::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'message' => 'Rays retrieved successfully.',
                'data' => $rays
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in index method:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => 'An error occurred while retrieving rays.'
            ], 500);
        }
    }

    /**
     * Store a new ray, send it for AI analysis, and save the results.
     */
    public function store(Request $request)
    {
        try {
            Log::info('Request Data:', $request->all());

            // Validation
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
                'temperature' => 'nullable|numeric|between:30,45',
                'systolic_bp' => 'nullable|integer|between:70,200',
                'heart_rate' => 'nullable|integer|between:40,200',
                'has_cough' => 'nullable|boolean',
                'has_headaches' => 'nullable|boolean',
                'can_smell_taste' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Check if user is authenticated
            if (!Auth::check()) {
                Log::error('User not authenticated');
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Store the image
            try {
                $path = $request->file('image')->store('rays', 'public');
                Log::info('Image stored at path:', ['path' => $path]);
            } catch (\Exception $e) {
                Log::error('Image storage failed:', ['error' => $e->getMessage()]);
                return response()->json([
                    'error' => 'Image upload failed',
                    'message' => 'Could not store the uploaded image.'
                ], 500);
            }

            // Prepare data for database insertion
            $rayData = [
                'user_id' => Auth::id(),
                'image_path' => $path,
                'temperature' => $request->temperature,
                'systolic_bp' => $request->systolic_bp,
                'heart_rate' => $request->heart_rate,
                'has_cough' => $request->boolean('has_cough'),
                'has_headaches' => $request->boolean('has_headaches'),
                'can_smell_taste' => $request->boolean('can_smell_taste'),
            ];

            Log::info('Ray data prepared:', $rayData);

            // Create the ray record
            $ray = Ray::create($rayData);
            Log::info('Ray created with ID:', ['id' => $ray->id]);

            // Send to AI service
            try {
                Log::info('Sending request to AI service...');
                Log::info('Image file details:', [
                    'original_name' => $request->file('image')->getClientOriginalName(),
                    'size' => $request->file('image')->getSize(),
                    'mime_type' => $request->file('image')->getMimeType()
                ]);

                // Test if AI service is reachable
                try {
                    $testResponse = Http::timeout(10)
                        ->withoutVerifying()
                        ->get('https://ai-project-production-e272.up.railway.app/predict');
                    Log::info('AI service reachability test:', ['status' => $testResponse->status()]);
                } catch (\Exception $testError) {
                    Log::warning('AI service reachability test failed:', ['error' => $testError->getMessage()]);
                }

                $response = Http::timeout(60)
                    ->withoutVerifying() // Disable SSL certificate verification
                    ->attach(
                        'file',
                        file_get_contents($request->file('image')),
                        $request->file('image')->getClientOriginalName()
                    )->post('https://ai-project-production-e272.up.railway.app/predict');

                Log::info('AI service response status:', ['status' => $response->status()]);
                Log::info('AI service response headers:', ['headers' => $response->headers()]);
                Log::info('AI service response body:', ['body' => $response->body()]);

                if ($response->successful()) {
                    $predictionData = $response->json();

                    // Validate AI response
                    if (empty($predictionData) || !is_array($predictionData)) {
                        Log::error('Invalid AI response format:', ['response' => $predictionData]);
                        $ray->update([
                            'ai_summary' => 'AI analysis failed - invalid response format'
                        ]);
                    } else {
                        Log::info('AI service response:', $predictionData);

                        // Handle the actual AI service response format
                        $hasPneumonia = $predictionData['has_pneumonia'] ?? false;
                        $confidence = $predictionData['confidence'] ?? 0;

                        // Determine diagnosis based on has_pneumonia flag
                        $diagnosis = $hasPneumonia ? 'Pneumonia' : 'Normal';

                        // Validate confidence value
                        $confidence = is_numeric($confidence) ? max(0, min(1, $confidence)) : 0;

                        // Create a more detailed summary
                        $confidencePercentage = (int)($confidence * 100);
                        $summary = "Diagnosis: {$diagnosis} (Confidence: {$confidencePercentage}%)";

                        if ($hasPneumonia) {
                            $summary .= " - Pneumonia detected in the chest X-ray.";
                        } else {
                            $summary .= " - No pneumonia detected in the chest X-ray.";
                        }

                        $updateData = [
                            'ai_status' => $diagnosis,
                            'ai_summary' => $summary,
                            'ai_confidence' => $confidencePercentage,
                        ];

                        $ray->update($updateData);
                        Log::info('Ray updated with AI results', $updateData);
                    }

                } else {
                    Log::error('AI API Error Response:', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);

                    $ray->update([
                        'ai_summary' => 'AI analysis failed - please try again'
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('AI Service Connection Error:', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                $ray->update([
                    'ai_summary' => 'AI service temporarily unavailable - ' . $e->getMessage()
                ]);
            }

            return response()->json([
                'message' => 'Ray uploaded and analysis completed.',
                'data' => $ray->fresh()
            ], 201);

        } catch (\Exception $e) {
            Log::error('General Error in store method:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => 'An error occurred while processing your request.'
            ], 500);
        }
    }

    /**
     * Display the specified ray.
     */
    public function show($id)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $ray = Ray::where('user_id', Auth::id())
                ->where('id', $id)
                ->first();

            if (!$ray) {
                return response()->json(['error' => 'Ray not found'], 404);
            }

            return response()->json([
                'message' => 'Ray retrieved successfully.',
                'data' => $ray
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in show method:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => 'An error occurred while retrieving the ray.'
            ], 500);
        }
    }

    /**
     * Remove the specified ray from storage.
     */
    public function destroy($id)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $ray = Ray::where('user_id', Auth::id())
                ->where('id', $id)
                ->first();

            if (!$ray) {
                return response()->json(['error' => 'Ray not found'], 404);
            }

            // Delete the image file
            if ($ray->image_path && Storage::disk('public')->exists($ray->image_path)) {
                Storage::disk('public')->delete($ray->image_path);
            }

            $ray->delete();

            return response()->json([
                'message' => 'Ray deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in destroy method:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => 'An error occurred while deleting the ray.'
            ], 500);
        }
    }
}
