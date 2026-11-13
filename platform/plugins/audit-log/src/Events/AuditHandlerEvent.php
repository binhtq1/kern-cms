<?php

namespace Kern\AuditLog\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Kern\Base\Events\Event;

class AuditHandlerEvent extends Event
{
    use SerializesModels;

    public string|int $referenceUser;

    public function __construct(
        public string $module,
        public string $action,
        public int|string $referenceId,
        public ?string $referenceName,
        public string $type,
        int|string $referenceUser = 0
    ) {
        if ($referenceUser === 0 && Auth::guard()->check()) {
            $referenceUser = Auth::guard()->id();
        }

        $this->referenceUser = $referenceUser;
    }
}
