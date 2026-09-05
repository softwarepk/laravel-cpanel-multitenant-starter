<?php

namespace App\Services;

class PlatformHttpsVerifier
{
    public function isReady(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }

        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $domain,
            'SNI_enabled' => true,
            'SNI_server_name' => $domain,
            'allow_self_signed' => false,
        ]]);

        $socket = @stream_socket_client('ssl://'.$domain.':443', $errno, $error, 5, STREAM_CLIENT_CONNECT, $context);
        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
