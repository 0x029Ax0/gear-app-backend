<?php

namespace App\Services\ProductImports;

use DOMDocument;
use DOMXPath;

class ProductParser
{
    /** @return array{name:string, weight_grams:?int, price_minor:?int, currency_code:?string, image_url:?string, product_url:string} */
    public function parse(string $body, string $url): array
    {
        $candidates = [];
        $json = json_decode($body, true);
        if (is_array($json)) {
            $candidates[] = $json;
        }
        $dom = new DOMDocument;
        @$dom->loadHTML($body);
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $decoded = json_decode(trim($script->textContent), true);
            if (is_array($decoded)) {
                $items = isset($decoded['@graph']) && is_array($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $candidates[] = $item;
                    }
                }
            }
        }
        $product = collect($candidates)->first(fn (array $item): bool => ($item['@type'] ?? null) === 'Product' || isset($item['name'])) ?? [];
        $name = trim((string) ($product['name'] ?? $this->meta($xpath, 'og:title') ?? $this->title($xpath)));
        if ($name === '') {
            throw new ProductImportException('MISSING_NAME', 'The product page did not contain a product name.');
        }
        $offers = $product['offers'] ?? $product;
        if (isset($offers[0]) && is_array($offers[0])) {
            $offers = $offers[0];
        }
        $price = $offers['price'] ?? $product['price'] ?? null;
        $currency = $offers['priceCurrency'] ?? $product['priceCurrency'] ?? null;
        $image = $product['image'] ?? $this->meta($xpath, 'og:image');
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }
        $weight = $product['weight'] ?? null;
        if (is_array($weight)) {
            $weight = $weight['value'] ?? null;
        }

        return [
            'name' => mb_substr($name, 0, 255),
            'weight_grams' => $this->weightGrams($weight),
            'price_minor' => $this->moneyMinor($price),
            'currency_code' => $currency ? strtoupper((string) $currency) : null,
            'image_url' => is_string($image) && filter_var($image, FILTER_VALIDATE_URL) ? $image : null,
            'product_url' => $url,
        ];
    }

    private function meta(DOMXPath $xpath, string $property): ?string
    {
        $nodes = $xpath->query(sprintf('//meta[@property="%s" or @name="%s"]/@content', $property, $property));

        return $nodes && $nodes->length > 0 ? trim($nodes->item(0)->nodeValue) : null;
    }

    private function title(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//title');

        return $nodes && $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function weightGrams(mixed $value): ?int
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        if (! preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(kg|g)?/i', (string) $value, $matches)) {
            return null;
        }
        $number = (float) str_replace(',', '.', $matches[1]);

        return (int) round($number * (strtolower($matches[2] ?? 'g') === 'kg' ? 1000 : 1));
    }

    private function moneyMinor(mixed $value): ?int
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $normalized = preg_replace('/[^0-9.,-]/', '', (string) $value);
        if ($normalized === null || $normalized === '') {
            return null;
        }
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace('.', '', str_replace(',', '.', $normalized))
                : str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (int) round(((float) $normalized) * 100);
    }
}
