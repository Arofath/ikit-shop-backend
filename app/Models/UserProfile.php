<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'gender',
        'date_of_birth',
        'address', // ត្រូវប្រាកដថាមានពាក្យនេះនៅទីនេះ
        'position',
        'bio',
        'profile_image'
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
