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
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password, 
            'user_type_id' => $this->user_type_id ?? null,
            'user_type_name' => $this->user_type_name ?? null,
            'shop_id' => $this->shop_id ?? null,
            'shop_ids' => $this->shop_ids ?? null,
            'shop_name' => $this->shop_name ?? null,
            'team_id' => $this->team_id ?? null,
            'team_name' => $this->team_name ?? null,
            'folder' => explode(", ", $this->folder),  
        ];
    }
}
