<?php

namespace Dashed\DashedLaposta;

use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedLaposta\Livewire\Confirm;
use Dashed\DashedLaposta\Livewire\Unsubscribe;
use Dashed\DashedLaposta\Classes\FormWebhooks\Webhook;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedLaposta\Classes\FormApis\NewsletterAPI;
use Dashed\DashedLaposta\Filament\Pages\Settings\DashedLapostaSettingsPage;

class DashedLapostaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-laposta';

    public function bootingPackage()
    {
        Livewire::component('dashed-laposta.newsletter-confirm', Confirm::class);
        Livewire::component('dashed-laposta.newsletter-unsubscribe', Unsubscribe::class);
    }

    public function configurePackage(Package $package): void
    {
        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . config('dashed-core.site_theme')),
        ], 'dashed-templates');

        cms()->registerSettingsPage(DashedLapostaSettingsPage::class, 'Dashed Laposta', 'bell', 'Beheer instellingen voor Laposta');

        forms()->builder(
            'webhookClasses',
            array_merge(cms()->builder('webhookClasses'), [
                'laposta-webhook-1' => [
                    'name' => 'Laposta webhook',
                    'class' => Webhook::class,
                ],
            ])
        );

        forms()->builder(
            'apiClasses',
            array_merge(cms()->builder('apiClasses'), [
                'laposta-newsletters-api' => [
                    'name' => 'Laposta newsletter API',
                    'class' => NewsletterAPI::class,
                ],
            ])
        );

        $package
            ->name('dashed-laposta');

        cms()->builder('plugins', [
            new DashedLapostaPlugin(),
        ]);
    }
}
