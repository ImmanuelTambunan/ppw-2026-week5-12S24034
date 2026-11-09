<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\Peminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoapClearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wsdl_endpoint_returns_xml_contract(): void
    {
        $response = $this->get('/soap/sipus.wsdl');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');
        $this->assertStringContainsString('LibraryClearanceSoapService', $response->getContent());
        $this->assertStringContainsString('CheckClearanceStatus', $response->getContent());
    }

    public function test_soap_clearance_returns_cleared_when_no_active_loans(): void
    {
        $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:m="http://sipus.del.ac.id/soap/clearance">
    <soap:Body>
        <m:CheckClearanceStatusRequest>
            <m:nim_peminjam>12S24034</m:nim_peminjam>
        </m:CheckClearanceStatusRequest>
    </soap:Body>
</soap:Envelope>
XML;

        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml; charset=utf-8',
        ], $xmlPayload);

        $response->assertStatus(200);
        $this->assertStringContainsString('<m:active_loans>0</m:active_loans>', $response->getContent());
        $this->assertStringContainsString('<m:is_cleared>true</m:is_cleared>', $response->getContent());
    }

    public function test_soap_clearance_returns_uncleared_when_active_loans_exist(): void
    {
        $kategori = Kategori::factory()->create();
        $buku = Buku::factory()->create(['kategori_id' => $kategori->id]);
        Peminjaman::create([
            'buku_id' => $buku->id,
            'nim_peminjam' => '12S22001',
            'nama_peminjam' => 'Bona Pandiangan',
            'tanggal_pinjam' => now()->toDateString(),
            'durasi_hari' => 7,
            'status' => 'dipinjam',
        ]);

        $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:m="http://sipus.del.ac.id/soap/clearance">
    <soap:Body>
        <m:CheckClearanceStatusRequest>
            <m:nim_peminjam>12S22001</m:nim_peminjam>
        </m:CheckClearanceStatusRequest>
    </soap:Body>
</soap:Envelope>
XML;

        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml; charset=utf-8',
        ], $xmlPayload);

        $response->assertStatus(200);
        $this->assertStringContainsString('<m:active_loans>1</m:active_loans>', $response->getContent());
        $this->assertStringContainsString('<m:is_cleared>false</m:is_cleared>', $response->getContent());
    }

    public function test_soap_clearance_returns_soap_fault_on_malformed_xml(): void
    {
        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml; charset=utf-8',
        ], '<invalid>xml');

        $response->assertStatus(500);
        $this->assertStringContainsString('<soap:Fault>', $response->getContent());
        $this->assertStringContainsString('Malformed XML', $response->getContent());
    }

    public function test_soap_clearance_returns_soap_fault_when_nim_empty(): void
    {
        $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:m="http://sipus.del.ac.id/soap/clearance">
    <soap:Body>
        <m:CheckClearanceStatusRequest>
            <m:nim_peminjam></m:nim_peminjam>
        </m:CheckClearanceStatusRequest>
    </soap:Body>
</soap:Envelope>
XML;

        $response = $this->call('POST', '/soap/sipus', [], [], [], [
            'CONTENT_TYPE' => 'text/xml; charset=utf-8',
        ], $xmlPayload);

        $response->assertStatus(500);
        $this->assertStringContainsString('<soap:Fault>', $response->getContent());
    }
}
