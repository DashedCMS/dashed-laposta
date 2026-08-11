<?php

namespace Dashed\DashedLaposta\Classes;

use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Models\Customsetting;

class Laposta
{
    public static function baseUrl(): string
    {
        return 'https://api.laposta.nl/v2/';
    }

    public static function isConnected(?string $siteId = null): bool
    {
        $apiKey = self::resolveApiKey($siteId);
        if (! $apiKey) {
            return false;
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get(self::baseUrl() . 'list');

        if ($response->status() === 200) {
            return true;
        } else {
            return false;
        }
    }

    public static function syncLists(?string $siteId = null): void
    {
        $apiKey = self::resolveApiKey($siteId);
        if (! $apiKey) {
            return;
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get(self::baseUrl() . 'list')
            ->json();

        if ($response['data'] ?? false) {
            Customsetting::set('laposta_lists', $response['data'], $siteId);
        }
    }

    /**
     * De lijsten zelf, in plaats van ze als Customsetting weg te schrijven zoals
     * syncLists() doet.
     *
     * Geeft null terug als het verzoek mislukte (of er geen API-sleutel is),
     * een array als het slaagde. Een lege array betekent dus altijd "geen
     * lijsten", nooit "kon niet ophalen".
     */
    public static function listsFor(?string $siteId = null): ?array
    {
        $apiKey = self::resolveApiKey($siteId);

        if (! $apiKey) {
            return null;
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->get(self::baseUrl() . 'list');

        if (! $response->successful()) {
            return null;
        }

        return $response->json()['data'] ?? [];
    }

    public static function fields(string $listId, ?string $siteId = null): ?array
    {
        return self::read('field', $listId, $siteId);
    }

    public static function members(string $listId, ?string $siteId = null): ?array
    {
        return self::read('member', $listId, $siteId);
    }

    /**
     * Alle leesverzoeken lopen hier langs, zodat er één plek is die weet hoe
     * er ingelogd wordt en wat er gebeurt als het misgaat.
     *
     * Geeft null terug bij een mislukt verzoek (ook een ontbrekende
     * API-sleutel telt als mislukt) en een array, eventueel leeg, als het
     * verzoek slaagde. Een lege array bij een mislukt verzoek zou een
     * aanroeper niet kunnen onderscheiden van een lijst die werkelijk leeg is.
     */
    private static function read(string $endpoint, string $listId, ?string $siteId): ?array
    {
        $apiKey = self::resolveApiKey($siteId);

        if (! $apiKey) {
            return null;
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->get(self::baseUrl() . $endpoint, ['list_id' => $listId]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * De enige plek die weet hoe er ingelogd wordt: vult het site-id aan
     * met de actieve site als het ontbreekt en geeft de API-sleutel terug,
     * of null als die er niet is.
     */
    private static function resolveApiKey(?string &$siteId): ?string
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        return Customsetting::get('laposta_api_key', $siteId) ?: null;
    }
}
