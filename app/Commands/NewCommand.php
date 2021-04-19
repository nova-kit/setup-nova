<?php

namespace NovaKit\SetupNova\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class NewCommand extends Command
{
    use Concerns\PathFinders;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'new {name} {--db-user=root} {--db-password=} {--db-host="127.0.0.1"}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new Laravel application with Nova';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $projectName = $this->argument('name');
        $laravel = $this->findLaravelInstaller();

        Terminal::builder()->in(getcwd())->run("{$laravel} new {$projectName}");

        $this->setupDatabase($projectName);

        $this->call('setup', [
            '--working-path' => getcwd().'/'.$projectName,
        ]);
    }

    /**
     * Setup database.
     */
    protected function setupDatabase(string $projectName): void
    {
        $mysql = $this->findMySqlBinary();
        $host = $this->option('db-host');
        $user = $this->option('db-user');
        $password = $this->option('db-password');

        Terminal::builder()
            ->in(getcwd())
            ->run(
                "{$mysql} --user={$user} --password={$password} --host={$host} -e \"create database {".Str::slug($projectName, '_')."};\""
            );
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
