<?php

namespace App\Contracts;

interface CustomDomainVerifier
{
    /** @return array{dns: bool, cpanel: bool, ssl: bool, errors: list<string>} */
    public function verify(string $domain): array;
}
