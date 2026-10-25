<?php

namespace App\Models\Website;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Web\CustomerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One download: who (login, contact, company or what they filled in) and when. */
#[Fillable(['download_id', 'customer_account_id', 'contact_id', 'company_id', 'visitor_id', 'name', 'email', 'company_name', 'locale', 'created_at'])]
class DownloadLog extends Model
{
    protected $table = 'web_download_logs';

    public const UPDATED_AT = null;

    public function download(): BelongsTo
    {
        return $this->belongsTo(Download::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    /** Known to us: a login, a contact or a company. */
    public function known(): bool
    {
        return (bool) ($this->customer_account_id || $this->contact_id || $this->company_id);
    }
}
