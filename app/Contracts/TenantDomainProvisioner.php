<?php

namespace App\Contracts;

interface TenantDomainProvisioner
{
    public function ensurePlatformDomainReady(string $domain, string $rootDomain, string $documentRoot): void;
}
