<?php

namespace App\Http\Controllers;

use App\Support\PublicStorage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function show(string $path): Response|StreamedResponse
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! PublicStorage::isPathAllowed($path)) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
