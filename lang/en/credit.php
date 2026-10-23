<?php

// Customer card › Credit check (KBO/BCE register, VIES, our own payments).
return [
    'title' => 'Credit check',
    'levels' => [
        'green' => 'No warning signs',
        'orange' => 'Be careful',
        'red' => 'Prepayment advised',
        'unknown' => 'Not enough information',
    ],
    'advice' => [
        'green' => 'normal payment terms',
        'orange' => 'check before giving credit or a large order',
        'red' => 'ask for prepayment or a deposit',
        'unknown' => 'no register data or payment history yet',
    ],
    'no_signs' => 'The register, the VAT number and the way they pay us show nothing unusual.',
    'reasons' => [
        'not_found' => 'Enterprise number :number is not in the company register (KBO).',
        'situation' => 'Register (KBO): :what.',
        'situation_other' => 'Register (KBO) shows a special situation: :what.',
        'stopped' => 'Register (KBO): the company is stopped.',
        'young' => 'Young company: started on :date.',
        'vies_invalid' => 'VAT number is not valid (VIES).',
        'vies_invalid_registered' => 'Not registered for VAT in VIES (the company itself is active): check the VAT on invoices.',
        'overdue' => ':n invoice(s) overdue, up to :days days.',
        'paid_late' => 'Paid :n of the last :of invoices late, on average :days days.',
    ],
    'reg' => [
        'name' => 'Official name',
        'form' => 'Legal form',
        'status' => 'Status',
        'active' => 'Active',
        'stopped' => 'Stopped',
        'since' => 'Since',
        'years' => '{0} less than a year|{1} 1 year|[2,*] :n years',
        'address' => 'Registered office',
        'source' => 'Company register (KBO) of :date',
        'open' => 'Open in KBO',
        'pending' => 'Register data arrives with the next KBO update.',
        'not_reached' => 'The KBO did not answer yet; it is tried again automatically.',
        'not_belgian' => 'Register data only for Belgian companies; VIES and our own payment history still count.',
    ],
    'refresh' => 'Check now in the KBO',
    'flash' => [
        'refreshed' => 'Register data checked in the KBO.',
        'not_reached' => 'The KBO could not be reached. Try again later.',
    ],
    'nbb_later' => 'Annual accounts and the growth chart follow as soon as the National Bank connection is active.',
    'mail' => [
        'subject' => 'Credit alert: :name',
        'intro' => 'The credit check of :name has turned red.',
        'reason' => 'Reason',
        'next' => 'Ask for prepayment or a deposit on new orders until this is clear.',
        'button' => 'Open the customer card',
    ],
];
