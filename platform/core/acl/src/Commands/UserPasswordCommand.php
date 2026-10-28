<?php

namespace Kern\ACL\Commands;

use Exception;
use Illuminate\Console\Command;
use Kern\ACL\Models\User;
use Kern\Base\Commands\Traits\ValidateCommandInput;
use Kern\Base\Supports\Helper;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[AsCommand('cms:user:password:change', 'Change admin user password')]
class UserPasswordCommand extends Command
{
    use ValidateCommandInput;

    public function handle(): int
    {
        try {
            $user = $this->changePassword();

            $this->sendSuccessMessage($user);

            Helper::clearCache();

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->components->error('Could not change user password.');
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function getUserData(): array
    {
        return [
            'username' => text(
                label: 'Username',
                required: true,
                validate: $this->validate('min:4|max:60')
            ),
            'password' => password(
                label: 'Password',
                required: true,
                validate: $this->validate('min:6|max:60')
            ),
        ];
    }

    protected function changePassword(): User
    {
        $options = $this->getUserData();

        /**
         * @var User $user
         */
        $user = User::query()->where('username', $options['username'])->first();

        if (! $user) {
            $this->components->error('No user found with that username.');

            exit(1);
        }

        $user->update([
            'password' => $options['password'],
        ]);

        return $user;
    }

    protected function sendSuccessMessage(User $user): void
    {
        $this->components->info(sprintf(
            'Then password of %s has been changed. You can login at %s',
            $user->username,
            route('access.login')
        ));
    }
}
