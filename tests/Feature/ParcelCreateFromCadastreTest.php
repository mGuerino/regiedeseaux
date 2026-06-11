<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Parcel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParcelCreateFromCadastreTest extends TestCase
{
    /**
     * Le schéma historique ne permet pas RefreshDatabase (les migrations
     * renomment des tables legacy), on crée donc uniquement les tables utiles.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('municipalities', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('code_with_division')->nullable();
        });

        Schema::create('parcels', function (Blueprint $table) {
            $table->increments('objectid');
            $table->integer('ccocomm')->nullable();
            $table->integer('ccodep')->nullable();
            $table->integer('ccodir')->nullable();
            $table->integer('ccoifp')->nullable();
            $table->string('ccopre')->nullable();
            $table->string('ccosec')->nullable();
            $table->string('ccovoi')->nullable();
            $table->string('codcomm')->nullable();
            $table->string('codeident')->nullable();
            $table->string('cprsecr')->nullable();
            $table->string('dnupla')->nullable();
            $table->string('ident')->nullable();
            $table->integer('parcelle')->nullable();
            $table->string('sect_cad')->nullable();
            $table->timestamps();
        });
    }

    private function makeMunicipality(): Municipality
    {
        return Municipality::create([
            'code' => '13001',
            'name' => 'AIX EN PROVENCE',
            'code_with_division' => '132001',
        ]);
    }

    public function test_creates_a_parcel_with_the_given_section_and_padded_number(): void
    {
        $municipality = $this->makeMunicipality();

        $parcel = Parcel::createFromCadastre($municipality, 'AC', 12);

        $this->assertSame('AC0012', $parcel->ident);
        $this->assertSame('AC', $parcel->ccosec);
        $this->assertSame('AC', $parcel->sect_cad);
        $this->assertSame('132001', $parcel->codcomm);
        $this->assertSame('0012', $parcel->dnupla);
        $this->assertSame(str_pad('132001', 9, ' ', STR_PAD_RIGHT).'AC0012', $parcel->codeident);
        $this->assertSame(12, $parcel->parcelle);
        $this->assertSame(13, $parcel->ccodep);

        $this->assertDatabaseHas('parcels', [
            'ident' => 'AC0012',
            'ccosec' => 'AC',
            'codcomm' => '132001',
        ]);
    }

    public function test_a_section_array_is_rejected_instead_of_creating_a_corrupted_ident(): void
    {
        $municipality = $this->makeMunicipality();

        $this->expectException(\TypeError::class);

        Parcel::createFromCadastre($municipality, ['AB', 'AC'], 12);
    }
}
