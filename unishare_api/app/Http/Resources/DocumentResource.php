<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'subject' => $this->subject,
            'course_code' => $this->course_code,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'download_count' => $this->download_count,
            'view_count' => $this->view_count,
            'is_official' => $this->is_official,
            'is_approved' => $this->is_approved,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
        ];
    }
}
