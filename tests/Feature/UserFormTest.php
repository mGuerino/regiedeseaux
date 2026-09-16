<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class UserFormTest extends TestCase
{
    /**
     * Le schéma historique ne permet pas RefreshDatabase (les migrations
     * renomment des tables legacy), on crée donc uniquement les tables utiles.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('profile_photo_path')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_supervisor')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_leaving_the_password_blank_keeps_the_current_one(): void
    {
        $user = $this->createAdmin('agent@example.test');
        $this->actingAsPanelUser($user);

        $originalPassword = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'DUPONT',
                'first_name' => 'Jeanne',
                'email' => 'agent@example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertSame('Jeanne', $user->fresh()->first_name);
    }

    public function test_a_new_password_must_be_confirmed(): void
    {
        $user = $this->createAdmin('agent@example.test');
        $this->actingAsPanelUser($user);

        // Sans ce garde-fou, une frappe malheureuse remplacerait en silence le
        // mot de passe du compte édité.
        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'DUPONT',
                'email' => 'agent@example.test',
                'password' => 'un-nouveau-secret',
                'password_confirmation' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['password_confirmation']);
    }

    public function test_the_supervisor_role_is_exposed_on_the_form(): void
    {
        $user = $this->createAdmin('agent@example.test');
        $this->actingAsPanelUser($user);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->assertFormFieldExists('is_supervisor')
            ->assertFormFieldExists('is_admin');
    }

    public function test_the_role_filter_isolates_each_kind_of_account(): void
    {
        $admin = $this->createAdmin('admin@example.test');
        $supervisor = User::create([
            'name' => 'COQUERY', 'email' => 'sup@example.test',
            'password' => Hash::make('x'), 'is_admin' => false, 'is_supervisor' => true,
        ]);
        $plain = User::create([
            'name' => 'SANS-ROLE', 'email' => 'simple@example.test',
            'password' => Hash::make('x'), 'is_admin' => false, 'is_supervisor' => false,
        ]);

        $this->actingAsPanelUser($admin);

        Livewire::test(ListUsers::class)
            ->filterTable('role', ['supervisor'])
            ->assertCanSeeTableRecords([$supervisor])
            ->assertCanNotSeeTableRecords([$admin, $plain]);

        Livewire::test(ListUsers::class)
            ->filterTable('role', ['none'])
            ->assertCanSeeTableRecords([$plain])
            ->assertCanNotSeeTableRecords([$admin, $supervisor]);

        // Plusieurs rôles cochés : l'union, pas l'intersection.
        Livewire::test(ListUsers::class)
            ->filterTable('role', ['supervisor', 'admin'])
            ->assertCanSeeTableRecords([$admin, $supervisor])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_several_accounts_can_be_made_supervisors_at_once(): void
    {
        $admin = $this->createAdmin('admin@example.test');
        $first = $this->createPlainUser('premier@example.test');
        $second = $this->createPlainUser('second@example.test');
        $untouched = $this->createPlainUser('intact@example.test');

        $this->actingAsPanelUser($admin);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction('designate_supervisors', [$first, $second])
            ->assertHasNoErrors();

        $this->assertTrue($first->fresh()->is_supervisor);
        $this->assertTrue($second->fresh()->is_supervisor);
        $this->assertFalse($untouched->fresh()->is_supervisor);

        // Le rôle suffit à valider : rien d'autre à cocher.
        $this->assertTrue($first->fresh()->canValidateAttestations());
    }

    public function test_the_supervisor_role_can_be_revoked_in_bulk(): void
    {
        $admin = $this->createAdmin('admin@example.test');
        $supervisor = $this->createPlainUser('sup@example.test');
        $supervisor->update(['is_supervisor' => true]);

        $this->actingAsPanelUser($admin);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction('revoke_supervisors', [$supervisor])
            ->assertHasNoErrors();

        $this->assertFalse($supervisor->fresh()->is_supervisor);
    }

    private function createPlainUser(string $email): User
    {
        return User::create([
            'name' => strtoupper(explode('@', $email)[0]),
            'email' => $email,
            'password' => Hash::make('x'),
            'is_admin' => false,
            'is_supervisor' => false,
        ]);
    }

    private function createAdmin(string $email): User
    {
        return User::create([
            'name' => 'MARTIN',
            'first_name' => 'Claire',
            'email' => $email,
            'password' => Hash::make('mot-de-passe-initial'),
            'is_admin' => true,
        ]);
    }

    private function actingAsPanelUser(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant(null);
    }
}
