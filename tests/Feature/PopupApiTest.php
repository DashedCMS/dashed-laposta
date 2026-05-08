<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Dashed\DashedPopups\Models\PopupView;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLaposta\Classes\PopupApis\PopupAPI;

function makeView(array $attributes = []): PopupView
{
    return new PopupView(array_merge([
        'id' => 1,
        'email' => 'visitor@example.com',
        'ip_address' => '127.0.0.1',
        'url' => 'https://example.test/landing',
        'referrer' => null,
        'device_type' => 'desktop',
        'locale' => 'nl',
    ], $attributes));
}

it('posts to laposta /member with the expected payload on success', function () {
    Customsetting::$values = [
        'laposta_api_key' => 'test-api-key',
        'laposta_connected' => true,
    ];

    Http::fake([
        'api.laposta.nl/*' => Http::response(['data' => ['member' => ['email' => 'visitor@example.com']]], 200),
    ]);

    $view = makeView();

    PopupAPI::dispatch($view, [
        'list_id' => 'list-abc',
        'customFields' => [
            ['field_id' => 'locale', 'field_name' => 'taal'],
        ],
    ]);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        return $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://api.laposta.nl/v2/member')
            && ($payload['email'] ?? null) === 'visitor@example.com'
            && ($payload['list_id'] ?? null) === 'list-abc'
            && ($payload['ip'] ?? null) === '127.0.0.1'
            && ($payload['source_url'] ?? null) === 'https://example.test/landing'
            && ($payload['custom_fields']['taal'] ?? null) === 'nl';
    });
});

it('does not throw when laposta replies that the email already exists', function () {
    Customsetting::$values = [
        'laposta_api_key' => 'test-api-key',
        'laposta_connected' => true,
    ];

    Http::fake([
        'api.laposta.nl/*' => Http::response(
            ['error' => ['message' => 'Email address exists']],
            400,
        ),
    ]);

    $view = makeView();

    expect(fn () => PopupAPI::dispatch($view, ['list_id' => 'list-abc']))
        ->not->toThrow(\Throwable::class);

    Http::assertSentCount(1);
});

it('throws when laposta returns any other error', function () {
    Customsetting::$values = [
        'laposta_api_key' => 'test-api-key',
        'laposta_connected' => true,
    ];

    Http::fake([
        'api.laposta.nl/*' => Http::response(
            ['error' => ['message' => 'List not found']],
            404,
        ),
    ]);

    $view = makeView();

    expect(fn () => PopupAPI::dispatch($view, ['list_id' => 'list-zzz']))
        ->toThrow(\RuntimeException::class);
});

it('is a no-op when laposta_api_key is missing', function () {
    Customsetting::$values = [];

    Http::fake();

    $view = makeView();

    PopupAPI::dispatch($view, ['list_id' => 'list-abc']);

    Http::assertNothingSent();
});

it('is a no-op when laposta_connected is false', function () {
    Customsetting::$values = [
        'laposta_api_key' => 'test-api-key',
        'laposta_connected' => false,
    ];

    Http::fake();

    $view = makeView();

    PopupAPI::dispatch($view, ['list_id' => 'list-abc']);

    Http::assertNothingSent();
});
