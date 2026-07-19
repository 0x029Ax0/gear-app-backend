<?php

namespace App\Services;

use App\Contracts\GearImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FilesystemGearImageStorage implements GearImageStorage
{
    public function store(UploadedFile $image, string $path): string
    {
        Storage::disk($this->disk())->putFileAs(dirname($path), $image, basename($path));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    private function disk(): string
    {
        return (string) config('gear_images.disk', 'public');
    }
}
