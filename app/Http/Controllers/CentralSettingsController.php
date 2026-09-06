<?php

namespace App\Http\Controllers;

use App\Services\CentralAuditLogger;
use App\Services\CentralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CentralSettingsController extends Controller
{
    public function index(CentralSettings $settings): View
    {
        return view('central.settings.index', [
            'passwordMinimumLength' => $settings->passwordMinimumLength(),
            'passwordRequireMixedCase' => $settings->passwordRequireMixedCase(),
            'passwordRequireNumbers' => $settings->passwordRequireNumbers(),
            'passwordRequireSymbols' => $settings->passwordRequireSymbols(),
            'passwordRejectCompromised' => $settings->passwordRejectCompromised(),
            'platformDomain' => (string) config('central.platform.domain'),
            'customDomainDnsTarget' => (string) config('central.custom_domain.dns_target'),
            'platformDocumentRoot' => (string) config('central.platform.document_root'),
            'cpanelApiHost' => (string) config('central.cpanel.api_host'),
            'cpanelApiTimeout' => (int) config('central.cpanel.api_timeout', 90),
        ]);
    }

    public function update(Request $request, CentralSettings $settings, CentralAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'password_min_length' => ['required', 'integer', 'min:8', 'max:64'],
        ]);

        $previous = [
            'password_min_length' => $settings->passwordMinimumLength(),
            'password_require_mixed_case' => $settings->passwordRequireMixedCase(),
            'password_require_numbers' => $settings->passwordRequireNumbers(),
            'password_require_symbols' => $settings->passwordRequireSymbols(),
            'password_reject_compromised' => $settings->passwordRejectCompromised(),
        ];

        $values = [
            'password_min_length' => (int) $validated['password_min_length'],
            'password_require_mixed_case' => $request->boolean('password_require_mixed_case'),
            'password_require_numbers' => $request->boolean('password_require_numbers'),
            'password_require_symbols' => $request->boolean('password_require_symbols'),
            'password_reject_compromised' => $request->boolean('password_reject_compromised'),
        ];

        $settings->setMany($values);
        $audit->log('central.settings_updated', 'Central platform settings updated.', context: [
            'password_policy' => ['from' => $previous, 'to' => $values],
        ], request: $request);

        return back()->with('status', 'Platform settings saved.');
    }
}
