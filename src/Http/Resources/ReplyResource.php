<?php

namespace Escalated\Laravel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal_note' => $this->is_internal_note,
            'is_pinned' => $this->is_pinned ?? false,
            'author' => $this->author ? [
                'id' => $this->author->getKey(),
                'name' => $this->author->name,
                'email' => $this->author->email ?? null,
            ] : null,
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'filename' => $a->filename,
                'mime_type' => $a->mime_type,
                'size' => $a->size,
                'url' => $a->url,
            ])),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
