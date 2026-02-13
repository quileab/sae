<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerResource extends JsonResource
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
            'resolution' => $this->resolution,
            'allow_enrollments' => $this->allow_enrollments,
            'allow_evaluations' => $this->allow_evaluations,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
