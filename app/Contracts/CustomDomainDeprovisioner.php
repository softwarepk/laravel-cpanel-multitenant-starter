<?php

namespace App\Contracts;

interface CustomDomainDeprovisioner
{
    public function deleteCustomDomain(string $domain): void;
}
