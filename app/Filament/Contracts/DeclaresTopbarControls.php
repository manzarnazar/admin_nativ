<?php

namespace App\Filament\Contracts;

interface DeclaresTopbarControls
{
    /**
     * Topbar context switchers this page honors. Omitted keys default to visible.
     *
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array;
}
