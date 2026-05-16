<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // 🌟 Import HasUuids

class Contact extends Model
{
    use HasFactory, HasUuids; // 🌟 ប្រើប្រាស់ HasUuids

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
    ];
}
