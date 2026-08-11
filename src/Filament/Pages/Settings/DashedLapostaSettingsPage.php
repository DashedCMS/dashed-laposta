<?php

namespace Dashed\DashedLaposta\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedLaposta\Classes\Laposta;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedLaposta\Import\LapostaContactImporter;

class DashedLapostaSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Laposta instellingen';
    protected static string|UnitEnum|null $navigationGroup = 'Systeem';
    protected static ?string $title = 'Laposta instellingen';
    protected string $view = 'dashed-core::settings.pages.default-settings';
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

    /**
     * Eén knop per verbonden site. Bij één site staat de naam er niet bij, zoals
     * de rest van het CMS het doet; bij meer sites wel, want anders staan er
     * twee knoppen die hetzelfde heten en iets anders doen.
     *
     * De knop verschijnt alleen als de nieuwsbriefmodule er is. In een project
     * met wel Laposta en geen nieuwsbrief is er niets om contacten in te zetten.
     */
    protected function getHeaderActions(): array
    {
        if (! app()->bound('newsletter')) {
            return [];
        }

        $sites = collect(Sites::getSites())
            ->filter(fn (array $site): bool => (bool) Customsetting::get('laposta_connected', $site['id']));

        $meerdereSites = $sites->count() > 1;

        return $sites
            ->map(fn (array $site): Action => Action::make('importContacts_' . $site['id'])
                ->label($meerdereSites
                    ? __('Contacten overnemen uit Laposta') . ' (' . $site['name'] . ')'
                    : __('Contacten overnemen uit Laposta'))
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->modalHeading(__('Contacten overnemen uit Laposta'))
                ->modalDescription(__('De lijsten, velden en contacten uit Laposta worden overgenomen in het CMS. Uitgeschreven contacten blijven uitgeschreven. In Laposta zelf verandert niets, en je kunt dit zo vaak herhalen als je wilt.'))
                ->modalSubmitActionLabel(__('Overnemen'))
                ->action(fn () => $this->importContacts($site['id'])))
            ->values()
            ->all();
    }

    public function importContacts(string $siteId): void
    {
        $report = app(LapostaContactImporter::class)->forSite($siteId);

        if ($report->error) {
            Notification::make()
                ->title(__('Overnemen is niet gelukt'))
                ->body($report->error)
                ->danger()
                ->send();

            return;
        }

        // Mislukte lijsten zijn geen half succes: er kan een lijst tussen zitten
        // waarvan de velden niet opgehaald konden worden, en dan zouden die
        // contacten zonder veldwaarden binnenkomen.
        if ($report->failed) {
            Notification::make()
                ->title(__('Niet alles is overgenomen'))
                ->body(__('Van minstens een lijst konden de velden of contacten niet worden opgehaald bij Laposta. Wat er wel binnenkwam: ') . $report->summary())
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Contacten overgenomen'))
            ->body($report->summary())
            ->success()
            ->send();
    }

    public function form(Schema $schema): Schema
    {
        $sites = Sites::getSites();
        $tabGroups = [];
        $tabs = [];
        foreach ($sites as $site) {
            $newSchema = [TextEntry::make('Laposta verbonden?')->state(function () use ($site) {
                $connected = Customsetting::get('laposta_connected', $site['id']);
                if ($connected) {
                    return 'Verbonden';
                }

                return 'Niet verbonden';
            })->columnSpan(2), TextInput::make("laposta_api_key_{$site['id']}")->label(__('API key'))->reactive(),];
            $tabs[] = Tab::make($site['id'])->label(ucfirst($site['name']))->schema($newSchema);
        }
        $tabGroups[] = Tabs::make('Sites')->tabs($tabs);

        return $schema->schema($tabGroups)->statePath('data');
    }

    public function submit()
    {
        $sites = Sites::getSites();
        $formState = $this->form->getState();
        foreach ($sites as $site) {
            Customsetting::set('laposta_api_key', $this->form->getState()["laposta_api_key_{$site['id']}"], $site['id']);
            $connected = Laposta::isConnected($site['id']);
            Customsetting::set('laposta_connected', $connected, $site['id']);
            if ($connected) {
                Laposta::syncLists($site['id']);
            }
        }
        $this->form->fill($formState);
        Notification::make()->title(__('De Dashed Laposta instellingen zijn opgeslagen'))->success()->send();
    }
}
