<?php

namespace App\Models\Media;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message: the birthday card, a newsletter (with its PDF), a promotion or the
 * banner on quotes, or an email banner under the app's mails. Written in English; the text is translated into each person's
 * language. The picture is the same for everyone.
 */
#[Fillable(['kind', 'title', 'subject', 'body', 'source_hash', 'image_path', 'pdf_path', 'pdf_name', 'link_url', 'position', 'status', 'send_at', 'sent_at', 'sent_count',
    'active', 'active_from', 'active_until', 'created_by_id', 'sent_by_id', 'plan'])]
class Message extends Model
{
    public const KINDS = ['birthday', 'newsletter', 'promotion', 'invitation', 'banner', 'mail_banner'];

    /** Kinds with a period (from–until) instead of a send moment. */
    public const PERIOD = ['banner', 'mail_banner'];

    /** Kinds that are mailed once to a list (the others are "in use" or not). */
    public const MAILED = ['newsletter', 'promotion', 'invitation'];

    public const LOCALES = ['en', 'nl', 'fr', 'es'];

    protected function casts(): array
    {
        return ['subject' => 'array', 'body' => 'array', 'send_at' => 'datetime', 'sent_at' => 'datetime', 'active' => 'boolean',
            'active_from' => 'date', 'active_until' => 'date', 'sent_count' => 'integer', 'position' => 'integer', 'plan' => 'array'];
    }

    public function sends(): HasMany
    {
        return $this->hasMany(MessageSend::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    public function scopeKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }

    /** The text in a language, else the English one. */
    public function text(string $field, ?string $locale): string
    {
        $v = $this->{$field} ?? [];

        return (string) (($locale && ! empty($v[$locale])) ? $v[$locale] : ($v['en'] ?? ''));
    }

    public function sourceHash(): string
    {
        return hash('sha256', ($this->subject['en'] ?? '')."\n".($this->body['en'] ?? ''));
    }

    /** Translations missing or made from an older English text. */
    public function translationsOutdated(): bool
    {
        if (blank($this->subject['en'] ?? null) && blank($this->body['en'] ?? null)) {
            return false;
        }

        return $this->source_hash !== $this->sourceHash();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true) || ! in_array($this->kind, self::MAILED, true);
    }

    /** The birthday card or banner that is in use today. */
    public static function inUse(string $kind, $on = null): ?self
    {
        $on ??= today();

        return static::kind($kind)->where('active', true)
            ->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', $on))
            ->where(fn ($q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', $on))
            ->latest('id')->first();
    }
}
