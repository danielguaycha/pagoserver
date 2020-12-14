<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;

class Install extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return mixed
     */
    public function handle()
    {
        if(!config('app.debug')) {
            $yesOrNot = $this->ask(
                'DETENTE! Estas seguro que deseas eliminar todos tus datos? y/n',
                'Borraras toda la información de tu base de datos'
            );
            if(strtolower(trim($yesOrNot)) === 'y') {
                $this->resetAll();
            } else {
                $this->info('Operación cancelada');
            }
        }
        else {
            $this->resetAll();
        }
    }

    protected function resetAll() {
        $this->info('Reiniciando base de datos...');
        Artisan::call('db:wipe');
        $this->info('Creando migraciones y semillas...');
        Artisan::call('migrate', ['--seed'=> 'default']);
        $this->createPersonalClient();    
        Artisan::call('passport:keys');        
        $this->line('Proceso finalizado con éxito');
    }

    protected function createPersonalClient()
    {
        $this->info('Creando token...');
        $name = 'Android';
        $clients = new ClientRepository();
        $clients->createPasswordGrantClient(
            null, $name, 'http://localhost'
        );

    }
}
