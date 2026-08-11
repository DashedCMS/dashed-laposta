<?php

declare(strict_types=1);

namespace Dashed\DashedLaposta\Import;

use Carbon\CarbonImmutable;
use Dashed\DashedNewsletter\Import\ImportedContact;
use Dashed\DashedNewsletter\Models\NewsletterField;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;

/**
 * De enige plek waar de vorm van Laposta bekend is. Alles wat hierna komt
 * spreekt de taal van het nieuwsbriefpakket, zodat een overname uit een andere
 * bron alleen een nieuwe mapper vraagt en verder niets.
 */
class LapostaContactMapper
{
    /**
     * De statuswaarden van Laposta zijn dezelfde drie als de onze. Toch
     * expliciet uitgeschreven: als Laposta er ooit een bijkrijgt, moet dat een
     * fout worden en geen stille aanname.
     */
    private const STATES = [
        'active' => NewsletterSubscriber::STATUS_ACTIVE,
        'unsubscribed' => NewsletterSubscriber::STATUS_UNSUBSCRIBED,
        'cleaned' => NewsletterSubscriber::STATUS_CLEANED,
    ];

    private const DATATYPES = [
        'text' => NewsletterField::TYPE_TEXT,
        'numeric' => NewsletterField::TYPE_NUMBER,
        'date' => NewsletterField::TYPE_DATE,
        'select_single' => NewsletterField::TYPE_SELECT,
        'select_multiple' => NewsletterField::TYPE_SELECT,
    ];

    /**
     * @param array<string, mixed> $member het lid zoals Laposta het teruggeeft
     * @param string $overgenomenOp de datum van deze overname, voor in het bewijs
     */
    public static function contact(array $member, string $overgenomenOp): ImportedContact
    {
        $state = (string) ($member['state'] ?? '');

        if (! isset(self::STATES[$state])) {
            throw new \InvalidArgumentException('Onbekende Laposta-status: ' . $state);
        }

        $signup = self::datum($member['signup_date'] ?? null);
        $ip = $member['ip'] ?? null;
        $origin = $member['source_url'] ?? null;

        return new ImportedContact(
            email: (string) ($member['email'] ?? ''),
            status: self::STATES[$state],
            fields: is_array($member['custom_fields'] ?? null) ? $member['custom_fields'] : [],
            subscribedAt: $signup,
            confirmedAt: self::datum($member['confirm_date'] ?? null),
            ip: $ip,
            source: 'laposta',
            origin: $origin,
            consentText: self::bewijstekst($overgenomenOp, $signup, $ip, $origin),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rawFields
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public static function fields(array $rawFields): array
    {
        $fields = [];

        foreach ($rawFields as $entry) {
            $field = $entry['field'] ?? $entry;
            $key = trim((string) ($field['tag'] ?? ''), '{} ');

            // Het e-mailadres is bij ons een kolom op het contact, geen zelf
            // gedefinieerd veld.
            if ($key === '' || $key === 'email') {
                continue;
            }

            $fields[] = [
                'key' => $key,
                'label' => (string) ($field['name'] ?? $key),
                'type' => self::DATATYPES[$field['datatype'] ?? ''] ?? NewsletterField::TYPE_TEXT,
            ];
        }

        return $fields;
    }

    private static function datum(mixed $waarde): ?CarbonImmutable
    {
        if (! $waarde) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $waarde);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Wat er als toestemmingsbewijs komt te staan. Laposta bewaart de tekst die
     * naast het vinkje stond niet, dus dat zeggen we erbij in plaats van te
     * doen alsof we hem hebben.
     */
    private static function bewijstekst(
        string $overgenomenOp,
        ?CarbonImmutable $signup,
        ?string $ip,
        ?string $origin,
    ): string {
        $tekst = 'Overgenomen uit Laposta op ' . $overgenomenOp . '.';

        if ($signup) {
            $tekst .= ' Oorspronkelijke aanmelding op ' . $signup->format('d-m-Y');
        }

        if ($ip) {
            $tekst .= ' vanaf ' . $ip;
        }

        if ($origin) {
            $tekst .= ' via ' . $origin;
        }

        return $tekst . '. De oorspronkelijke toestemmingstekst is door Laposta niet bewaard.';
    }
}
