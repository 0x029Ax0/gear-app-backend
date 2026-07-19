<?php

namespace App\Services\ProductImports;

class SafeUrlValidator
{
    public function validate(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || $host === '' || isset($parts['user']) || isset($parts['pass'])
            || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ProductImportException('INVALID_URL', 'The URL must be a valid public HTTP or HTTPS URL.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw new ProductImportException('DNS_FAILED', 'The URL host could not be resolved.');
        }
        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                throw new ProductImportException('SSRF_BLOCKED', 'The URL points to a private or reserved network.');
            }
        }

        return $url;
    }

    public function validateRedirect(string $url): string
    {
        return $this->validate($url);
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
