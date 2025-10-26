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
}
