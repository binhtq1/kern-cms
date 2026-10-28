<?php

namespace Kern\DataSynchronize\Contracts\Importer;

interface WithMapping
{
    public function map(mixed $row): array;
}
