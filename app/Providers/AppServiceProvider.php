<?php

namespace App\Providers;

use App\Contracts\CustomDomainDeprovisioner;
use App\Contracts\CustomDomainProvisioner;
use App\Contracts\CustomDomainVerifier;
use App\Contracts\PlatformDomainDeprovisioner;
use App\Contracts\TenantDatabaseProvisioner;
use App\Contracts\TenantDomainProvisioner;
use App\Services\CentralSettings;
use App\Services\CpanelApi2CustomDomainDeprovisioner;
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
        $this->app->bind(CustomDomainDeprovisioner::class, CpanelApi2CustomDomainDeprovisioner::class);
        $this->app->bind(CustomDomainVerifier::class, CpanelCustomDomainVerifier::class);
        $this->app->singleton(CentralSettings::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        DB::prohibitDestructiveCommands(app()->isProduction());
        Password::defaults(fn (): Password => app(CentralSettings::class)->passwordRule());
    }
}
