<?php

namespace Tests\Feature;

use App\Support\PaymentQr;
use Tests\TestCase;

class PaymentQrTest extends TestCase
{
    public function test_the_structured_communication_and_the_epc_code(): void
    {
        $this->assertSame('+++000/0001/12962+++', PaymentQr::ogm('INV-1129'));
        $this->assertSame('+++002/0270/00108+++', PaymentQr::ogm('2027/0001'));
        $this->assertSame('+++000/0000/09797+++', PaymentQr::ogm('INV-97')); // check digits 0 become 97

        $p = PaymentQr::payload('CLUK BV', 'BE68 5390 0754 7034', 'KREDBEBB', 108900, PaymentQr::ogm('INV-1129'));
        $this->assertSame("BCD\n002\n1\nSCT\nKREDBEBB\nCLUK BV\nBE68539007547034\nEUR1089.00\n\n\n+++000/0001/12962+++", $p);
        $this->assertNull(PaymentQr::payload('CLUK BV', 'not an iban', null, 1000, 'x'));
        $this->assertNull(PaymentQr::payload('CLUK BV', 'BE68539007547034', null, 0, 'x'));
        $this->assertStringStartsWith('data:image/png;base64,', PaymentQr::png($p));
    }
}
