<?php

namespace App\Services\ProductImports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SafeImageDownloader
{
    public function __construct(private readonly SafeUrlValidator $validator) {}

    public function download(string $url, int $userId, int $importId): ?string
    {
        $this->validator->validate($url);
        try {
            $response = Http::connectTimeout(5)->timeout((int) config('product_imports.fetch_timeout'))
                ->withOptions(['allow_redirects' => false])->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $response->successful() || strlen($response->body()) > (int) config('product_imports.max_image_bytes')) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'import-image-');
        file_put_contents($tmp, $response->body());
        $info = @getimagesize($tmp);
        @unlink($tmp);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($info === false || ! isset($extensions[$info['mime']])) {
            return null;
        }
        $path = 'gear-images/'.$userId.'/imports/'.$importId.'/'.Str::uuid().'.'.$extensions[$info['mime']];
        Storage::disk(config('product_imports.image_disk'))->put($path, $response->body());

        return $path;
    }
}
