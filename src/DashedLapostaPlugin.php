<?php

namespace Dashed\DashedLaposta;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedLaposta\Filament\Pages\Settings\DashedLapostaSettingsPage;

class DashedLapostaPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-laposta';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                DashedLapostaSettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
