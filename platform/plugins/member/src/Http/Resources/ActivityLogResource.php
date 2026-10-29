<?php

namespace Kern\Member\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Kern\Member\Models\MemberActivityLog;

/**
 * @mixin MemberActivityLog
 */
class ActivityLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),
            'ip_address' => $this->ip_address,
            'description' => $this->getDescription(),
        ];
    }
}
