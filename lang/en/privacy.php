<?php

// Website privacy policy (GDPR). :company, :address, :vat and :email come from Settings › Company details.
return [
    'title' => 'Privacy policy',
    'meta' => 'How CLUK handles your personal data on cluk.live, in My CLUK and in our mails.',
    'updated' => 'Last updated: :date',
    'intro' => 'This policy explains which personal data :company collects through this website, your My CLUK account and our mails, why we do that, how long we keep it and what your rights are.',
    'sections' => [
        [
            'h' => 'Who we are',
            'p' => [':company, :address, VAT :vat, is responsible for your personal data (the “controller”). For any question about privacy, mail :email.'],
        ],
        [
            'h' => 'What we collect and why',
            'li' => [
                'Requests and contact: when you send a dry-hire or sales request, book Cluk Factory or mail us, we keep your name, company, email address, phone number, VAT number and your message, to answer you and make an offer (steps before a contract).',
                'My CLUK account: your name, email address, phone number, company and VAT number, and the quotes, orders, invoices and baskets of your company, to run your account and our agreements (contract). Your password is stored encrypted; our staff cannot see it.',
                'Downloads: for some documents we ask your name and email address first, so we know who received them and can follow up on your question (legitimate interest).',
                'Website statistics, only with your consent: which pages and products you look at, what you search and put in a basket, linked to a random visitor number in a cookie. We do not store your IP address. Once you send a request, sign in or download with your email, these visits are linked to you, so our team can help you better. You can withdraw consent at any time under “Cookie settings”.',
                'Chat with Clukie: on the sales pages you can chat with Clukie, our AI helper. We keep what you write, the answers, the page you were on and, if you give them, your name, email address and phone number, to answer your question and, when you ask for it, pass it to our team (steps before a contract and legitimate interest). Please do not share sensitive personal data in the chat.',
                'Mails: newsletters (only if you said yes), promotions and invitations to existing customers, each with an unsubscribe link (legitimate interest; you can object at any time). If a mail to you cannot be delivered, we stop mailing that address and check it with you.',
                'Afrit 6 members and Cluk Factory bookings: the details you give when you register or book, and your bookings, desks and requests (contract).',
                'Company information: for companies we check VAT numbers with the European VIES service and company data with the Belgian company register (KBO) and the National Bank, to invoice correctly (legal obligation and legitimate interest).',
            ],
        ],
        [
            'h' => 'Cookies',
            'p' => ['Necessary cookies make the site work: your session and sign-in, security against forged forms, your language, your basket and your cookie choice. They need no consent. The statistics cookies (cluk_consent and cluk_vid) are only set when you click “Accept statistics”. We do not use advertising cookies or trackers from others.'],
        ],
        [
            'h' => 'Who we share it with',
            'p' => ['We never sell your data. We only share what is needed with the companies that help us: one.com (hosting, in the EU), Microsoft 365 (email, EU data centres), OkiOki (our accounting and e-invoicing, Belgium) and the Peppol network for e-invoices. Our news comes from our own Facebook, Instagram and LinkedIn pages; pictures are shown from our own server, and when you click through, the policy of that platform applies. What you type to Clukie is sent to Anthropic (the company behind the AI model Claude, based in the United States), only to write the answer. Anthropic processes it on our behalf under a data processing agreement with the EU standard contractual clauses, and does not use it to train its models.'],
        ],
        [
            'h' => 'How long we keep it',
            'li' => [
                'Website statistics: 13 months, then deleted automatically.',
                'Chats with Clukie: 12 months after the last message, then deleted automatically. When a chat is passed to our team, it is also kept as a request (see above).',
                'Requests without an order: up to 3 years after the last contact.',
                'Accounts, quotes, orders and invoices: as long as your account or our relationship runs, and afterwards as long as accounting and tax law requires.',
                'Mailing lists: until you unsubscribe.',
            ],
        ],
        [
            'h' => 'Your rights',
            'p' => ['You can ask to see, correct or delete your data, to limit its use, to receive it in a common format, and object to its use for mails or statistics. Mail :email; we answer within one month. If you are not satisfied, you can file a complaint with the Belgian Data Protection Authority (www.dataprotectionauthority.be).'],
        ],
        [
            'h' => 'Security',
            'p' => ['Our site only works over an encrypted connection. Staff sign in with Microsoft 365 and only see what their role needs; important changes are logged. Passwords of customers and members are stored encrypted.'],
        ],
        [
            'h' => 'Changes',
            'p' => ['When this policy changes, we publish the new version here with its date.'],
        ],
    ],
    'link' => 'Privacy policy',
    'agree' => 'How we handle your data: see our :link.',
];
