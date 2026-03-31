<?php

namespace Dashed\DashedLaposta\Classes\FormApis;

use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Dashed\DashedLaposta\Classes\Laposta;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

class OrderAPI
{
    public static function dispatch(Order $order, $api)
    {
        $apiKey = Customsetting::get('laposta_api_key');

        if (! $apiKey || ! Customsetting::get('laposta_connected')) {
            return;
        }

        $data = [];
        $data['ip'] = $order->ip;
        $data['source_url'] = $order->url;
        $data['list_id'] = $api['list_id'];
        $data['email'] = $order->{$api['email_field_id'] ?? ''} ?? '';
        foreach ($api['customFields'] as $customField) {
            $value = $order->{$api['email_field_id'] ?? ''} ?? '';
            if ($value) {
                $data['custom_fields'][$customField['field_name']] = $value;
            }
        }

        try {
            $response = Http::withBasicAuth($apiKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post(Laposta::baseUrl() . 'member', $data);
        } catch (\Exception $e) {
            OrderLog::createLog($order->id, note: 'Fout bij toevoegen aan Laposta lijst ' . $data['list_id'] . ' : ' . $e->getMessage());

            return;
        }

        if (! $response || $response->failed()) {
            OrderLog::createLog($order->id, note: 'Fout bij toevoegen aan Laposta lijst ' . $data['list_id'] . ' : ' . ($response->body() ?? 'Geen response van Laposta API'));
        } else {
            OrderLog::createLog($order->id, note: 'Succesvol toegevoegd aan Laposta lijst ' . $data['list_id'] . ' .');
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
            Select::make('email_field_id')
                ->label('Email veld')
                ->required()
                ->columnSpanFull()
                ->options(Order::getMarketingFields()),
            Repeater::make('customFields')
                ->label('Aangepaste velden')
                ->schema([
                    Select::make('field_id')
                        ->label('Veld')
                        ->options(Order::getMarketingFields()),
                    TextInput::make('field_name')
                        ->label('Veld naam in Laposta')
                        ->required(),
                ])
                ->columnSpanFull(),
        ];
    }
}
