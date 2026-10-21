<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Equipment\ItemPhoto;
use App\Models\User;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Uploading and removing pictures: item photos, company logos, contact and staff photos. */
class PictureController extends Controller
{
    private function stored(Request $request, string $field, string $dir, int $px, bool $alpha = false): string
    {
        try {
            return Images::store($request->file($field), $dir, $px, $alpha);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => __('pictures.not_image')]);
        }
    }

    // ---------------------------------------------------------------- items

    public function storeItemPhotos(Request $request, Item $item): RedirectResponse
    {
        $request->validate(['photos' => ['required', 'array', 'max:12'], 'photos.*' => Images::rule()]);
        $pos = (int) $item->photos()->max('position');
        foreach ($request->file('photos') as $i => $file) {
            try {
                $path = Images::store($file, 'equipment', 1600);
            } catch (Throwable) {
                throw ValidationException::withMessages(['photos' => __('pictures.not_image')]);
            }
            $item->photos()->create(['path' => $path, 'position' => $pos + $i + 1]);
        }
        $item->syncMainPhoto();
        AuditLog::record('equipment.item_updated', $item, ['changes' => ['photo' => true]]);

        return back()->with('status', trans_choice('pictures.added', count($request->file('photos')), ['n' => count($request->file('photos'))]));
    }

    public function mainItemPhoto(Item $item, ItemPhoto $photo): RedirectResponse
    {
        abort_unless($photo->item_id === $item->id, 404);
        DB::transaction(function () use ($item, $photo) {
            $item->photos()->whereKeyNot($photo->id)->increment('position');
            $photo->update(['position' => 0]);
        });
        $item->syncMainPhoto();

        return back()->with('status', __('pictures.main_set'));
    }

    public function destroyItemPhoto(Item $item, ItemPhoto $photo): RedirectResponse
    {
        abort_unless($photo->item_id === $item->id, 404);
        Images::delete($photo->path);
        $photo->delete();
        $item->syncMainPhoto();

        return back()->with('status', __('pictures.removed'));
    }

    // ------------------------------------------------- companies & contacts

    public function storeLogo(Request $request, Company $company): RedirectResponse
    {
        $request->validate(['logo' => ['required', ...Images::rule()]]);
        $old = $company->logo_path;
        $company->forceFill(['logo_path' => $this->stored($request, 'logo', 'logos', 400, true)])->save();
        Images::delete($old);

        return back()->with('status', __('pictures.logo_saved'));
    }

    public function destroyLogo(Company $company): RedirectResponse
    {
        Images::delete($company->logo_path);
        $company->forceFill(['logo_path' => null])->save();

        return back()->with('status', __('pictures.removed'));
    }

    public function storeContactPhoto(Request $request, Company $company, Contact $contact): RedirectResponse
    {
        abort_unless($company->contacts()->whereKey($contact->id)->exists(), 404);
        $request->validate(['photo' => ['required', ...Images::rule()]]);
        $old = $contact->photo_path;
        $contact->forceFill(['photo_path' => $this->stored($request, 'photo', 'contacts', 400)])->save();
        Images::delete($old);

        return back()->with('status', __('pictures.photo_saved'));
    }

    public function destroyContactPhoto(Company $company, Contact $contact): RedirectResponse
    {
        abort_unless($company->contacts()->whereKey($contact->id)->exists(), 404);
        Images::delete($contact->photo_path);
        $contact->forceFill(['photo_path' => null])->save();

        return back()->with('status', __('pictures.removed'));
    }

    // ---------------------------------------------------------------- staff

    /** Normally the photo comes from Microsoft 365 at login; this is for people without one there. */
    public function storeUserPhoto(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->is($user) || $request->user()->hasRight('users.manage'), 403);
        $request->validate(['photo' => ['required', ...Images::rule()]]);
        $old = $user->photo_path;
        $user->forceFill(['photo_path' => $this->stored($request, 'photo', 'users', 400)])->save();
        Images::delete($old);

        return back()->with('status', __('pictures.photo_saved'));
    }
}
