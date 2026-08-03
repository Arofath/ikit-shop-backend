<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone_number'   => $this->phone_number,
            'role'           => $this->role,
            'is_super_admin' => $this->isSuperAdmin(),
            'is_active'      => $this->is_active,
            'is_2fa_enabled' => $this->is_2fa_enabled, // 🌟 បន្ថែមសម្រាប់ Frontend Toggle 2FA

            // រៀបចំ Profile Object ឱ្យស្អាត និងលាក់ Column មិនចាំបាច់
            'profile'        => [
                'profile_image' => $this->profile->profile_image ?? null,
                'gender'        => $this->profile->gender ?? null,
                'date_of_birth' => $this->profile->date_of_birth ?? null,
                'address'       => $this->profile->address ?? null,
                'position'      => $this->profile->position ?? null,
                'bio'           => $this->profile->bio ?? null,
            ],

            // 🌟 ទាញយកបញ្ជីសិទ្ធិពី Spatie បោះទៅឱ្យ Frontend (Pinia Store)
            'roles'          => $this->getRoleNames(),
            'permissions'    => $this->getAllPermissions()->pluck('name'),

            'last_login_at'  => $this->last_login_at?->toDateTimeString(),
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }
}
