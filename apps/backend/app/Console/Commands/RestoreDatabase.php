<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RestoreDatabase extends Command
{
    protected $signature = 'backup:restore {path : SQL file under storage/app/private/backups} {--confirm : Replace the current MySQL database}';

    protected $description = 'Restore a MySQL backup from outside the public web root';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Re-run with --confirm after checking the backup file. This replaces the current database.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        $public = str_replace('\\', '/', realpath(public_path()) ?: public_path());
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, '/public/') || str_starts_with($normalized, $public)) {
            $this->error('Refusing to read a backup from the public web root.');

            return self::FAILURE;
        }

        $allowed = str_replace('\\', '/', storage_path('app/private/backups'));
        if (! str_starts_with($normalized, $allowed)) {
            $this->error('Restore only from storage/app/private/backups.');

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error('The backup file was not found.');

            return self::FAILURE;
        }

        if ((string) config('database.default') !== 'mysql') {
            $this->error('Restore supports the MySQL connection used in production.');

            return self::FAILURE;
        }

        $config = config('database.connections.mysql');
        $process = new Process([
            'mysql',
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--user='.$config['username'],
            (string) $config['database'],
        ], null, ['MYSQL_PWD' => (string) $config['password']]);
        $process->setTimeout(300);
        $process->setInput(file_get_contents($path) ?: '');
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error('mysql restore failed. Confirm the client is installed and the backup is a MySQL dump.');

            return self::FAILURE;
        }

        $this->info('Database restored.');

        return self::SUCCESS;
    }
}
