<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--path= : Directory outside the public web root}';

    protected $description = 'Write a database backup outside the public web root';

    public function handle(): int
    {
        $directory = $this->option('path') ?: storage_path('app/private/backups');
        $public = realpath(public_path()) ?: public_path();
        if (str_starts_with(str_replace('\\', '/', $directory), str_replace('\\', '/', $public))) {
            $this->error('Choose a backup directory outside the public web root.');

            return self::FAILURE;
        }
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('The backup directory could not be created.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $stamp = now()->format('Ymd-His');
        if ($connection === 'sqlite') {
            $source = (string) config('database.connections.sqlite.database');
            if ($source === ':memory:' || ! is_file($source)) {
                $this->error('An in-memory or missing SQLite database cannot be copied. Use a file database or MySQL for a backup.');

                return self::FAILURE;
            }
            $target = $directory.DIRECTORY_SEPARATOR.'oneflo-'.$stamp.'.sqlite';
            if (! copy($source, $target)) {
                $this->error('The SQLite backup could not be copied.');

                return self::FAILURE;
            }
            $this->info($target);

            return self::SUCCESS;
        }

        if ($connection !== 'mysql') {
            $this->error('This backup command supports MySQL and file-based SQLite.');

            return self::FAILURE;
        }

        $config = config('database.connections.mysql');
        $target = $directory.DIRECTORY_SEPARATOR.'oneflo-'.$stamp.'.sql';
        $process = new Process([
            'mysqldump',
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--result-file='.$target,
            (string) $config['database'],
        ], null, ['MYSQL_PWD' => (string) $config['password']]);
        $process->setTimeout(300);
        $process->run();
        if (! $process->isSuccessful() || ! is_file($target)) {
            $this->error('mysqldump failed. Confirm the client is installed and the database credentials are valid.');

            return self::FAILURE;
        }
        $this->info($target);

        return self::SUCCESS;
    }
}
