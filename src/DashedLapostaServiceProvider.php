<?php

namespace Dashed\DashedLaposta;

use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedLaposta\Classes\FormApis\OrderAPI;
use Dashed\DashedLaposta\Commands\SyncLapostaLists;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedLaposta\Classes\FormApis\NewsletterAPI;
use Dashed\DashedLaposta\Filament\Pages\Settings\DashedLapostaSettingsPage;

class DashedLapostaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-laposta';

    public function bootingPackage()
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(SyncLapostaLists::class)->hourly();
        });

        cms()->registerSettingsDocs(
            page: \Dashed\DashedLaposta\Filament\Pages\Settings\DashedLapostaSettingsPage::class,
            title: 'Laposta instellingen',
            intro: 'Op deze pagina koppel je jouw webshop aan Laposta. Laposta is een Nederlands e-mail marketing platform voor nieuwsbrieven en mailings. Met deze koppeling kunnen klanten zich via formulieren op je website aanmelden voor mailinglijsten in jouw Laposta account. Werk je met meerdere sites? Dan kun je per site een eigen Laposta account koppelen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => 'De API key waarmee jouw webshop verbinding maakt met Laposta. Na een geldige koppeling worden de beschikbare mailinglijsten opgehaald zodat je ze in formulieren kunt gebruiken.',
                ],
                [
                    'heading' => 'Hoe zet je dit op?',
                    'body' => <<<MARKDOWN
1. Log in op je Laposta account.
2. Ga naar Instellingen en open het onderdeel API.
3. Maak een API key aan of kopieer een bestaande key.
4. Plak de API key op deze pagina en sla op.
5. Daarna kun je in je formulieren een Laposta mailinglijst koppelen aan een aanmeldveld.
MARKDOWN,
                ],
            ],
            fields: [
                'API key' => 'De API key uit je Laposta account. Deze sleutel is nodig om aanmeldingen vanuit jouw webshop door te zetten naar de juiste mailinglijst in Laposta. Behandel de key als een wachtwoord.',
            ],
        );
    }

    public function configurePackage(Package $package): void
    {
        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . config('dashed-core.site_theme', 'dashed')),
        ], 'dashed-templates');

        cms()->registerSettingsPage(DashedLapostaSettingsPage::class, 'Laposta', 'bell', 'Beheer instellingen voor Laposta');

        forms()->builder(
            'apiClasses',
            array_merge(forms()->builder('apiClasses'), [
                'laposta-newsletters-api' => [
                    'name' => 'Laposta newsletter API',
                    'class' => NewsletterAPI::class,
                ],
            ])
        );

        forms()->builder(
            'orderApiClasses',
            array_merge(forms()->builder('orderApiClasses'), [
                'laposta-order-api' => [
                    'name' => 'Laposta bestelling API',
                    'class' => OrderAPI::class,
                ],
            ])
        );

        $package
            ->name('dashed-laposta')
            ->hasCommands([
                SyncLapostaLists::class,
            ]);

        cms()->builder('plugins', [
            new DashedLapostaPlugin(),
        ]);
    }
}
