<?php

namespace NovaKit\SetupNova\Commands;

use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use TitasGailius\Terminal\Terminal;

class NewCommand extends Command
{
    use Concerns\PathFinders;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'new {name} {--issue}';

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

        if ($this->option('issue') === true) {
            $projectName = implode('-', ['issue', $projectName]);
        }

        $workingPath = getcwd().'/'.$projectName;

        $this->runLaravelInstaller($projectName);
        $this->configureDatabase($projectName, $workingPath);

        $this->call('install', [
            '--working-path' => $workingPath,
        ]);
    }

    /**
     * Run Laravel Installer.
     */
    protected function runLaravelInstaller(string $projectName): void
    {
        $this->task('Install Laravel', function () use ($projectName) {
            $laravel = $this->findLaravelInstaller();

            Terminal::builder()->in(getcwd())->run("{$laravel} new {$projectName}");

            return true;
        });
    }

    /**
     * Configure database.
     */
    protected function configureDatabase(string $projectName, string $workingPath): void
    {
        $this->task('Configure Database', function () use ($projectName, $workingPath) {
            $database = $this->menu('Choose Database Driver', [
                'mysql',
                'sqlite'
            ])->open();

            $phpBinary = $this->findPhpBinary();

            if ($database == 0) {
                return $this->configureMySqlDatabase($phpBinary, $projectName, $workingPath);
            } elseif ($database == 1) {
                return $this->configureSqliteDatabase($phpBinary, $projectName, $workingPath);
            }

            return false;
        });
    }

    /**
     * Configure database.
     */
    protected function configureMySqlDatabase(string $phpBinary, string $projectName, string $workingPath): bool
    {
        $db['HOST'] = $this->ask('Database Host?', $defaults['HOST'] = '127.0.0.1');
        $db['PORT'] = $this->ask('Database Port?', $defaults['PORT'] = '3306');
        $db['USERNAME'] = $this->ask('Database Username?', $defaults['USERNAME'] = 'root');
        $db['PASSWORD'] = $this->ask('Database Password?', $defaults['PASSWORD'] = null);
        $db['DATABASE'] = $this->ask('Database Name?', $defaults['DATABASE'] = Str::slug($projectName, '_'));

        Terminal::builder()
            ->in(getcwd())
            ->run(
                "mysql --user={$db['USERNAME']} --password={$db['PASSWORD']} --host={$db['HOST']} --port={$db['PORT']} -e \"create database {$db['DATABASE']};\""
            );

        $commands = collect();

        foreach (['HOST', 'PORT', 'USERNAME', 'PASSWORD', 'DATABASE'] as $type) {
            if ($db[$type] !== $defaults[$type]) {
                $commands->push(
                    "{$phpBinary} artisan --execute=\"file_put_contents('.env', str_replace(['DB_{$type}={$defaults[$type]}'], ['DB_{$type}={$db[$type]}'], file_get_contents('.env')));\""
                );
            }
        }

        foreach ($commands as $command) {
            Terminal::builder()->in($workingPath)->run($command);
        }

        return true;
    }

    protected function configureSqliteDatabase(string $phpBinary, string $projectName, string $workingPath): bool
    {
        $defaultDatabase = Str::slug($projectName, '_');

        touch("{$workingPath}/database/database.sqlite");

        $commands = collect([
            "{$phpBinary} artisan --execute=\"file_put_contents('.env', str_replace(['DB_CONNECTION=mysql'], ['DB_CONNECTION=sqlite'], file_get_contents('.env')));\"",
            "{$phpBinary} artisan --execute=\"file_put_contents('.env', str_replace(['DB_DATABASE={$defaultDatabase}'], ['# DB_DATABASE={$defaultDatabase}'], file_get_contents('.env')));\"",
        ]);

        foreach ($commands as $command) {
            Terminal::builder()->in($workingPath)->run($command);
        }

        return true;
    }
}
