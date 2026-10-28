<?php

namespace Kern\ACL\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Kern\ACL\Models\User;
use Kern\Base\Models\BaseModel;

interface ActivationInterface
{
    public function createUser(User $user): BaseModel|Model;

    public function exists(User $user, ?string $code = null): BaseModel|bool;

    public function complete(User $user, string $code): bool;

    public function completed(User $user): BaseModel|bool;

    public function remove(User $user);

    public function removeExpired();
}
