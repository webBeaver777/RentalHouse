<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User table should NOT have PESEL field (RODO compliance).
     */
    public function test_user_table_has_no_pesel_field(): void
    {
        $this->assertFalse(
            Schema::hasColumn('users', 'pesel'),
            'User table should NOT have PESEL field for RODO compliance'
        );
    }

    /**
     * User table should NOT have global user_type/role field.
     * Roles are per-protocol, not global account types.
     */
    public function test_user_table_has_no_global_user_type(): void
    {
        $columns = Schema::getColumnListing('users');

        $forbiddenColumns = ['user_type', 'type', 'role', 'account_type', 'is_landlord', 'is_tenant'];

        foreach ($forbiddenColumns as $column) {
            $this->assertNotContains(
                $column,
                $columns,
                "User table should NOT have '{$column}' field. Roles are per-protocol, not global."
            );
        }
    }

    /**
     * User can be created with required fields.
     */
    public function test_user_can_be_created(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jan@example.com',
            'name' => 'Jan Kowalski',
        ]);
    }

    /**
     * User has preferred_locale field with default 'pl'.
     */
    public function test_user_has_preferred_locale_with_polish_default(): void
    {
        $user = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
        ]);

        $this->assertEquals('pl', $user->preferred_locale);
    }

    /**
     * User implements FilamentUser interface.
     */
    public function test_user_implements_filament_user(): void
    {
        $user = new User();

        $this->assertInstanceOf(
            \Filament\Models\Contracts\FilamentUser::class,
            $user
        );
    }
}
