<?php

namespace Dashed\DashedLaposta\Classes\PopupApis;

use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Dashed\DashedLaposta\Classes\Laposta;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedCore\Models\Customsetting;

class PopupAPI
{
    public static function dispatch(PopupView $view, array $api): void
    {
        $apiKey = Customsetting::get('laposta_api_key');

        if (! $apiKey || ! Customsetting::get('laposta_connected')) {
            return;
        }

        $data = [
            'ip' => $view->ip_address,
            'source_url' => $view->url,
            'list_id' => $api['list_id'] ?? null,
            'email' => $view->email,
        ];

        foreach ($api['customFields'] ?? [] as $customField) {
            $value = self::resolveFieldValue($view, $customField['field_id'] ?? null);
            if ($value !== null && $value !== '') {
                $data['custom_fields'][$customField['field_name']] = $value;
            }
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(Laposta::baseUrl().'member', $data);

        if ($response->failed() && ! str($response->body())->contains('Email address exists')) {
            throw new \RuntimeException('Laposta error for list '.($api['list_id'] ?? '?').': '.$response->body());
        }
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
            Repeater::make('customFields')
                ->label('Aangepaste velden')
                ->schema([
                    Select::make('field_id')
                        ->label('Veld uit popup')
                        ->options([
                            'email' => 'Email',
                            'url' => 'Pagina URL',
                            'referrer' => 'Referrer',
                            'device_type' => 'Apparaat type',
                            'locale' => 'Taal',
                        ]),
                    TextInput::make('field_name')
                        ->label('Veld naam in Laposta')
                        ->required(),
                ])
                ->columnSpanFull(),
        ];
    }

    protected static function resolveFieldValue(PopupView $view, ?string $fieldId): ?string
    {
        return match ($fieldId) {
            'email' => $view->email,
            'url' => $view->url,
            'referrer' => $view->referrer,
            'device_type' => $view->device_type,
            'locale' => $view->locale,
            default => null,
        };
    }
}
