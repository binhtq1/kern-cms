<?php

namespace Kern\Api\Supports;

use Illuminate\Database\Eloquent\Model;
use Kern\ACL\Models\User;
use Kern\Base\Models\BaseModel;

class ApiHelper
{
    public function modelName(): string
    {
        return (string) $this->getConfig('model', User::class);
    }

    public function setModelName(string $modelName): self
    {
        config(['packages.api.api.provider.model' => $modelName]);

        return $this;
    }

    public function guard(): ?string
    {
        return $this->getConfig('guard');
    }

    public function passwordBroker(): ?string
    {
        return $this->getConfig('password_broker');
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return config('packages.api.api.provider.'.$key, $default);
    }

    public function setConfig(array $config)
    {
        return config(['packages.api.api.provider' => $config]);
    }

    public function newModel(): ?Model
    {
        $model = $this->modelName();

        if (! $model || ! class_exists($model)) {
            return new BaseModel;
        }

        return new $model;
    }

    public function getTable(): string
    {
        return $this->newModel()->getTable();
    }

    public function enabled(): bool
    {
        return setting('api_enabled', 0) == 1;
    }
}
