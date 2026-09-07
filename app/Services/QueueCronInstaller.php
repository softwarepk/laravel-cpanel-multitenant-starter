<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class QueueCronInstaller
{
    private const MARKER_PREFIX = 'laravel-cpanel-multitenant-starter-queue';

    /**
     * @return array{
     *     automatic:bool,
     *     php_binary:string|null,
     *     flock_binary:string|null,
     *     schedule:string,
     *     marker:string,
     *     command:string|null,
     *     worker_command:string|null,
     *     reason:string|null
     * }
     */
    public function plan(): array
    {
        $phpBinary = $this->detectPhpBinary();
        $flockBinary = $this->detectFlockBinary();
        $marker = $this->marker();
        $workerCommand = $phpBinary !== null ? $this->workerCommand($phpBinary) : null;
        $command = $phpBinary !== null && $flockBinary !== null
            ? $this->lockedCommand($flockBinary, $workerCommand, $marker)
            : null;

        $reason = null;
        if ($phpBinary === null) {
            $reason = 'A compatible PHP CLI binary could not be detected automatically.';
        } elseif ($flockBinary === null) {
            $reason = 'The flock utility was not detected, so the installer will not create a cron job that could launch overlapping workers.';
        }

        return [
            'automatic' => $command !== null,
            'php_binary' => $phpBinary,
            'flock_binary' => $flockBinary,
            'schedule' => '* * * * *',
            'marker' => $marker,
            'command' => $command,
            'worker_command' => $workerCommand,
            'reason' => $reason,
        ];
    }

    /** @return array{configured:bool,status:string,schedule:string,command:string|null,message:string} */
    public function configure(InstallerCpanelClient $cpanel): array
    {
        $plan = $this->plan();
        $command = $plan['command'];

        if (! $plan['automatic'] || ! is_string($command)) {
            return $this->manualResult(
                $plan,
                $plan['reason'] ?? 'Automatic queue cron configuration is unavailable on this host.',
            );
        }

        try {
            $jobs = $cpanel->cronJobs();
            $managedJobs = $this->managedJobs($jobs, $plan['marker']);

            foreach ($managedJobs as $job) {
                if ($this->cronMatches($job, $command)) {
                    return [
                        'configured' => true,
                        'status' => 'existing',
                        'schedule' => $plan['schedule'],
                        'command' => $command,
                        'message' => 'The queue worker cron job was already configured and has been left unchanged.',
                    ];
                }
            }

            if ($managedJobs !== []) {
                return $this->manualResult(
                    $plan,
                    'A managed queue cron entry already exists for this deployment but does not match the current worker command. Review it in cPanel Cron Jobs before replacing it.',
                );
            }

            $cpanel->addCronLine($command);

            foreach ($cpanel->cronJobs() as $job) {
                if ($this->cronMatches($job, $command)) {
                    return [
                        'configured' => true,
                        'status' => 'installed',
                        'schedule' => $plan['schedule'],
                        'command' => $command,
                        'message' => 'The installer created and verified the once-per-minute queue worker cron job.',
                    ];
                }
            }

            throw new RuntimeException('cPanel reported that the queue cron was added, but the new entry could not be verified.');
        } catch (Throwable $e) {
            report($e);

            return $this->manualResult(
                $plan,
                'Automatic cron configuration was not available through this cPanel account. Add the worker manually using cPanel Cron Jobs.',
            );
        }
    }

    /** @param list<array<string, mixed>> $jobs @return list<array<string, mixed>> */
    private function managedJobs(array $jobs, string $marker): array
    {
        return array_values(array_filter(
            $jobs,
            static fn (array $job): bool => str_contains((string) ($job['command'] ?? ''), $marker),
        ));
    }

    /** @param array<string, mixed> $job */
    private function cronMatches(array $job, string $command): bool
    {
        return (string) ($job['command'] ?? '') === $command
            && (string) ($job['minute'] ?? '') === '*'
            && (string) ($job['hour'] ?? '') === '*'
            && (string) ($job['day'] ?? '') === '*'
            && (string) ($job['month'] ?? '') === '*'
            && (string) ($job['weekday'] ?? '') === '*';
    }

    /**
     * @param array{automatic:bool,php_binary:string|null,flock_binary:string|null,schedule:string,marker:string,command:string|null,worker_command:string|null,reason:string|null} $plan
     * @return array{configured:bool,status:string,schedule:string,command:string|null,message:string}
     */
    private function manualResult(array $plan, string $message): array
    {
        return [
            'configured' => false,
            'status' => 'manual_required',
            'schedule' => $plan['schedule'],
            'command' => $plan['command'] ?? $plan['worker_command'],
            'message' => $message,
        ];
    }

    private function marker(): string
    {
        $deployment = rtrim(str_replace('\\', '/', base_path()), '/');

        return self::MARKER_PREFIX.'-'.substr(hash('sha256', $deployment), 0, 12);
    }

    private function workerCommand(string $phpBinary): string
    {
        return escapeshellarg($phpBinary).' '.escapeshellarg(base_path('artisan')).' queue:work database --queue=default --stop-when-empty --tries=1 --timeout=600';
    }

    private function lockedCommand(string $flockBinary, string $workerCommand, string $marker): string
    {
        return escapeshellarg($flockBinary).' -n '.escapeshellarg(storage_path('framework/queue-worker.lock')).' '.$workerCommand.' # '.$marker;
    }

    private function detectPhpBinary(): ?string
    {
        $configured = config('installer.queue_php_binary');
        if (is_string($configured) && $this->usableExecutable($configured)) {
            return $configured;
        }

        $version = PHP_MAJOR_VERSION.PHP_MINOR_VERSION;
        $binaryDirectory = dirname(PHP_BINARY);
        $candidates = [
            '/opt/alt/php'.$version.'/usr/bin/php',
            '/opt/cpanel/ea-php'.$version.'/root/usr/bin/php',
            $binaryDirectory.DIRECTORY_SEPARATOR.'php',
            PHP_BINARY,
            '/usr/local/bin/php',
            '/usr/bin/php',
        ];

        foreach (array_unique($candidates) as $candidate) {
            if ($this->usableExecutable($candidate) && $this->looksLikePhpCli($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function detectFlockBinary(): ?string
    {
        $configured = config('installer.queue_flock_binary');
        if (is_string($configured) && $this->usableExecutable($configured)) {
            return $configured;
        }

        foreach (['/usr/bin/flock', '/bin/flock'] as $candidate) {
            if ($this->usableExecutable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function usableExecutable(string $path): bool
    {
        $path = trim($path);
        if ($path === '' || ! is_file($path)) {
            return false;
        }

        return PHP_OS_FAMILY === 'Windows' || is_executable($path);
    }

    private function looksLikePhpCli(string $path): bool
    {
        $name = strtolower(pathinfo($path, PATHINFO_FILENAME));

        return $name === 'php';
    }
}
