<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Referentiels;
    case Administration;

    public function getLabel(): string
    {
        return match ($this) {
            self::Referentiels => 'Référentiels',
            self::Administration => 'Administration',
        };
    }
}
