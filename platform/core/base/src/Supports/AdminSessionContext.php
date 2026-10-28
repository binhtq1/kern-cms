<?php

namespace Kern\Base\Supports;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\JsonResponse;

class AdminSessionContext
{
    public const HEADER = 'X-Admin-Draft-Context';

    public static function issue(Authenticatable $actor): string
    {
        return Crypt::encryptString((string) $actor->getAuthIdentifier());
    }

    public static function actor(Request $request): ?string
    {
        $context = $request->header(self::HEADER);
        if (! is_string($context) || strlen($context) > 2048 || $context === '') {
            return null;
        }

        try {
            $actor = Crypt::decryptString($context);

            return $actor !== '' ? $actor : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public static function matches(Request $request): bool
    {
        $actor = self::actor($request);

        return $actor !== null && $request->user()
            && hash_equals($actor, (string) $request->user()->getAuthIdentifier());
    }

    public static function recoveryResponse(): JsonResponse
    {
        return response()->json([
            'error' => true,
            'code' => 'admin_session_expired',
            'message' => 'Sign in again with the same account. Your changes have not been saved.',
            'loginUrl' => route('access.login'),
            'statusUrl' => route('admin.session-status'),
        ], 401, ['X-Admin-Session-Recovery' => '1', 'Cache-Control' => 'no-store']);
    }
}
