<?php

namespace Tests\Feature;

use App\Filament\Resources\Requests\Pages\CreateRequest;
use App\Models\Municipality;
use App\Models\Road;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class RequestRoadsFilteredByMunicipalityTest extends TestCase
{
    /**
     * Le schéma historique empêche RefreshDatabase (les migrations renomment
     * des tables legacy importées d'un dump SQL), on crée donc à la main
     * uniquement les tables nécessaires au montage du formulaire de demande.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('code_with_division')->nullable();
        });

        Schema::create('roads', function (Blueprint $table) {
            $table->bigIncrements('CDRURU');
            $table->string('municipality_code');
            $table->string('name');
            $table->softDeletes();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('parcels', function (Blueprint $table) {
            $table->increments('objectid');
            $table->string('codcomm')->nullable();
            $table->string('ccosec')->nullable();
            $table->string('ident')->nullable();
            $table->integer('parcelle')->default(0);
            $table->timestamps();
        });

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('municipality_code')->nullable();
            $table->timestamps();
        });

        Schema::create('request_road', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('road_code');
            $table->string('road_name')->nullable();
            $table->timestamps();
        });
    }

    private function actingAsAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'is_admin' => true,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    private function getRoadsField(CreateRequest $page): Select
    {
        /** @var Select $field */
        $field = $page->getSchema('form')->getFlatFields()['roads'];

        return $field;
    }

    public function test_road_search_only_returns_roads_of_the_selected_municipality(): void
    {
        $this->actingAsAdmin();

        Municipality::create(['code' => 'AIX', 'name' => 'AIX EN PROVENCE']);
        Municipality::create(['code' => 'CHN', 'name' => 'CHATEAUNEUF LE ROUGE']);

        $aixRoad = Road::create(['municipality_code' => 'AIX', 'name' => 'FORTUNE FERRINI (Avenue)']);
        $chnRoad = Road::create(['municipality_code' => 'CHN', 'name' => 'FORTUNE DU VILLAGE (Rue)']);

        $component = Livewire::test(CreateRequest::class);
        $component->set('data.municipality_code', 'CHN');

        $results = $this->getRoadsField($component->instance())->getSearchResults('FORTUNE');

        $this->assertArrayHasKey($chnRoad->CDRURU, $results);
        $this->assertArrayNotHasKey(
            $aixRoad->CDRURU,
            $results,
            'La recherche de rues ne doit pas renvoyer les rues d\'une autre commune.'
        );
    }

    public function test_road_search_returns_nothing_when_no_municipality_selected(): void
    {
        $this->actingAsAdmin();

        Municipality::create(['code' => 'AIX', 'name' => 'AIX EN PROVENCE']);
        Road::create(['municipality_code' => 'AIX', 'name' => 'FORTUNE FERRINI (Avenue)']);

        $component = Livewire::test(CreateRequest::class);

        $results = $this->getRoadsField($component->instance())->getSearchResults('FORTUNE');

        $this->assertSame([], $results);
    }
}
