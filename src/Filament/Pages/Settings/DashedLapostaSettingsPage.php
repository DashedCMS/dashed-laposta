<?php

namespace Dashed\DashedLaposta\Filament\Pages\Settings;

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
            $formData["laposta_x_api_application_header_{$site['id']}"] = Customsetting::get('laposta_x_api_application_header', $site['id']);
            $formData["laposta_api_username_{$site['id']}"] = Customsetting::get('laposta_api_username', $site['id']);
            $formData["laposta_api_password_{$site['id']}"] = Customsetting::get('laposta_api_password', $site['id']);
            foreach (Customsetting::get('laposta_redirect_after_confirm_url', $site['id'], type: 'array') ?: [] as $key => $value) {
                $formData["laposta_redirect_after_confirm_url_{$site['id']}_{$key}"] = $value;
            }
            foreach (Customsetting::get('laposta_redirect_after_unsubscribe_url', $site['id'], type: 'array') ?: [] as $key => $value) {
                $formData["laposta_redirect_after_unsubscribe_url_{$site['id']}_{$key}"] = $value;
            }
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
                TextInput::make("laposta_x_api_application_header_{$site['id']}")
                    ->label('X-API-Application-Header voor de Laposta API')
                    ->reactive(),
                TextInput::make("laposta_api_username_{$site['id']}")
                    ->label('API username')
                    ->reactive(),
                TextInput::make("laposta_api_password_{$site['id']}")
                    ->label('API wachtwoord')
                    ->password()
                    ->reactive(),
                linkHelper()->field("laposta_redirect_after_confirm_url_{$site['id']}", false, 'Redirect na bevestigen'),
                linkHelper()->field("laposta_redirect_after_unsubscribe_url_{$site['id']}", false, 'Redirect na uitschrijven'),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($schema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
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
            Customsetting::set('laposta_x_api_application_header', $this->form->getState()["laposta_x_api_application_header_{$site['id']}"], $site['id']);
            Customsetting::set('laposta_api_username', $this->form->getState()["laposta_api_username_{$site['id']}"], $site['id']);
            Customsetting::set('laposta_api_password', $this->form->getState()["laposta_api_password_{$site['id']}"], $site['id']);
            Customsetting::set('laposta_redirect_after_confirm_url', linkHelper()->getDataToSave($this->form->getState(), "laposta_redirect_after_confirm_url", $site['id']), $site['id']);
            Customsetting::set('laposta_redirect_after_unsubscribe_url', linkHelper()->getDataToSave($this->form->getState(), "laposta_redirect_after_unsubscribe_url", $site['id']), $site['id']);
        }

        $this->form->fill($formState);

        Notification::make()
            ->title('De Dashed Laposta instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
