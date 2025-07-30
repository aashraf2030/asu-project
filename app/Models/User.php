<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Appointment;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'gender',
        'phone_number',
        'medical_condition',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function rays()
    {
        return $this->hasMany(Ray::class);
    }

    public function appointmentsAsPatient()
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function appointmentsAsDoctor()
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function notesWritten()
    {
        return $this->hasMany(MedicalNote::class, 'doctor_id');
    }

    public function notesReceived()
    {
        return $this->hasMany(MedicalNote::class, 'patient_id');
    }

    public function statuses()
    {
        return $this->hasMany(PatientStatus::class, 'patient_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
