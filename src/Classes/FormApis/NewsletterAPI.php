<?php

namespace Dashed\DashedLaposta\Classes\FormApis;

use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Dashed\DashedForms\Models\FormInput;
use Filament\Forms\Components\TextInput;
use Dashed\DashedLaposta\Classes\Laposta;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Contracts\SupportsEmailBackfill;

class NewsletterAPI implements SupportsEmailBackfill
{
    public static function dispatch(FormInput $formInput, $api)
    {
        $apiKey = Customsetting::get('laposta_api_key');

        if (! $apiKey || ! Customsetting::get('laposta_connected')) {
            return;
        }

        $data = [];
        $data['ip'] = $formInput->ip;
        $data['source_url'] = $formInput->from_url;
        $data['list_id'] = $api['list_id'];
        $data['email'] = $formInput->formFields->where('form_field_id', $api['email_field_id'] ?? '')->first()->value ?? null;
        foreach ($api['customFields'] as $customField) {
            $value = $formInput->formFields->where('form_field_id', $customField['field_id'] ?? '')->first()->value ?? null;
            if ($value) {
                $data['custom_fields'][$customField['field_name']] = $value;
            }
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post(Laposta::baseUrl() . 'member', $data);

        if ($response->failed() && ! str($response->body())->contains('Email address exists')) {
            $formInput->api_error = $response->body();
            $formInput->save();

            return;
        }

        $formInput->api_error = null;
        $formInput->api_send = 1;
        $formInput->save();
    }

    public static function formFields(): array
    {
        return [
            Select::make('list_id')
                ->label('Lijst om aan toe te voegen')
                ->required()
                ->options(function () {
                    $lists = Customsetting::get('laposta_lists');
                    $options = [];
                    if ($lists) {
                        foreach ($lists as $list) {
                            $options[$list['list']['list_id']] = $list['list']['name'];
                        }
                    }

                    return $options;
                }),
            Select::make('email_field_id')
                ->label('Email veld')
                ->required()
                ->columnSpanFull()
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->where('input_type', 'email')->pluck('name', 'id') : []),
            Repeater::make('customFields')
                ->label('Aangepaste velden')
                ->schema([
                    Select::make('field_id')
                        ->label('Veld')
                        ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
                    TextInput::make('field_name')
                        ->label('Veld naam in Laposta')
                        ->required(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Backfill-pad: voegt een (email, voornaam, achternaam) toe aan de geconfigureerde Laposta lijst.
     */
    public static function syncEmail(string $email, ?string $firstName, ?string $lastName, array $api): array
    {
        $apiKey = Customsetting::get('laposta_api_key');

        if (! $apiKey || ! Customsetting::get('laposta_connected')) {
            return ['status' => 'skipped', 'error' => 'Laposta niet geconfigureerd of niet verbonden'];
        }

        if (empty($api['list_id'])) {
            return ['status' => 'skipped', 'error' => 'Geen list_id geconfigureerd'];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'skipped', 'error' => 'Ongeldig e-mailadres'];
        }

        $data = [
            'list_id' => $api['list_id'],
            'email' => $email,
            'ip' => '0.0.0.0',
            'source_url' => config('app.url'),
        ];

        $customFields = [];
        if ($firstName !== null && $firstName !== '') {
            $customFields['first_name'] = $firstName;
        }
        if ($lastName !== null && $lastName !== '') {
            $customFields['last_name'] = $lastName;
        }
        if (! empty($customFields)) {
            $data['custom_fields'] = $customFields;
        }

        try {
            $response = Http::withBasicAuth($apiKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(Laposta::baseUrl() . 'member', $data);
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }

        if ($response->successful()) {
            return ['status' => 'success', 'error' => null];
        }

        $body = (string) $response->body();
        $decoded = json_decode($body, true);
        $message = $decoded['error']['message'] ?? '';

        // Laposta rate-limit signaal. De backfill-job vangt dit op en
        // released zichzelf naar de queue met een delay.
        if ($response->status() === 429 || str_contains($message, 'Rate limit')) {
            $retryAfter = 60;
            if (preg_match('/(\d+)\s*second/i', $message, $m)) {
                $retryAfter = max(1, (int) $m[1]);
            } elseif ($response->header('Retry-After')) {
                $retryAfter = max(1, (int) $response->header('Retry-After'));
            }

            return ['status' => 'rate_limited', 'retry_after' => $retryAfter, 'error' => $message ?: 'Rate limit'];
        }

        if (str_contains($body, 'Email address exists')) {
            return ['status' => 'success', 'error' => null];
        }

        return ['status' => 'failed', 'error' => mb_substr($body, 0, 1000)];
    }
}
