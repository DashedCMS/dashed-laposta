<?php

namespace Dashed\DashedForms\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
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
