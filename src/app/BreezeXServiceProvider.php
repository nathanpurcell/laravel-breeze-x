<?php

namespace Purcell\BreezeX;

use Illuminate\Support\ServiceProvider;

class BreezeXServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    //
    private function registerCommands()
    {
        // artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\BreezeInstallCommand::class,
            ]);
        }
    }
}
