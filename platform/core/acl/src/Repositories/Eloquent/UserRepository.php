<?php

namespace Kern\ACL\Repositories\Eloquent;

use Kern\ACL\Repositories\Interfaces\UserInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class UserRepository extends RepositoryAbstract implements UserInterface
{
    public function getUniqueUsernameFromEmail(string $email): string
    {
        $emailPrefix = substr($email, 0, strpos($email, '@'));
        $username = $emailPrefix;
        $offset = 1;
        while ($this->getFirstBy(['username' => $username])) {
            $username = $emailPrefix.$offset;
            $offset++;
        }

        $this->resetModel();

        return $username;
    }
}
