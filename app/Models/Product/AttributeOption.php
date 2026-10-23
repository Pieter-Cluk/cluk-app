<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One choice of an attribute: a value (2.5, G51, black) with an optional translated label. */
#[Fillable(['attribute_id', 'value', 'label', 'sort', 'active'])]
class AttributeOption extends Model
{
    protected $table = 'product_attribute_options';

    protected function casts(): array
    {
        return ['label' => 'array', 'active' => 'boolean'];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** "2,5 m", "470–638 MHz", "Black" in the reader's language. */
    public function display(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $l = $this->label ?? [];
        if (($l[$locale] ?? '') !== '' || ($l['en'] ?? '') !== '') {
            return (string) (($l[$locale] ?? '') !== '' ? $l[$locale] : $l['en']);
        }
        $v = is_numeric($this->value) && $locale !== 'en' ? str_replace('.', ',', $this->value) : $this->value;
        $unit = $this->relationLoaded('attribute') ? $this->attribute?->unit : $this->attribute()->value('unit');

        return trim($v.' '.$unit);
    }
}
