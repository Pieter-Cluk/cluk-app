<?php

namespace App\Services\Finance\OkiOki;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\CompanyRegistry;
use App\Models\Finance\PreInvoice;
use App\Support\VatNumber;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * The invoice as an e-invoice in the Peppol BIS Billing 3.0 format (UBL 2.1),
 * the format Belgian B2B invoices travel in since 2026. OkiOki takes this file
 * and sends it to the customer over Peppol.
 */
class UblInvoice
{
    private const NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /** Peppol address types for a VAT number, per country (others: by email). */
    private const VAT_SCHEMES = ['NL' => '9944', 'DE' => '9930', 'FR' => '9957', 'LU' => '9938', 'ES' => '9920', 'AT' => '9915', 'IT' => '0211', 'IE' => '9935', 'PT' => '9946', 'PL' => '9945', 'GR' => '9933', 'SE' => '9955', 'DK' => '0198', 'FI' => '0213'];

    private DOMDocument $doc;

    /** Can the customer be reached over Peppol (a Belgian company number or an EU VAT number we can address)? */
    public static function onPeppol(PreInvoice $pf): bool
    {
        $pf->loadMissing(['company', 'member']);

        return $pf->company !== null && (new self)->buyer($pf)['endpoint'][0] !== 'EM';
    }

    public static function make(PreInvoice $pf): string
    {
        return (new self)->build($pf);
    }

    /** UBL VAT category for our VAT code: S standard, AE reverse charge (EU business), E outside the EU (not subject to Belgian VAT). */
    public static function category(PreInvoice $pf): array
    {
        return match (true) {
            $pf->vat_rate_bp > 0 => ['S', null],
            $pf->vat_code === (string) CompanyDetail::current()->vat_default_eu_business || $pf->vat_code === '46' => ['AE', ['VATEX-EU-AE', 'Reverse charge - Autoliquidation - Verlegging van heffing (art. 196 Directive 2006/112/EC)']],
            default => ['E', [null, 'Not subject to Belgian VAT - Niet onderworpen aan Belgische btw (art. 21 §2 WBTW)']],
        };
    }

    private function build(PreInvoice $pf): string
    {
        if (! $pf->invoice_number || ! $pf->invoice_date) {
            throw new RuntimeException('The invoice has no number or date yet.');
        }
        $pf->loadMissing(['company', 'member.company']);
        $me = CompanyDetail::current();
        $bank = BankAccount::where('on_documents', true)->orderByDesc('is_main')->first();
        [$cat, $exempt] = self::category($pf);
        $pct = $pf->vat_rate_bp / 100;

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $inv = $this->doc->createElementNS(self::NS, 'Invoice');
        $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $this->doc->appendChild($inv);

        $this->cbc($inv, 'CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0');
        $this->cbc($inv, 'ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $this->cbc($inv, 'ID', $pf->invoice_number);
        $this->cbc($inv, 'IssueDate', $pf->invoice_date->toDateString());
        if ($pf->due_date) {
            $this->cbc($inv, 'DueDate', $pf->due_date->toDateString());
        }
        $this->cbc($inv, 'InvoiceTypeCode', '380');
        $this->cbc($inv, 'Note', mb_substr($pf->description, 0, 200));
        if ($pf->service_date) {
            $this->cbc($inv, 'TaxPointDate', $pf->service_date->toDateString());
        }
        $this->cbc($inv, 'DocumentCurrencyCode', 'EUR');
        $this->cbc($inv, 'BuyerReference', mb_substr($pf->po_number ?: ($pf->source_code ?: $pf->number), 0, 100));
        if ($pf->po_number) {
            $this->cac($inv, 'OrderReference', fn ($o) => $this->cbc($o, 'ID', mb_substr($pf->po_number, 0, 100)));
        }

        // Seller: CLUK.
        $myNumber = CompanyRegistry::numberFromVat($me->vat_number);
        $this->cac($inv, 'AccountingSupplierParty', fn ($s) => $this->cac($s, 'Party', function ($p) use ($me, $myNumber) {
            $this->cbc($p, 'EndpointID', preg_replace('/\D/', '', (string) $myNumber), ['schemeID' => '0208']);
            $this->cac($p, 'PartyName', fn ($n) => $this->cbc($n, 'Name', trim($me->legal_name.' '.$me->legal_form)));
            $this->address($p, $me->street, $me->city, $me->postcode, $me->country);
            $this->cac($p, 'PartyTaxScheme', function ($t) use ($me) {
                $this->cbc($t, 'CompanyID', (string) $me->vat_number);
                $this->cac($t, 'TaxScheme', fn ($s) => $this->cbc($s, 'ID', 'VAT'));
            });
            $this->cac($p, 'PartyLegalEntity', function ($l) use ($me, $myNumber) {
                $this->cbc($l, 'RegistrationName', trim($me->legal_name.' '.$me->legal_form));
                $this->cbc($l, 'CompanyID', preg_replace('/\D/', '', (string) $myNumber), ['schemeID' => '0208']);
            });
            if ($me->invoice_email || $me->email) {
                $this->cac($p, 'Contact', fn ($c) => $this->cbc($c, 'ElectronicMail', $me->invoice_email ?: $me->email));
            }
        }));

        // Buyer: the customer (a company, or an Afrit 6 member).
        $buyer = $this->buyer($pf);
        $this->cac($inv, 'AccountingCustomerParty', fn ($s) => $this->cac($s, 'Party', function ($p) use ($buyer) {
            $this->cbc($p, 'EndpointID', $buyer['endpoint'][1], ['schemeID' => $buyer['endpoint'][0]]);
            $this->cac($p, 'PartyName', fn ($n) => $this->cbc($n, 'Name', $buyer['name']));
            $this->address($p, $buyer['street'], $buyer['city'], $buyer['postcode'], $buyer['country']);
            if ($buyer['vat']) {
                $this->cac($p, 'PartyTaxScheme', function ($t) use ($buyer) {
                    $this->cbc($t, 'CompanyID', $buyer['vat']);
                    $this->cac($t, 'TaxScheme', fn ($s) => $this->cbc($s, 'ID', 'VAT'));
                });
            }
            $this->cac($p, 'PartyLegalEntity', function ($l) use ($buyer) {
                $this->cbc($l, 'RegistrationName', $buyer['name']);
                if ($buyer['kbo']) {
                    $this->cbc($l, 'CompanyID', $buyer['kbo'], ['schemeID' => '0208']);
                }
            });
            if ($buyer['email']) {
                $this->cac($p, 'Contact', fn ($c) => $this->cbc($c, 'ElectronicMail', $buyer['email']));
            }
        }));

        if ($pf->service_date) {
            $this->cac($inv, 'Delivery', fn ($d) => $this->cbc($d, 'ActualDeliveryDate', $pf->service_date->toDateString()));
        }
        $this->cac($inv, 'PaymentMeans', function ($m) use ($bank, $pf) {
            $this->cbc($m, 'PaymentMeansCode', $bank ? '30' : '1', $bank ? ['name' => 'Credit transfer'] : []);
            $this->cbc($m, 'PaymentID', $pf->invoice_number);
            if ($bank) {
                $this->cac($m, 'PayeeFinancialAccount', function ($a) use ($bank) {
                    $this->cbc($a, 'ID', strtoupper(preg_replace('/\s+/', '', $bank->iban)));
                    if ($bank->bic) {
                        $this->cac($a, 'FinancialInstitutionBranch', fn ($b) => $this->cbc($b, 'ID', $bank->bic));
                    }
                });
            }
        });
        $this->cac($inv, 'PaymentTerms', fn ($t) => $this->cbc($t, 'Note', $pf->payment_days > 0 ? 'Payment within '.$pf->payment_days.' days' : 'Payment on receipt'));

        // Lines; a negative line (a discount) becomes a document allowance.
        $lines = collect($pf->lines)->filter(fn ($l) => (int) $l['amount_cents'] !== 0)->values();
        $allow = $lines->filter(fn ($l) => (int) $l['amount_cents'] < 0);
        $positive = $lines->filter(fn ($l) => (int) $l['amount_cents'] > 0)->values();
        foreach ($allow as $a) {
            $this->cac($inv, 'AllowanceCharge', function ($c) use ($a, $cat, $pct) {
                $this->cbc($c, 'ChargeIndicator', 'false');
                $this->cbc($c, 'AllowanceChargeReason', mb_substr($a['d'], 0, 100));
                $this->cbc($c, 'Amount', self::money(-(int) $a['amount_cents']), ['currencyID' => 'EUR']);
                $this->taxCategory($c, $cat, $pct, null);
            });
        }
        $lineSum = (int) $positive->sum('amount_cents');
        $allowSum = -(int) $allow->sum('amount_cents');
        $net = $lineSum - $allowSum;
        $vat = (int) $pf->vat_cents;

        $this->cac($inv, 'TaxTotal', function ($t) use ($net, $vat, $cat, $pct, $exempt) {
            $this->cbc($t, 'TaxAmount', self::money($vat), ['currencyID' => 'EUR']);
            $this->cac($t, 'TaxSubtotal', function ($s) use ($net, $vat, $cat, $pct, $exempt) {
                $this->cbc($s, 'TaxableAmount', self::money($net), ['currencyID' => 'EUR']);
                $this->cbc($s, 'TaxAmount', self::money($vat), ['currencyID' => 'EUR']);
                $this->taxCategory($s, $cat, $pct, $exempt);
            });
        });
        $this->cac($inv, 'LegalMonetaryTotal', function ($m) use ($lineSum, $allowSum, $net, $vat) {
            $this->cbc($m, 'LineExtensionAmount', self::money($lineSum), ['currencyID' => 'EUR']);
            $this->cbc($m, 'TaxExclusiveAmount', self::money($net), ['currencyID' => 'EUR']);
            $this->cbc($m, 'TaxInclusiveAmount', self::money($net + $vat), ['currencyID' => 'EUR']);
            if ($allowSum) {
                $this->cbc($m, 'AllowanceTotalAmount', self::money($allowSum), ['currencyID' => 'EUR']);
            }
            $this->cbc($m, 'PayableAmount', self::money($net + $vat), ['currencyID' => 'EUR']);
        });

        foreach ($positive as $i => $l) {
            $qty = (float) str_replace(',', '.', (string) $l['q']) ?: 1.0;
            $amount = (int) $l['amount_cents'];
            $this->cac($inv, 'InvoiceLine', function ($il) use ($i, $l, $qty, $amount, $cat, $pct) {
                $this->cbc($il, 'ID', (string) ($i + 1));
                $this->cbc($il, 'InvoicedQuantity', self::num($qty), ['unitCode' => 'C62']);
                $this->cbc($il, 'LineExtensionAmount', self::money($amount), ['currencyID' => 'EUR']);
                $this->cac($il, 'Item', function ($it) use ($l, $cat, $pct) {
                    $this->cbc($it, 'Name', mb_substr($l['d'], 0, 200));
                    $this->taxCategory($it, $cat, $pct, null, 'ClassifiedTaxCategory');
                });
                // Price per piece after factor and discount, so quantity × price = line amount.
                $this->cac($il, 'Price', fn ($p) => $this->cbc($p, 'PriceAmount', self::num(round($amount / 100 / $qty, 4)), ['currencyID' => 'EUR']));
            });
        }

        return $this->doc->saveXML();
    }

    /** @return array{name: string, street: ?string, city: ?string, postcode: ?string, country: string, vat: ?string, kbo: ?string, email: ?string, endpoint: array{0: string, 1: string}} */
    private function buyer(PreInvoice $pf): array
    {
        $c = $pf->company;
        if ($c instanceof Company) {
            $vat = $c->vat_number ? strtoupper(preg_replace('/[\s.\-]/', '', $c->vat_number)) : null;
            $kbo = CompanyRegistry::numberFromVat($vat) ? preg_replace('/\D/', '', CompanyRegistry::numberFromVat($vat)) : null;
            $country = strtoupper($c->country ?: 'BE');
            $endpoint = match (true) {
                $kbo !== null => ['0208', $kbo],
                $vat !== null && isset(self::VAT_SCHEMES[$country]) && VatNumber::isEu($country) => [self::VAT_SCHEMES[$country], $vat],
                default => ['EM', (string) ($c->email ?: $pf->member?->email)],
            };

            return ['name' => $c->name, 'street' => $c->street, 'city' => $c->city, 'postcode' => $c->postcode, 'country' => $country, 'vat' => $vat, 'kbo' => $kbo, 'email' => $c->email, 'endpoint' => $endpoint];
        }
        $m = $pf->member;

        return ['name' => $m?->reg_company_name ?: ($m?->name ?? '—'), 'street' => $m?->reg_address, 'city' => null, 'postcode' => null, 'country' => strtoupper($m?->reg_country ?: 'BE'),
            'vat' => $m?->reg_vat_number ?: null, 'kbo' => null, 'email' => $m?->email, 'endpoint' => ['EM', (string) $m?->email]];
    }

    private function address(DOMElement $p, ?string $street, ?string $city, ?string $postcode, ?string $country): void
    {
        $this->cac($p, 'PostalAddress', function ($a) use ($street, $city, $postcode, $country) {
            if ($street) {
                $this->cbc($a, 'StreetName', $street);
            }
            if ($city) {
                $this->cbc($a, 'CityName', $city);
            }
            if ($postcode) {
                $this->cbc($a, 'PostalZone', $postcode);
            }
            $this->cac($a, 'Country', fn ($c) => $this->cbc($c, 'IdentificationCode', strtoupper($country ?: 'BE')));
        });
    }

    private function taxCategory(DOMElement $parent, string $cat, float $pct, ?array $exempt, string $name = 'TaxCategory'): void
    {
        $this->cac($parent, $name, function ($t) use ($cat, $pct, $exempt) {
            $this->cbc($t, 'ID', $cat);
            $this->cbc($t, 'Percent', self::num($cat === 'S' ? $pct : 0));
            if ($exempt) {
                if ($exempt[0]) {
                    $this->cbc($t, 'TaxExemptionReasonCode', $exempt[0]);
                }
                $this->cbc($t, 'TaxExemptionReason', $exempt[1]);
            }
            $this->cac($t, 'TaxScheme', fn ($s) => $this->cbc($s, 'ID', 'VAT'));
        });
    }

    private function cac(DOMElement $parent, string $name, callable $fill): DOMElement
    {
        $el = $this->doc->createElementNS(self::CAC, 'cac:'.$name);
        $parent->appendChild($el);
        $fill($el);

        return $el;
    }

    private function cbc(DOMElement $parent, string $name, string $value, array $attrs = []): DOMElement
    {
        $el = $this->doc->createElementNS(self::CBC, 'cbc:'.$name);
        $el->appendChild($this->doc->createTextNode($value));
        foreach ($attrs as $k => $v) {
            $el->setAttribute($k, $v);
        }
        $parent->appendChild($el);

        return $el;
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.') ?: '0';
    }
}
