<?php

namespace Kern\Table;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Kern\Table\Abstracts\TableAbstract;

class TableBuilder
{
    public function __construct(protected Container $container) {}

    public function create(string $tableClass): TableAbstract
    {
        if (! class_exists($tableClass)) {
            throw new InvalidArgumentException(sprintf('Table class with name %s does not exist.', $tableClass));
        }

        return $this->container->make($tableClass);
    }
}
