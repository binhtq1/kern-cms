<?php

namespace Kern\Member\Tables\Traits;

trait ForMember
{
    public function booted(): void
    {
        $this->setView('plugins/member::table.base');
    }
}
