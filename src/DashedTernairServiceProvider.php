<?php

namespace Dashed\DashedTernair;

use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedTernair\Livewire\Confirm;
use Dashed\DashedTernair\Livewire\Unsubscribe;
use Dashed\DashedTernair\Classes\FormWebhooks\Webhook;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedTernair\Classes\FormApis\NewsletterAPI;
use Dashed\DashedTernair\Filament\Pages\Settings\DashedTernairSettingsPage;

class DashedTernairServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-ternair';

    public function bootingPackage()
    {
        Livewire::component('dashed-ternair.newsletter-confirm', Confirm::class);
        Livewire::component('dashed-ternair.newsletter-unsubscribe', Unsubscribe::class);
    }

    public function configurePackage(Package $package): void
    {
        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . config('dashed-core.site_theme')),
        ], 'dashed-templates');

        cms()->registerSettingsPage(DashedTernairSettingsPage::class, 'Dashed Ternair', 'bell', 'Beheer instellingen voor Ternair');

        forms()->builder(
            'webhookClasses',
            array_merge(cms()->builder('webhookClasses'), [
                'ternair-webhook-1' => [
                    'name' => 'Ternair webhook',
                    'class' => Webhook::class,
                ],
            ])
        );

        forms()->builder(
            'apiClasses',
            array_merge(cms()->builder('apiClasses'), [
                'ternair-newsletters-api' => [
                    'name' => 'Ternair newsletter API',
                    'class' => NewsletterAPI::class,
                ],
            ])
        );

        $package
            ->name('dashed-ternair');

        cms()->builder('plugins', [
            new DashedTernairPlugin(),
        ]);
    }
}
