<?php

namespace Kern\Base\Listeners;

use Exception;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Kern\Base\Events\SendMailEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Supports\EmailAbstract;

class SendMailListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected Mailer $mailer) {}

    public function viaConnection(SendMailEvent $event): ?string
    {
        return $event->debug ? 'sync' : null;
    }

    public function handle(SendMailEvent $event): void
    {
        try {
            $this->mailer->to($event->to)->send(new EmailAbstract($event->content, $event->title, $event->args));
        } catch (Exception $exception) {
            if ($event->debug) {
                throw $exception;
            }

            BaseHelper::logError($exception);
        }
    }
}
