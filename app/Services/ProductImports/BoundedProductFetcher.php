<?php

namespace App\Services\ProductImports;

use Illuminate\Support\Facades\Http;

class BoundedProductFetcher
{
    public function __construct(private readonly SafeUrlValidator $validator) {}

    /** @return array{url:string, body:string, content_type:string} */
    public function fetch(string $url): array
    {
        $current = $this->validator->validate($url);
        for ($redirect = 0; $redirect <= (int) config('product_imports.max_redirects'); $redirect++) {
            try {
                $response = Http::connectTimeout(5)->timeout((int) config('product_imports.fetch_timeout'))
                    ->withHeaders(['Accept' => 'text/html,application/xhtml+xml,application/ld+json;q=0.9'])
                    ->withOptions(['allow_redirects' => false])->get($current);
            } catch (\Throwable) {
                throw new ProductImportException('FETCH_FAILED', 'The product page could not be fetched.');
            }
            if ($response->redirect()) {
                if ($redirect === (int) config('product_imports.max_redirects')) {
                    throw new ProductImportException('TOO_MANY_REDIRECTS', 'The product page redirected too many times.');
                }
                $location = $response->header('Location');
                if (! is_string($location) || $location === '') {
                    throw new ProductImportException('INVALID_REDIRECT', 'The product page returned an invalid redirect.');
                }
                $current = $this->resolveUrl($current, $location);
                $this->validator->validateRedirect($current);

                continue;
            }
            if (! $response->successful()) {
                throw new ProductImportException('FETCH_FAILED', 'The product page could not be fetched.');
            }
            $body = $response->body();
            if (strlen($body) > (int) config('product_imports.max_bytes')) {
                throw new ProductImportException('RESPONSE_TOO_LARGE', 'The product page is too large.');
            }
            $contentType = strtolower((string) $response->header('Content-Type'));
            if (! str_contains($contentType, 'html') && ! str_contains($contentType, 'json')) {
                throw new ProductImportException('UNSUPPORTED_CONTENT', 'The product page is not HTML or JSON.');
            }

            return ['url' => $current, 'body' => $body, 'content_type' => $contentType];
        }
        throw new ProductImportException('FETCH_FAILED', 'The product page could not be fetched.');
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }
        $baseParts = parse_url($base);
        if (str_starts_with($location, '//')) {
            return ($baseParts['scheme'] ?? 'https').':'.$location;
        }
        $path = str_starts_with($location, '/') ? $location : rtrim(dirname($baseParts['path'] ?? '/'), '/').'/'.$location;

        return ($baseParts['scheme'] ?? 'https').'://'.$baseParts['host'].$path;
    }
}
