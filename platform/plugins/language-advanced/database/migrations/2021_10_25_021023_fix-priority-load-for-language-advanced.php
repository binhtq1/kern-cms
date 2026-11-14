<?php

use Illuminate\Database\Migrations\Migration;
use Kern\LanguageAdvanced\Plugin;

return new class extends Migration
{
    public function up(): void
    {
        rescue(fn () => Plugin::activated(), report: false);
    }
};
