<?php

declare(strict_types=1);

namespace Dashed\DashedLaposta\Import;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLaposta\Classes\Laposta;
use Dashed\DashedNewsletter\Models\NewsletterList;

/**
 * De hele overname op één plek. Zowel het console-command als de knop op de
 * instellingenpagina roepen dit aan; stond de logica in allebei, dan lopen ze
 * uit elkaar zodra iemand er één aanpast, en dat merk je pas als de een andere
 * contacten oplevert dan de ander.
 */
class LapostaContactImporter
{
    public function forSite(string $siteId, ?string $fromEmail = null, ?string $onlyListId = null): LapostaImportReport
    {
        // Zelfde guard als dashed-ecommerce-core voor de segmentcondities: dit
        // pakket vereist de nieuwsbriefmodule niet, dus als hij er niet is
        // stoppen we met een uitleg in plaats van een fatale fout.
        if (! app()->bound('newsletter')) {
            return LapostaImportReport::fail('De nieuwsbriefmodule (dashed-newsletter) is niet geinstalleerd.');
        }

        // Geen adres is geen probleem meer: een lijst zonder eigen afzenderadres
        // valt terug op de algemene instellingen. Wat hier wordt meegegeven is
        // dus een keuze en geen voorwaarde.
        $lists = Laposta::listsFor($siteId);

        // null betekent dat het verzoek mislukte, een lege array dat het account
        // geen lijsten heeft. Die twee dezelfde melding geven stuurt iemand met
        // een leeg account op zoek naar een sleutelprobleem dat er niet is.
        if ($lists === null) {
            return LapostaImportReport::fail('Kon de lijsten niet ophalen bij Laposta. Staat er een geldige API key op deze site?');
        }

        if ($lists === []) {
            return LapostaImportReport::fail('Er staan geen lijsten in dit Laposta-account.');
        }

        $report = new LapostaImportReport();
        $overgenomenOp = now()->format('d-m-Y');

        foreach ($lists as $entry) {
            $lapostaList = $entry['list'] ?? $entry;
            $lapostaId = (string) ($lapostaList['list_id'] ?? '');

            if ($onlyListId && $onlyListId !== $lapostaId) {
                continue;
            }

            $this->importList($report, $lapostaList, $lapostaId, $siteId, $fromEmail, $overgenomenOp);
        }

        return $report;
    }

    public function forActiveSite(?string $fromEmail = null, ?string $onlyListId = null): LapostaImportReport
    {
        return $this->forSite(Sites::getActive(), $fromEmail, $onlyListId);
    }

    /**
     * @param array<string, mixed> $lapostaList
     */
    private function importList(
        LapostaImportReport $report,
        array $lapostaList,
        string $lapostaId,
        string $siteId,
        ?string $fromEmail,
        string $overgenomenOp,
    ): void {
        $name = (string) ($lapostaList['name'] ?? 'Overgenomen uit Laposta');

        // Eerst allebei ophalen en controleren, vóórdat er een lijst wordt
        // aangemaakt: een mislukt verzoek mag geen halve overname opleveren
        // (bijvoorbeeld contacten zonder voornaam omdat de velden er niet
        // kwamen terwijl de leden wel binnenkwamen).
        $lapostaFields = Laposta::fields($lapostaId, $siteId);
        $members = Laposta::members($lapostaId, $siteId);

        if ($lapostaFields === null || $members === null) {
            $report->addFailedList($name, $lapostaId);

            return;
        }

        $list = $this->findOrCreateList($lapostaId, $name, $siteId, $fromEmail);

        foreach (LapostaContactMapper::fields($lapostaFields) as $field) {
            $list->fields()->firstOrCreate(
                ['key' => $field['key']],
                ['label' => $field['label'], 'type' => $field['type']]
            );
        }

        $contacts = [];
        $geweigerd = [];

        foreach ($members as $entry) {
            $member = $entry['member'] ?? $entry;

            try {
                $contacts[] = LapostaContactMapper::contact($member, $overgenomenOp);
            } catch (\InvalidArgumentException $e) {
                // Een lijst en geen afbeelding op e-mailadres: twee leden die om
                // dezelfde reden afvallen moeten allebei meetellen, ook als hun
                // adres gelijk of leeg is.
                $geweigerd[] = ['email' => (string) ($member['email'] ?? ''), 'reason' => $e->getMessage()];
            }
        }

        $result = app('newsletter')->importMany($list, $contacts);

        // De mapper wijst een lid al af vóórdat importMany() er ooit van weet,
        // dus telt het hier apart mee in dezelfde slotregel.
        foreach ($geweigerd as $afgewezen) {
            $result->skip($afgewezen['email'], $afgewezen['reason']);
        }

        $report->addList($name, $lapostaId, $result->created, $result->updated, $result->skipped, $result->reasons);
    }

    /**
     * De lijst wordt teruggevonden op het opgeslagen Laposta-id en niet op naam.
     * Zonder die verwijzing levert elke ronde een nieuwe lijst met dezelfde naam
     * op, en daar kom je later niet meer uit.
     */
    private function findOrCreateList(string $lapostaId, string $name, string $siteId, ?string $fromEmail): NewsletterList
    {
        $list = NewsletterList::where('site_id', $siteId)
            ->where('settings->laposta_list_id', $lapostaId)
            ->first();

        if ($list) {
            return $list;
        }

        return NewsletterList::create([
            'site_id' => $siteId,
            'name' => $name,
            'from_email' => $fromEmail,
            'settings' => ['laposta_list_id' => $lapostaId],
        ]);
    }
}
