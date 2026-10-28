<?php

namespace Kern\ACL\Repositories\Interfaces;

use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface UserInterface extends RepositoryContract
{
    public function getUniqueUsernameFromEmail(string $email): string;
}
