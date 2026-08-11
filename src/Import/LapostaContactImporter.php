<?php

declare(strict_types=1);

namespace Dashed\DashedLaposta\Import;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLaposta\Classes\Laposta;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;

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

        // Is er een standaardlijst ingesteld, dan komt alles daarop terecht en
        // spiegelen we de lijstindeling van Laposta niet. Dat is wat een
        // beheerder bedoelt als hij een standaardlijst kiest: daar horen mijn
        // contacten. Zonder die instelling blijft het oude gedrag, een lijst per
        // Laposta-lijst.
        $defaultList = app('newsletter')->defaultList($siteId);

        $verzameldeContacten = [];
        $verzameldeVelden = [];
        $bronnen = [];

        foreach ($lists as $entry) {
            $lapostaList = $entry['list'] ?? $entry;
            $lapostaId = (string) ($lapostaList['list_id'] ?? '');
            $name = (string) ($lapostaList['name'] ?? 'Overgenomen uit Laposta');

            if ($onlyListId && $onlyListId !== $lapostaId) {
                continue;
            }

            $opgehaald = $this->gather($lapostaId, $siteId, $overgenomenOp);

            if ($opgehaald === null) {
                $report->addFailedList($name, $lapostaId);

                continue;
            }

            if ($defaultList) {
                $verzameldeContacten = array_merge($verzameldeContacten, $opgehaald['contacts']);
                $verzameldeVelden = array_merge($verzameldeVelden, $opgehaald['fields']);
                $bronnen[] = $lapostaId;

                continue;
            }

            $list = $this->findOrCreateList($lapostaId, $name, $siteId, $fromEmail);
            $this->writeFields($list, $opgehaald['fields']);
            $this->importContacts($report, $list, $name, $lapostaId, $opgehaald['contacts'], $opgehaald['rejected']);
        }

        if ($defaultList && $bronnen) {
            $this->writeFields($defaultList, $verzameldeVelden);
            $this->importContacts(
                $report,
                $defaultList,
                $defaultList->name,
                implode(', ', $bronnen),
                $this->dedupe($verzameldeContacten),
                [],
            );
        }

        return $report;
    }

    /**
     * Hetzelfde adres kan op meer dan een Laposta-lijst staan. Komen die samen
     * op een lijst, dan wint de niet-actieve status: iemand die zich ergens heeft
     * uitgeschreven mag niet actief worden omdat hij elders nog aanstond. Zonder
     * deze regel bepaalt de volgorde van de lijsten de uitkomst.
     *
     * @param array<int, \Dashed\DashedNewsletter\Import\ImportedContact> $contacts
     * @return array<int, \Dashed\DashedNewsletter\Import\ImportedContact>
     */
    private function dedupe(array $contacts): array
    {
        $perEmail = [];

        foreach ($contacts as $contact) {
            $key = mb_strtolower(trim($contact->email));
            $bestaande = $perEmail[$key] ?? null;

            if (! $bestaande) {
                $perEmail[$key] = $contact;

                continue;
            }

            if ($bestaande->status === NewsletterSubscriber::STATUS_ACTIVE
                && $contact->status !== NewsletterSubscriber::STATUS_ACTIVE) {
                $perEmail[$key] = $contact;
            }
        }

        return array_values($perEmail);
    }

    public function forActiveSite(?string $fromEmail = null, ?string $onlyListId = null): LapostaImportReport
    {
        return $this->forSite(Sites::getActive(), $fromEmail, $onlyListId);
    }

    /**
     * Haalt velden en leden op en vertaalt ze, zonder iets weg te schrijven.
     *
     * Eerst allebei ophalen en controleren, vóórdat er een lijst wordt
     * aangemaakt: een mislukt verzoek mag geen halve overname opleveren
     * (bijvoorbeeld contacten zonder voornaam omdat de velden er niet kwamen
     * terwijl de leden wel binnenkwamen).
     *
     * @return array{fields: array<int, array{key: string, label: string, type: string}>, contacts: array<int, \Dashed\DashedNewsletter\Import\ImportedContact>, rejected: array<int, array{email: string, reason: string}>}|null
     */
    private function gather(string $lapostaId, string $siteId, string $overgenomenOp): ?array
    {
        $lapostaFields = Laposta::fields($lapostaId, $siteId);
        $members = Laposta::members($lapostaId, $siteId);

        if ($lapostaFields === null || $members === null) {
            return null;
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

        return [
            'fields' => LapostaContactMapper::fields($lapostaFields),
            'contacts' => $contacts,
            'rejected' => $geweigerd,
        ];
    }

    /**
     * @param array<int, array{key: string, label: string, type: string}> $fields
     */
    private function writeFields(NewsletterList $list, array $fields): void
    {
        foreach ($fields as $field) {
            $list->fields()->firstOrCreate(
                ['key' => $field['key']],
                ['label' => $field['label'], 'type' => $field['type']]
            );
        }
    }

    /**
     * @param array<int, \Dashed\DashedNewsletter\Import\ImportedContact> $contacts
     * @param array<int, array{email: string, reason: string}> $rejected
     */
    private function importContacts(
        LapostaImportReport $report,
        NewsletterList $list,
        string $name,
        string $lapostaId,
        array $contacts,
        array $rejected,
    ): void {
        $result = app('newsletter')->importMany($list, $contacts);

        // De mapper wijst een lid al af vóórdat importMany() er ooit van weet,
        // dus telt het hier apart mee in dezelfde slotregel.
        foreach ($rejected as $afgewezen) {
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
