<?php

declare(strict_types=1);

namespace Dashed\DashedLaposta\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLaposta\Import\LapostaContactImporter;

class ImportLapostaContacts extends Command
{
    protected $signature = 'dashed:import-laposta-contacts
        {--site= : De site waarvan het Laposta-account gebruikt wordt}
        {--list= : Alleen deze Laposta-lijst overnemen}
        {--from-email= : Afzenderadres voor een nieuw aangemaakte lijst}';

    protected $description = 'Neemt lijsten, velden en contacten over uit Laposta';

    /**
     * De overname zelf staat in LapostaContactImporter, want de knop op de
     * instellingenpagina doet precies hetzelfde. Dit command is alleen de
     * ingang vanaf de commandoregel en de uitvoer eromheen.
     */
    public function handle(LapostaContactImporter $importer): int
    {
        $siteId = $this->option('site') ?: Sites::getActive();

        $report = $importer->forSite(
            siteId: $siteId,
            fromEmail: $this->option('from-email'),
            onlyListId: $this->option('list'),
        );

        if ($report->error) {
            $this->error($report->error);

            return self::FAILURE;
        }

        foreach ($report->lists as $list) {
            $this->info('Lijst ' . $list['name'] . ' (' . $list['id'] . ')');

            if ($list['failed']) {
                $this->error('  kon de velden of leden niet ophalen bij Laposta, lijst overgeslagen.');

                continue;
            }

            $this->info('  aangemaakt: ' . $list['created']
                . ', bijgewerkt: ' . $list['updated']
                . ', overgeslagen: ' . $list['skipped']);

            foreach ($list['reasons'] as $email => $reason) {
                $this->warn('  ' . $email . ': ' . $reason);
            }
        }

        return $report->failed ? self::FAILURE : self::SUCCESS;
    }
}
