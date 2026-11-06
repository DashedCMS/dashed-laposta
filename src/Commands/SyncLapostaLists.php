<?php

namespace Dashed\DashedLaposta\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLaposta\Classes\Laposta;

class SyncLapostaLists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:sync-laposta-lists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Laposta lists for all sites';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Zonder API-sleutel doet syncLists() niets. Lukt het lezen niet, dan draait
     * het commando gewoon.
     */
    public static function hasWork(): bool
    {
        return rescue(fn () => (bool) Customsetting::get('laposta_api_key', Sites::getActive()), true, false);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Laposta::syncLists(Sites::getActive());

        return 0;
    }
}
