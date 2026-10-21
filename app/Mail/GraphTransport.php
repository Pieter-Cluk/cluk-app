<?php

namespace App\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends email through Microsoft 365 (Microsoft Graph, sendMail) with the
 * app registration "CLUK app": client credentials, application permission
 * Mail.Send. Mail leaves from a real @cluk.live mailbox, so SPF and DKIM
 * of Microsoft 365 apply and it lands in the inbox, not in spam.
 */
class GraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $tenant,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $sender,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0]?->getAddress() ?: $this->sender;

        $response = Http::withToken($this->token())->timeout(20)
            ->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($this->sender).'/sendMail', [
                'message' => $this->payload($email, $from),
                'saveToSentItems' => false,
            ]);

        if (! $response->successful()) {
            throw new TransportException('Microsoft Graph sendMail failed: HTTP '.$response->status().' '.$response->json('error.code').' '.$response->json('error.message'));
        }
    }

    private function token(): string
    {
        return Cache::remember('graph-mail-token', 3000, function () {
            $r = Http::asForm()->timeout(15)->post('https://login.microsoftonline.com/'.$this->tenant.'/oauth2/v2.0/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new TransportException('Microsoft Graph token failed: '.$r->json('error').' '.mb_substr((string) $r->json('error_description'), 0, 160));
            }

            return $r->json('access_token');
        });
    }

    /** @return array<string, mixed> */
    private function payload(Email $email, string $from): array
    {
        $list = fn (array $addresses) => array_map(fn (Address $a) => ['emailAddress' => array_filter(['address' => $a->getAddress(), 'name' => $a->getName() ?: null])], $addresses);
        $html = $email->getHtmlBody();

        $payload = [
            'subject' => (string) $email->getSubject(),
            'body' => $html !== null
                ? ['contentType' => 'HTML', 'content' => is_resource($html) ? stream_get_contents($html) : (string) $html]
                : ['contentType' => 'Text', 'content' => (string) $email->getTextBody()],
            'from' => ['emailAddress' => array_filter(['address' => $from, 'name' => $email->getFrom()[0]?->getName() ?: null])],
            'toRecipients' => $list($email->getTo()),
            'ccRecipients' => $list($email->getCc()),
            'bccRecipients' => $list($email->getBcc()),
            'replyTo' => $list($email->getReplyTo()),
        ];
        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $part->getFilename() ?? 'attachment',
                'contentType' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'contentBytes' => base64_encode($part->getBody()),
            ];
        }
        if ($attachments) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
