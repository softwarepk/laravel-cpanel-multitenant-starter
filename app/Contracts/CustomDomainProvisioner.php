<?php

namespace App\Contracts;

interface CustomDomainProvisioner
{
    public function ensureCustomDomainReady(string $domain): void;
}
