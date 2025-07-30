<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Ray extends Model
{
     use HasFactory;

    protected $fillable = [
        'user_id', 'image_path', 'temperature', 'systolic_bp', 'heart_rate',
        'has_cough', 'has_headaches', 'can_smell_taste', 'ai_status',
        'ai_summary', 'differential_diagnosis', 'ai_confidence'
    ];

    protected $casts = [
        'differential_diagnosis' => 'array',
        'has_cough' => 'boolean',
        'has_headaches' => 'boolean',
        'can_smell_taste' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function note()
    {
        return $this->hasOne(MedicalNote::class);
    }
}
