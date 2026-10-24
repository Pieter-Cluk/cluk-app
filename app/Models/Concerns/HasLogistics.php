<?php

namespace App\Models\Concerns;

/**
 * Logistics of a rental quote or order: open (not known yet), pickup (the customer
 * collects at our warehouse), customer (the customer arranges transport) or
 * delivery (CLUK delivers and collects, for transport_cents excl. VAT).
 */
trait HasLogistics
{
    public static function logisticsOptions(): array
    {
        return ['open', 'pickup', 'customer', 'delivery'];
    }

    /** The logistics choice; a model not yet read back from the database has the default. */
    public function getLogisticsAttribute(?string $value): string
    {
        return $value ?: 'open';
    }

    /** The transport price that counts in the totals: only when we deliver. */
    public function transportCharged(): int
    {
        return $this->logistics === 'delivery' ? max(0, (int) $this->transport_cents) : 0;
    }

    /** We deliver, but the price is not known yet. */
    public function transportToPrice(): bool
    {
        return $this->logistics === 'delivery' && (int) $this->transport_cents === 0;
    }

    /** One line for the customer documents, in the current language. */
    public function logisticsText(): string
    {
        return __('quotes.transport.doc.'.($this->logistics ?: 'open')).($this->transport_note ? ' · '.$this->transport_note : '');
    }
}
