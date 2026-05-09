<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'role',
        'provider',
        'provider_id',
        'is_active',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'provider_id',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected $attributes = [
        'role' => 'customer',
        'provider' => 'local',
        'is_active' => true,
    ];

    public function isSuperAdmin(): bool
    {
        // វាផ្តល់សិទ្ធិជា Super Admin ប្រសិនបើ៖
        // ១. ជា Email ដែលកំណត់ក្នុង .env (Admin គោលមិនអាចលុបបាន)
        // ២. ឬមាន Role ស្មើនឹង 'super_admin' នៅក្នុង Database
        return $this->email === config('app.super_admin_email') || $this->role === 'super_admin';
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function otps()
    {
        return $this->hasMany(Otp::class);
    }

    // Optional: Get the latest OTP for the user
    public function latestOtp()
    {
        return $this->hasOne(Otp::class)->latestOfMany();
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }
}
