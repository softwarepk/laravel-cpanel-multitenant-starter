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
            'platformDomain' => (string) config('central.platform.domain'),
            'customDomainDnsTarget' => (string) config('central.custom_domain.dns_target'),
            'platformDocumentRoot' => (string) config('central.platform.document_root'),
            'cpanelApiHost' => (string) config('central.cpanel.api_host'),
            'cpanelApiTimeout' => (int) config('central.cpanel.api_timeout', 90),
        ]);
    }

    public function update(Request $request, CentralSettings $settings, CentralAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['password_min_length' => ['required', 'integer', 'min:8', 'max:64']]);
        $previous = $settings->passwordMinimumLength();
        $settings->setMany(['password_min_length' => (int) $validated['password_min_length']]);
        $audit->log('central.settings_updated', 'Central platform settings updated.', context: ['password_min_length' => ['from' => $previous, 'to' => (int) $validated['password_min_length']]], request: $request);

        return back()->with('status', 'Platform settings saved.');
    }
}
