<?php

namespace App\Contracts;

interface PlatformDomainDeprovisioner
{
    public function deletePlatformDomain(string $domain): void;
}
