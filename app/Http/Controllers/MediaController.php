<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\ItemPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves stored pictures to signed-in staff. */
class MediaController extends Controller
{
    private function file(?string $path): BinaryFileResponse
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=604800']);
    }

    public function user(User $user): BinaryFileResponse
    {
        return $this->file($user->photo_path);
    }

    public function company(Company $company): BinaryFileResponse
    {
        return $this->file($company->logo_path);
    }

    public function contact(Contact $contact): BinaryFileResponse
    {
        return $this->file($contact->photo_path);
    }

    public function itemPhoto(ItemPhoto $photo): BinaryFileResponse
    {
        return $this->file($photo->path);
    }
}
