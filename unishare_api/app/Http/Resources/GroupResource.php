<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'members_count' => $this->members_count ?? 0,
            'documents_count' => $this->documents_count ?? 0,
            'posts_count' => $this->posts_count ?? 0,
        ];
    }
}
