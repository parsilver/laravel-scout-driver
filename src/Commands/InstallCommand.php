<?php

namespace Farzai\ScoutDriver\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farzai:scout:install {driver : Driver name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install dependencies for scout driver';

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
        $name = $this->getDriverName();

        $this->comment("Installing {$name} driver...");

        if ($this->callInstaller(Str::camel($name)) !== false) {
            $this->info("Scout driver {$name} installed successfully.");
        }

        return 0;
    }


    private function getDriverName()
    {
        $drivers = array_keys(require __DIR__.'/../../config.php');

        $name = strtolower($this->argument('driver'));

        if (in_array($name, $drivers)) {
            return $name;
        }

        throw new \InvalidArgumentException("Only supported driver: ".implode(', ', $drivers));
    }

    /**
     * @param string $driver
     * @return mixed|void
     */
    private function callInstaller(string $driver)
    {
        $method = "{$driver}Installer";

        if (method_exists($this, $method)) {
            $installer = $this->$method();
            $installer();
        }
    }

    /**
     * @return \Closure
     */
    private function appSearchInstaller()
    {
        return function () {
            if (! class_exists('Elastic\EnterpriseSearch\Client')) {
                $this->requireComposerPackages(['--with-all-dependencies', "elastic/enterprise-search"]);
            }
        };
    }

    /**
     * Installs the given Composer Packages into the application.
     *
     * @param  mixed  $packages
     * @return void
     */
    private function requireComposerPackages($packages)
    {
        $command = array_merge(
            ['composer', 'require'],
            is_array($packages) ? $packages : func_get_args()
        );

        (new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output) {
                $this->output->write($output);
            });
    }
}
