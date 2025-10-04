<?php

namespace Dashed\DashedLaposta\Filament\Pages\Settings;

use Dashed\DashedLaposta\Classes\Laposta;
use Filament\Forms\Components\Placeholder;
use Filament\Pages\Page;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;

class DashedLapostaSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Laposta instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Laposta instellingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["laposta_api_key_{$site['id']}"] = Customsetting::get('laposta_api_key', $site['id']);
            $formData["laposta_connected_{$site['id']}"] = Customsetting::get('laposta_connected', $site['id']);
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('Laposta verbonden?')
                    ->content(function () use ($site) {
                        $connected = Customsetting::get('laposta_connected', $site['id']);
                        if ($connected) {
                            return 'Verbonden';
                        }
                        return 'Niet verbonden';
                    })
                    ->columnSpan(2),
                TextInput::make("laposta_api_key_{$site['id']}")
                    ->label('API key')
                    ->reactive(),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($schema);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $tabGroups;
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function submit()
    {
        $sites = Sites::getSites();
        $formState = $this->form->getState();

        foreach ($sites as $site) {
            Customsetting::set('laposta_api_key', $this->form->getState()["laposta_api_key_{$site['id']}"], $site['id']);
            $connected = Laposta::isConnected($site['id']);
            Customsetting::set('laposta_connected', $connected, $site['id']);
            if($connected){
                Laposta::syncLists($site['id']);
            }
        }

        $this->form->fill($formState);

        Notification::make()
            ->title('De Dashed Laposta instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
