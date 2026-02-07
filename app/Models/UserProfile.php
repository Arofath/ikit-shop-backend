<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_image',
        'gender',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'string',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
