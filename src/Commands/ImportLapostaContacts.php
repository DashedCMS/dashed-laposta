<?php

declare(strict_types=1);

namespace Dashed\DashedLaposta\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLaposta\Classes\Laposta;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLaposta\Import\LapostaContactMapper;

class ImportLapostaContacts extends Command
{
    protected $signature = 'dashed:import-laposta-contacts
        {--site= : De site waarvan het Laposta-account gebruikt wordt}
        {--list= : Alleen deze Laposta-lijst overnemen}
        {--from-email= : Afzenderadres voor een nieuw aangemaakte lijst}';

    protected $description = 'Neemt lijsten, velden en contacten over uit Laposta';

    public function handle(): int
    {
        // Zelfde guard als dashed-ecommerce-core voor de segmentcondities: dit
        // pakket vereist de nieuwsbriefmodule niet, dus als hij er niet is
        // stoppen we met een uitleg in plaats van een fatale fout.
        if (! app()->bound('newsletter')) {
            $this->error('De nieuwsbriefmodule (dashed-newsletter) is niet geinstalleerd.');

            return self::FAILURE;
        }

        $siteId = $this->option('site') ?: Sites::getActive();

        $fromEmail = $this->option('from-email')
            ?: Customsetting::get('site_from_email', $siteId)
            ?: config('mail.from.address');

        if (! $fromEmail) {
            $this->error('Geen afzenderadres gevonden. Geef er een mee met --from-email of vul het in bij de algemene instellingen.');

            return self::FAILURE;
        }

        $lists = Laposta::listsFor($siteId);

        if (! $lists) {
            $this->error('Geen lijsten opgehaald. Staat er een geldige Laposta API key op deze site?');

            return self::FAILURE;
        }

        $overgenomenOp = now()->format('d-m-Y');

        foreach ($lists as $entry) {
            $lapostaList = $entry['list'] ?? $entry;
            $lapostaId = (string) ($lapostaList['list_id'] ?? '');

            if ($this->option('list') && $this->option('list') !== $lapostaId) {
                continue;
            }

            $this->importList($lapostaList, $lapostaId, $siteId, (string) $fromEmail, $overgenomenOp);
        }

        return self::SUCCESS;
    }

    private function importList(
        array $lapostaList,
        string $lapostaId,
        string $siteId,
        string $fromEmail,
        string $overgenomenOp,
    ): void {
        $name = (string) ($lapostaList['name'] ?? 'Overgenomen uit Laposta');

        $this->info('Lijst ' . $name . ' (' . $lapostaId . ')');

        $list = $this->findOrCreateList($lapostaId, $name, $siteId, $fromEmail);

        foreach (LapostaContactMapper::fields(Laposta::fields($lapostaId, $siteId)) as $field) {
            $list->fields()->firstOrCreate(
                ['key' => $field['key']],
                ['label' => $field['label'], 'type' => $field['type']]
            );
        }

        $members = Laposta::members($lapostaId, $siteId);
        $contacts = [];

        foreach ($members as $entry) {
            $member = $entry['member'] ?? $entry;

            try {
                $contacts[] = LapostaContactMapper::contact($member, $overgenomenOp);
            } catch (\InvalidArgumentException $e) {
                $this->warn('  overgeslagen: ' . $e->getMessage());
            }
        }

        $result = app('newsletter')->importMany($list, $contacts);

        $this->info('  aangemaakt: ' . $result->created
            . ', bijgewerkt: ' . $result->updated
            . ', overgeslagen: ' . $result->skipped);

        foreach ($result->reasons as $email => $reason) {
            $this->warn('  ' . $email . ': ' . $reason);
        }
    }

    /**
     * De lijst wordt teruggevonden op het opgeslagen Laposta-id en niet op naam.
     * Zonder die verwijzing levert elke ronde een nieuwe lijst met dezelfde naam
     * op, en daar kom je later niet meer uit.
     */
    private function findOrCreateList(string $lapostaId, string $name, string $siteId, string $fromEmail)
    {
        $listClass = \Dashed\DashedNewsletter\Models\NewsletterList::class;

        $list = $listClass::where('site_id', $siteId)
            ->where('settings->laposta_list_id', $lapostaId)
            ->first();

        if ($list) {
            return $list;
        }

        return $listClass::create([
            'site_id' => $siteId,
            'name' => $name,
            'from_email' => $fromEmail,
            'settings' => ['laposta_list_id' => $lapostaId],
        ]);
    }
}
