<?php

use App\Services\InstallerCpanelClient;
use App\Services\QueueCronInstaller;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'installer.queue_php_binary' => PHP_BINARY,
        'installer.queue_flock_binary' => PHP_BINARY,
    ]);
});

it('adds and verifies a managed queue cron when one is missing', function (): void {
    $installer = app(QueueCronInstaller::class);
    $plan = $installer->plan();

    expect($plan['automatic'])->toBeTrue()
        ->and($plan['command'])->toBeString()
        ->and($plan['command'])->toContain('queue:work database')
        ->and($plan['command'])->toContain($plan['marker']);

    Http::fakeSequence()
        ->push(['cpanelresult' => ['event' => ['result' => 1], 'data' => [['count' => 0]]]])
        ->push(['cpanelresult' => ['event' => ['result' => 1], 'data' => [['status' => 1, 'statusmsg' => 'crontab installed']]]])
        ->push(['cpanelresult' => ['event' => ['result' => 1], 'data' => [[
            'command' => $plan['command'],
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'weekday' => '*',
        ]]]]);

    $result = $installer->configure(new InstallerCpanelClient('1.1.1.1', 2083, 'tester', str_repeat('a', 32)));

    expect($result['configured'])->toBeTrue()
        ->and($result['status'])->toBe('installed')
        ->and($result['schedule'])->toBe('* * * * *');
});

it('reuses an existing matching managed queue cron without adding another', function (): void {
    $installer = app(QueueCronInstaller::class);
    $plan = $installer->plan();

    Http::fake([
        '*' => Http::response(['cpanelresult' => ['event' => ['result' => 1], 'data' => [[
            'command' => $plan['command'],
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'weekday' => '*',
        ]]]]),
    ]);

    $result = $installer->configure(new InstallerCpanelClient('1.1.1.1', 2083, 'tester', str_repeat('a', 32)));

    expect($result['configured'])->toBeTrue()
        ->and($result['status'])->toBe('existing');

    Http::assertSentCount(1);
});

it('falls back to a manual queue action when cPanel cron management is unavailable', function (): void {
    $installer = app(QueueCronInstaller::class);

    Http::fake([
        '*' => Http::response(['cpanelresult' => [
            'event' => ['result' => 0],
            'error' => 'Cron feature is unavailable.',
            'data' => [],
        ]]),
    ]);

    $result = $installer->configure(new InstallerCpanelClient('1.1.1.1', 2083, 'tester', str_repeat('a', 32)));

    expect($result['configured'])->toBeFalse()
        ->and($result['status'])->toBe('manual_required')
        ->and($result['command'])->toBeString();
});
