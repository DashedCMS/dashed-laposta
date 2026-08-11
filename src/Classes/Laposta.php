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
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $apiKey = Customsetting::get('laposta_api_key', $siteId);
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
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $apiKey = Customsetting::get('laposta_api_key', $siteId);
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

    public static function fields(string $listId, ?string $siteId = null): array
    {
        return self::read('field', $listId, $siteId);
    }

    public static function members(string $listId, ?string $siteId = null): array
    {
        return self::read('member', $listId, $siteId);
    }

    /**
     * Alle leesverzoeken lopen hier langs, zodat er één plek is die weet hoe
     * er ingelogd wordt en wat er gebeurt als het misgaat: een lege array en
     * geen halve waarheid.
     */
    private static function read(string $endpoint, string $listId, ?string $siteId): array
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $apiKey = Customsetting::get('laposta_api_key', $siteId);

        if (! $apiKey) {
            return [];
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->get(self::baseUrl() . $endpoint, ['list_id' => $listId]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json()['data'] ?? [];
    }
}
