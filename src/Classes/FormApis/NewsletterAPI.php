<?php

namespace Dashed\DashedLaposta\Classes\FormApis;

use Dashed\DashedLaposta\Classes\Laposta;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
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
        $apiKey = Customsetting::get('laposta_api_key');

        if (!$apiKey || !Customsetting::get('laposta_connected')) {
            return;
        }

        $data = [];
        $data['ip'] = $formInput->ip;
        $data['source_url'] = $formInput->from_url;
        $data['list_id'] = $api['list_id'];
        $data['email'] = $formInput->formFields->where('form_field_id', $api['email_field_id'] ?? '')->first()->value ?? null;
        foreach( $api['customFields'] as $customField ) {
            $value = $formInput->formFields->where('form_field_id', $customField['field_id'] ?? '')->first()->value ?? null;
            if ($value) {
                $data['custom_fields'][$customField['field_name']] = $value;
            }
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->post(Laposta::baseUrl() . 'member', $data);

        if ($response->failed() && !str($response->body())->contains('Email address exists')) {
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
                ->options(fn($record) => $record ? $record->fields()->where('type', 'input')->where('input_type', 'email')->pluck('name', 'id') : []),
            Repeater::make('customFields')
                ->label('Aangepaste velden')
                ->schema([
                    Select::make('field_id')
                        ->label('Veld')
                        ->options(fn($record) => $record ? $record->fields()->where('type', 'input')->pluck('name', 'id') : []),
                    TextInput::make('field_name')
                        ->label('Veld naam in Laposta')
                        ->required(),
                ])
                ->columnSpanFull(),
        ];
    }

}
