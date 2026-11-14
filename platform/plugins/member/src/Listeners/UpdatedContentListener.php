<?php

namespace Kern\Member\Listeners;

use Exception;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Blog\Models\Post;
use Kern\Member\Models\Member;
use Kern\Member\Models\MemberActivityLog;

class UpdatedContentListener
{
    public function handle(UpdatedContentEvent $event): void
    {
        try {
            $post = $event->data;

            if (! $post instanceof Post) {
                return;
            }

            if ($post->getKey() &&
                $post->author_type === Member::class &&
                auth('member')->check() &&
                $post->author_id == auth('member')->id()
            ) {
                MemberActivityLog::query()->create([
                    'action' => 'your_post_updated_by_admin',
                    'reference_name' => $post->name,
                    'reference_url' => route('public.member.posts.edit', $post->getKey()),
                ]);
            }
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
