<?php

namespace Dashed\DashedTernair;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedTernair\Filament\Pages\Settings\DashedTernairSettingsPage;

class DashedTernairPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-ternair';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                DashedTernairSettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
