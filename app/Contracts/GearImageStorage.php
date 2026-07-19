<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface GearImageStorage
{
    public function store(UploadedFile $image, string $path): string;

    public function delete(?string $path): void;
}
