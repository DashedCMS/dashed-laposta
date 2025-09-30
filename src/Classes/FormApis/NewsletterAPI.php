<?php

namespace Dashed\DashedTernair\Classes\FormApis;

use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormInput;
use Filament\Forms\Components\TextInput;
use Dashed\DashedCore\Models\Customsetting;

class NewsletterAPI
{
    public static function dispatch(FormInput $formInput, $api)
    {
        if (! Customsetting::get('ternair_api_username') || ! Customsetting::get('ternair_api_password')) {
            return;
        }

        $data = [];
        $data['IpAddress'] = $formInput->ip;
        $data['EzineCode'] = $api['EzineCode'];
        $data['SendOptinMail'] = $api['SendOptinMail'] ? 1 : 0;
        $data['SendConfirmationMail'] = $api['SendConfirmationMail'] ? 1 : 0;
        $data['Email'] = $formInput->formFields->where('form_field_id', $api['email_field_id'] ?? '')->first()->value ?? null;
        $data['Fingerprint'] = $formInput->formFields->where('form_field_id', $api['fingerprint_field_id'] ?? '')->first()->value ?? null;
        $data['Tid'] = $formInput->formFields->where('form_field_id', $api['tid_field_id'] ?? '')->first()->value ?? null;
        $data['Firstname'] = $formInput->formFields->where('form_field_id', $api['firstname_field_id'] ?? '')->first()->value ?? null;
        $data['Middlename'] = $formInput->formFields->where('form_field_id', $api['middlename_field_id'] ?? '')->first()->value ?? null;
        $data['Lastname'] = $formInput->formFields->where('form_field_id', $api['lastname_field_id'] ?? '')->first()->value ?? null;

        foreach ($formInput->formFields as $field) {
            $data['data'][$field->formField->name] = $field->formField->type == 'file' ? Storage::disk('dashed')->url($field->value) : $field->value;
        }

        $data['Properties'] = [
            [
                'Key' => 'taalcode',
                'Value' => app()->getLocale(),
            ],
        ];

        $optinHerkomst = $formInput->formFields->where('form_field_id', $api['optin_herkomsts'] ?? '')->first()->value ?? null;
        if ($optinHerkomst) {
            $data['Properties'][] = [
                'Key' => 'OptinHerkomst',
                'Value' => $optinHerkomst,
            ];
        }

        foreach (str(str($formInput->from_url)->explode('?')->last())->explode('&') as $query) {
            $query = str($query)->explode('=');
            $data[$query[0]] = $query[1] ?? '';
            $data['queryParams'][$query[0]] = $query[1] ?? '';
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-Application' => Customsetting::get('ternair_x_api_application_header'),
        ])
            ->withBasicAuth(Customsetting::get('ternair_api_username'), Customsetting::get('ternair_api_password'))
            ->post('https://campaign3-interact-api.ternairsoftware.com/subscription/newsletter', $data);

        if ($response->failed()) {
            $formInput->api_error = $response->body();
        }

        $formInput->api_send = $response->successful() ? 1 : 2;
        $formInput->save();
    }

    public static function formFields(): array
    {
        return [
            TextInput::make('EzineCode')
                ->label('Ezine code')
                ->required(),
            Toggle::make('SendOptinMail')
                ->label('Stuur optin mail'),
            Toggle::make('SendConfirmationMail')
                ->label('Stuur bevestigingsmail'),
            Select::make('email_field_id')
                ->label('Email veld')
                ->required()
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->where('input_type', 'email')->pluck('name', 'id') : []),
            Select::make('firstname_field_id')
                ->label('Voornaam veld')
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
            Select::make('middlename_field_id')
                ->label('Tussenvoegsel veld')
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
            Select::make('lastname_field_id')
                ->label('Achternaam veld')
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
            Select::make('tid_field_id')
                ->label('TID veld')
                ->required()
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
            Select::make('fingerprint_field_id')
                ->label('Fingerprint veld')
                ->required()
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
            Select::make('optin_herkomsts')
                ->label('Optin Herkomst veld')
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
        ];
    }

    public static function confirm(string $aapKey, ?string $tid = null): void
    {
        if (! Customsetting::get('ternair_api_username') || ! Customsetting::get('ternair_api_password')) {
            return;
        }

        $data = [
            'aapKey' => $aapKey,
        ];

        if ($tid) {
            $data['tid'] = $tid;
        }

        $url = 'https://campaign3-interact-api.ternairsoftware.com/subscription/confirm' . '?' . http_build_query($data);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-Application' => Customsetting::get('ternair_x_api_application_header'),
        ])
            ->withBasicAuth(Customsetting::get('ternair_api_username'), Customsetting::get('ternair_api_password'))
            ->post($url);

        if ($response->failed() && app()->isLocal()) {
            throw new \Exception('Failed to unsubscribe from newsletter with error ' . $response->body());
        }
    }

    public static function unsubscribe(string $ezineCode, string $tid): void
    {
        if (! Customsetting::get('ternair_api_username') || ! Customsetting::get('ternair_api_password')) {
            return;
        }

        $data = [
            'ezineCodes' => $ezineCode,
            'tid' => $tid,
        ];

        $url = 'https://campaign3-interact-api.ternairsoftware.com/subscription/unsubscribe' . '?' . http_build_query($data);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-Application' => Customsetting::get('ternair_x_api_application_header'),
        ])
            ->withBasicAuth(Customsetting::get('ternair_api_username'), Customsetting::get('ternair_api_password'))
            ->post($url);

        if ($response->failed() && app()->isLocal()) {
            throw new \Exception('Failed to unsubscribe from newsletter with error ' . $response->body());
        }
    }
}
