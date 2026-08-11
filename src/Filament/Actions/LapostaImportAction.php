<?php

declare(strict_types=1);

namespace Dashed\DashedLaposta\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLaposta\Import\LapostaContactImporter;

/**
 * De knop om contacten over te nemen, op één plek gedefinieerd.
 *
 * Hij staat op twee schermen: op de Laposta-instellingenpagina, waar de
 * koppeling hoort, en op de nieuwsbrief-instellingenpagina, waar iemand die
 * met contacten bezig is hem zoekt. Twee keer dezelfde knop uitschrijven
 * betekent dat de teksten en het gedrag uit elkaar lopen zodra er een wordt
 * aangepast.
 */
class LapostaImportAction
{
    public static function for(string $siteId): Action
    {
        return Action::make('importLapostaContacts_' . $siteId)
            ->label(__('Contacten overnemen uit Laposta'))
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => (bool) Customsetting::get('laposta_connected', $siteId))
            ->requiresConfirmation()
            ->modalHeading(__('Contacten overnemen uit Laposta'))
            ->modalDescription(__('De lijsten, velden en contacten uit Laposta worden overgenomen in het CMS. Uitgeschreven contacten blijven uitgeschreven. In Laposta zelf verandert niets, en je kunt dit zo vaak herhalen als je wilt.'))
            ->modalSubmitActionLabel(__('Overnemen'))
            ->action(fn () => static::run($siteId));
    }

    private static function run(string $siteId): void
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
}
