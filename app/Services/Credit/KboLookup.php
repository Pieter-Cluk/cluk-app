<?php

namespace App\Services\Credit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live lookup in the KBO/BCE Public Search web service (SOAP, WS-Security
 * UsernameToken with PasswordDigest). Free, 3000 lookups a day.
 * Returns the register data in the same shape as the monthly file import.
 */
class KboLookup
{
    public static function available(): bool
    {
        return filled(config('services.kbo_ws.user')) && filled(config('services.kbo_ws.password'));
    }

    /**
     * @param  string  $number  0123.456.789 or 0123456789
     * @return array{found: bool, snapshot: ?string, entry: ?array}|null null when the service could not be reached
     */
    public static function read(string $number): ?array
    {
        if (! self::available()) {
            return null;
        }
        $digits = str_pad(preg_replace('/\D/', '', $number), 10, '0', STR_PAD_LEFT);
        try {
            $res = Http::timeout(8)->withHeaders(['SOAPAction' => '""'])->withBody(self::envelope($digits), 'text/xml; charset=utf-8')
                ->post(config('services.kbo_ws.url'));
            $xml = $res->body();
            if (! $res->successful()) {
                Log::warning('KBO web service', ['status' => $res->status(), 'fault' => self::first($xml, 'faultstring')]);

                return null;
            }

            return self::parse($xml);
        } catch (Throwable $e) {
            Log::warning('KBO web service not reachable', ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return null;
        }
    }

    private static function envelope(string $digits): string
    {
        $now = now('UTC');
        $created = $now->format('Y-m-d\TH:i:s.v\Z');
        $expires = $now->copy()->addMinutes(5)->format('Y-m-d\TH:i:s.v\Z');
        $nonce = random_bytes(16);
        $digest = base64_encode(sha1($nonce.$created.config('services.kbo_ws.password'), true));
        $user = htmlspecialchars((string) config('services.kbo_ws.user'), ENT_XML1);
        $id = (string) Str::uuid();
        $nonce64 = base64_encode($nonce);

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:mes="http://economie.fgov.be/kbopub/webservices/v1/messages" xmlns:dat="http://economie.fgov.be/kbopub/webservices/v1/datamodel">
<soapenv:Header><wsse:Security soapenv:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
<wsu:Timestamp wsu:Id="TS-1"><wsu:Created>{$created}</wsu:Created><wsu:Expires>{$expires}</wsu:Expires></wsu:Timestamp>
<wsse:UsernameToken wsu:Id="UT-1"><wsse:Username>{$user}</wsse:Username>
<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">{$digest}</wsse:Password>
<wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">{$nonce64}</wsse:Nonce>
<wsu:Created>{$created}</wsu:Created></wsse:UsernameToken></wsse:Security>
<mes:RequestContext><mes:Id>{$id}</mes:Id><mes:Language>nl</mes:Language><mes:Language>fr</mes:Language></mes:RequestContext></soapenv:Header>
<soapenv:Body><mes:ReadEnterpriseRequest><dat:EnterpriseNumber>{$digits}</dat:EnterpriseNumber></mes:ReadEnterpriseRequest></soapenv:Body></soapenv:Envelope>
XML;
    }

    /** Reads a ReadEnterpriseReply. Public so tests can feed a recorded answer. */
    public static function parse(string $xml): ?array
    {
        $doc = new DOMDocument;
        if (! @$doc->loadXML($xml, LIBXML_NONET)) {
            return null;
        }
        $x = new DOMXPath($doc);
        $q = fn (string $path, $ctx = null) => $x->query($path, $ctx);
        $text = fn (string $path, $ctx = null) => ($n = $q($path, $ctx)->item(0)) ? trim($n->textContent) : null;
        $code = $text('//*[local-name()="ReplyStatus"]/*[local-name()="Code"]');
        $snapshot = $text('//*[local-name()="ReplyContext"]/*[local-name()="SnapshotDate"]');
        $ent = $q('//*[local-name()="Enterprise"]')->item(0);
        if (! $ent instanceof DOMElement) {
            // 00010: number does not exist, 00014: cancelled, 00016: not active yet.
            return in_array($code, ['00003', '00010', '00014', '00016', '10003'], true) ? ['found' => false, 'snapshot' => $snapshot, 'entry' => null] : null;
        }
        $langs = function (?DOMElement $el) use ($q, $text): array {
            $out = [];
            if ($el) {
                foreach ($q('./*[local-name()="Description"]', $el) as $d) {
                    $l = $text('./*[local-name()="Language"]', $d);
                    $v = $text('./*[local-name()="Value"]', $d);
                    if ($l && $v !== null) {
                        $out[strtolower($l)] = preg_replace('/\s+/', ' ', $v);
                    }
                }
            }

            return $out;
        };
        $sit = $q('./*[local-name()="JuridicalSituation"]', $ent)->item(0);
        $form = $q('./*[local-name()="JuridicalForm"]', $ent)->item(0);

        $name = null;
        foreach ($q('./*[local-name()="Denomination"]', $ent) as $d) {
            if ($text('./*[local-name()="Code"]', $d) === '001') {
                $l = $langs($d);
                $name = $l['nl'] ?? $l['fr'] ?? reset($l) ?: $name;
                if (isset($l['nl'])) {
                    break;
                }
            }
        }
        $address = null;
        foreach ($q('./*[local-name()="Address"]', $ent) as $a) {
            if ($text('./*[local-name()="TypeOfAddress"]/*[local-name()="Code"]', $a) !== 'REGO') {
                continue;
            }
            $street = $langs($q('./*[local-name()="Street"]', $a)->item(0));
            $town = $langs($q('./*[local-name()="Municipality"]', $a)->item(0));
            $box = $text('./*[local-name()="Box"]', $a);
            $line1 = trim(($street['nl'] ?? $street['fr'] ?? '').' '.$text('./*[local-name()="HouseNumber"]', $a).($box ? ' bus '.$box : ''));
            $line2 = trim($text('./*[local-name()="Zipcode"]', $a).' '.($town['nl'] ?? $town['fr'] ?? ''));
            $address = implode(', ', array_filter([$line1, $line2]));
        }

        return ['found' => true, 'snapshot' => $snapshot, 'entry' => [
            'status' => $sit ? $text('./*[local-name()="Status"]/*[local-name()="Code"]', $sit) : null,
            'situation' => $sit ? $text('./*[local-name()="Code"]', $sit) : null,
            'situation_text' => $langs($sit),
            'form' => $form ? $text('./*[local-name()="Code"]', $form) : null,
            'form_text' => $langs($form),
            'start_date' => $text('./*[local-name()="Period"]/*[local-name()="Begin"]', $ent),
            'name' => $name,
            'address' => $address ?: null,
        ]];
    }

    private static function first(string $xml, string $tag): ?string
    {
        return preg_match('/<(?:\w+:)?'.$tag.'>([^<]*)</', $xml, $m) ? $m[1] : null;
    }
}
