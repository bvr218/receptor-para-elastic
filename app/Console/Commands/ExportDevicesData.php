<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ElasticsearchService;

class ExportDevicesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */


    protected $elasticsearchService;

    public function __construct(ElasticsearchService $elasticsearchService)
    {
        parent::__construct();
        $this->elasticsearchService = $elasticsearchService;
    }


    public function handle()
    {
        $this->info("Iniciando exportación...");

        $filePath = $this->elasticsearchService->checkDevicesDataAndExport();

        $this->info("Exportación finalizada. Archivo generado en: " . $filePath);
    }
}
