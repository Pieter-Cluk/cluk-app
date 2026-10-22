<?php

namespace App\Services\Equipment;

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Equipment\PhotoSuggestion;
use App\Models\User;
use App\Support\Images;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Product photos found on the web (database/data/equipment_photos.json): the
 * server fetches them, a person picks the right one per product, and that one
 * becomes the main photo of every item of that product.
 */
class PhotoSuggestions
{
    public const DATA = 'data/equipment_photos.json';

    /** Read the list; new products are added, decided ones are left alone. Returns how many were added or updated. */
    public static function load(): int
    {
        $path = database_path(self::DATA);
        if (! is_file($path)) {
            return 0;
        }
        $n = 0;
        foreach (json_decode((string) file_get_contents($path), true) ?: [] as $row) {
            $cands = array_values(array_filter($row['candidates'] ?? [], fn ($u) => is_string($u) && preg_match('#^https?://#', $u)));
            if (! $cands || empty($row['items'])) {
                continue;
            }
            $s = PhotoSuggestion::firstOrNew(['group_key' => mb_substr($row['key'], 0, 255)]);
            if (in_array($s->status, ['approved', 'rejected'], true)) {
                continue;
            }
            $changed = ! $s->exists || $s->candidates !== $cands;
            $s->fill(['title' => mb_substr($row['title'] ?? $row['key'], 0, 255), 'item_numbers' => array_values($row['items']), 'candidates' => $cands,
                'page' => isset($row['page']) ? mb_substr((string) $row['page'], 0, 500) : null, 'source' => $row['source'] ?? null,
                'note' => isset($row['note']) ? mb_substr((string) $row['note'], 0, 500) : null]);
            if ($changed) {
                $s->files = null;
                $s->status = 'new';
                $n++;
            }
            $s->save();
        }

        return $n;
    }

    /** Fetch the photos of up to $limit products. Returns how many products are still to fetch. */
    public static function fetch(int $limit = 40): int
    {
        foreach (PhotoSuggestion::where('status', 'new')->orderBy('id')->limit($limit)->get() as $s) {
            $files = [];
            foreach ($s->candidates as $i => $url) {
                $files[$i] = self::download($url, $s->id.'-'.$i);
            }
            $ok = array_filter($files);
            $s->update(['files' => $files, 'status' => $ok ? 'pending' : 'failed']);
        }

        return PhotoSuggestion::where('status', 'new')->count();
    }

    private static function download(string $url, string $name): ?string
    {
        try {
            $res = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0 (CLUK equipment photos)', 'Accept' => 'image/*'])->get($url);
            $body = $res->body();
            if (! $res->successful() || strlen($body) < 2000 || strlen($body) > 15 * 1024 * 1024) {
                return null;
            }

            return Images::store($body, 'photo-suggestions', 1200);
        } catch (Throwable) {
            return null;
        }
    }

    /** Use photo $index for every item of this product: it becomes their main photo. */
    public static function approve(PhotoSuggestion $s, int $index, User $by): int
    {
        $file = $s->usable()[$index] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file), 404);
        $data = Storage::disk('local')->get($file);
        $n = 0;
        DB::transaction(function () use ($s, $data, $by, $index, &$n) {
            foreach ($s->items() as $item) {
                /** @var Item $item */
                $path = Images::store($data, 'equipment', 1600);
                $item->photos()->increment('position');
                $item->photos()->create(['path' => $path, 'position' => 0]);
                $item->syncMainPhoto();
                $n++;
            }
            $s->update(['status' => 'approved', 'chosen' => $index, 'decided_by_id' => $by->id, 'decided_at' => now()]);
            AuditLog::record('equipment.photo_approved', null, ['product' => $s->title, 'items' => $n], $by);
        });
        self::cleanup($s);

        return $n;
    }

    public static function reject(PhotoSuggestion $s, User $by): void
    {
        $s->update(['status' => 'rejected', 'decided_by_id' => $by->id, 'decided_at' => now()]);
        self::cleanup($s);
    }

    /** The downloaded copies are not needed once decided. */
    private static function cleanup(PhotoSuggestion $s): void
    {
        foreach ($s->usable() as $f) {
            Images::delete($f);
        }
        $s->update(['files' => null]);
    }
}
