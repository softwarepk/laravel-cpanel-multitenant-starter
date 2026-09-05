<?php

namespace App\Providers;

use App\Contracts\CustomDomainProvisioner;
use App\Contracts\CustomDomainVerifier;
use App\Contracts\PlatformDomainDeprovisioner;
use App\Contracts\TenantDatabaseProvisioner;
use App\Contracts\TenantDomainProvisioner;
use App\Services\CentralSettings;
use App\Services\CpanelApi2CustomDomainProvisioner;
use App\Services\CpanelApi2PlatformDomainDeprovisioner;
use App\Services\CpanelClient;
use App\Services\CpanelCustomDomainVerifier;
use App\Services\CpanelUapiTenantDatabaseProvisioner;
use App\Services\CpanelUapiTenantDomainProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CpanelClient::class);
        $this->app->bind(TenantDatabaseProvisioner::class, CpanelUapiTenantDatabaseProvisioner::class);
        $this->app->bind(TenantDomainProvisioner::class, CpanelUapiTenantDomainProvisioner::class);
        $this->app->bind(PlatformDomainDeprovisioner::class, CpanelApi2PlatformDomainDeprovisioner::class);
        $this->app->bind(CustomDomainProvisioner::class, CpanelApi2CustomDomainProvisioner::class);
        $this->app->bind(CustomDomainVerifier::class, CpanelCustomDomainVerifier::class);
        $this->app->singleton(CentralSettings::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        DB::prohibitDestructiveCommands(app()->isProduction());
        Password::defaults(function (): ?Password {
            if (! app()->isProduction()) return null;
            return Password::min(app(CentralSettings::class)->passwordMinimumLength())->mixedCase()->letters()->numbers()->symbols()->uncompromised();
        });
    }
}
