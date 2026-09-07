<?php

namespace App\Services;

use RuntimeException;

class InstallationState
{
    public function __construct(private readonly EnvironmentFile $environment) {}

    public function requiresInstallation(): bool
    {
        if ($this->isComplete() || $this->isLegacyConfigured()) {
            return false;
        }

        return $this->isPending() || $this->isProductionEnvironment();
    }

    public function isComplete(): bool
    {
        return (bool) config('installer.complete', false) || is_file($this->completeFile());
    }

    public function isPending(): bool
    {
        return is_file($this->pendingFile());
    }

    /** @param array<string, scalar|null> $metadata */
    public function begin(array $metadata = []): void
    {
        $this->ensureDirectory(dirname($this->pendingFile()));

        $existing = $this->pendingMetadata();
        $payload = json_encode(array_merge(
            ['started_at' => $existing['started_at'] ?? now()->toIso8601String()],
            $existing,
            $metadata,
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($payload === false || file_put_contents($this->pendingFile(), $payload.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to create the installation pending marker.');
        }
    }

    /** @return array<string, mixed> */
    public function pendingMetadata(): array
    {
        if (! $this->isPending()) {
            return [];
        }

        $contents = @file_get_contents($this->pendingFile());
        if (! is_string($contents) || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function pendingDatabaseMatches(string $database): bool
    {
        $stored = $this->pendingMetadata()['central_database'] ?? null;

        return is_string($stored) && hash_equals($stored, $database);
    }

    /** @param array<string, scalar|null> $metadata */
    public function complete(array $metadata = []): void
    {
        $this->ensureDirectory(dirname($this->completeFile()));

        $payload = json_encode(array_merge([
            'completed_at' => now()->toIso8601String(),
        ], $metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($payload !== false) {
            @file_put_contents($this->completeFile(), $payload.PHP_EOL, LOCK_EX);
        }

        if (is_file($this->pendingFile())) {
            @unlink($this->pendingFile());
        }
    }

    private function isLegacyConfigured(): bool
    {
        if (! $this->isProductionEnvironment() || $this->isPending()) {
            return false;
        }

        $override = config('installer.legacy_configured');
        if (is_bool($override)) {
            return $override;
        }

        return $this->environment->hasConfiguredApplicationKey();
    }

    private function isProductionEnvironment(): bool
    {
        return strtolower((string) config('app.env')) === 'production';
    }

    private function pendingFile(): string
    {
        return (string) config('installer.pending_file', storage_path('app/installation.pending'));
    }

    private function completeFile(): string
    {
        return (string) config('installer.complete_file', storage_path('app/installation.complete'));
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create installer directory [{$directory}].");
        }
    }
}
