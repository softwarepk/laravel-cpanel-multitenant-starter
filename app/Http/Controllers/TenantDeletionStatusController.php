<?php

namespace App\Http\Controllers;

use App\Models\TenantDeletionRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TenantDeletionStatusController extends Controller
{
    public function __invoke(string $tenantId): JsonResponse
    {
        $record = TenantDeletionRecord::query()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        if (! $record instanceof TenantDeletionRecord) {
            return response()->json([
                'tenant_id' => $tenantId,
                'status' => 'starting',
                'progress' => 5,
                'message' => 'Starting permanent deletion…',
                'stale' => false,
            ]);
        }

        $cleanupResults = $record->getAttribute('cleanup_results');
        $customDomainValues = $record->getAttribute('custom_domains');
        $results = is_array($cleanupResults) ? $cleanupResults : [];
        $customDomains = is_array($customDomainValues) ? $customDomainValues : [];
        $totalSteps = 4 + count($customDomains);
        $completedSteps = count($results);
        $status = (string) $record->status;
        $stale = $status === 'started'
            && $record->updated_at !== null
            && $record->updated_at->lt(now()->subMinutes(2));

        $progress = match ($status) {
            'completed', 'completed_with_warnings', 'failed' => 100,
            default => min(94, 8 + (int) floor(($completedSteps / max(1, $totalSteps)) * 84)),
        };

        $message = match ($status) {
            'completed' => 'Tenant deletion and managed infrastructure cleanup completed.',
            'completed_with_warnings' => 'Tenant deleted. Some infrastructure cleanup needs manual attention.',
            'failed' => 'Permanent deletion failed before the central tenant record could be removed.',
            default => $this->progressMessage($results, $customDomains, $stale),
        };

        return response()->json([
            'tenant_id' => $tenantId,
            'record_id' => $record->getKey(),
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'stale' => $stale,
            'cleanup_results' => $results,
            'redirect' => in_array($status, ['completed', 'completed_with_warnings'], true)
                ? route('central.tenants.index')
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  list<mixed>  $customDomains
     */
    private function progressMessage(array $results, array $customDomains, bool $stale): string
    {
        if ($stale) {
            return 'Deletion has not advanced recently. The previous synchronous request may have been interrupted; recheck the status or retry deletion from this tenant page.';
        }

        if (! array_key_exists('platform_domain', $results)) {
            return 'Removing the permanent platform hostname…';
        }

        $completedCustomDomains = collect(array_keys($results))
            ->filter(fn (string $key): bool => Str::startsWith($key, 'custom_domain:'))
            ->count();

        if ($completedCustomDomains < count($customDomains)) {
            return 'Removing managed custom domains…';
        }

        if (! array_key_exists('storage', $results)) {
            return 'Removing tenant storage…';
        }

        if (! array_key_exists('database', $results)) {
            return 'Removing the isolated tenant database…';
        }

        return 'Removing the central tenant record and finalizing cleanup history…';
    }
}
