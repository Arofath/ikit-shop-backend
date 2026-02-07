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
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone_number' => $this->phone_number,
            'role'         => $this->role,
            'is_super_admin' => $this->isSuperAdmin(), // បន្ថែមបន្ទាត់នេះ
            'is_active'    => $this->is_active,
            // ទាញយកព័ត៌មានពី table user_profiles
            'profile'      => [
                'profile_image' => $this->profile->profile_image ?? null,
                'gender'        => $this->profile->gender ?? null,
            ],
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'created_at'    => $this->created_at->toDateTimeString(),
        ];
    }
}
