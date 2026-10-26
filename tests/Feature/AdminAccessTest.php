<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_cannot_access_card_management_even_with_direct_urls(): void
    {
        foreach (['/cards', '/football/cards'] as $path) {
            $this->getJson($path)->assertForbidden();
            $this->postJson($path, [])->assertForbidden();
            $card = Card::factory()->create(['domain' => $path === '/cards' ? 'custom' : 'football']);
            $this->postJson($path.'/'.$card->id, [])->assertForbidden();
        }
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/')->assertOk()->assertDontSee('Upload card picture')->assertDontSee('Create cards');
        $this->actingAs(User::factory()->create())->getJson('/cards')->assertForbidden();
    }

    public function test_only_an_admin_can_log_in_and_manage_the_library(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'a-long-test-password']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'a-long-test-password'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Upload card picture');
        $this->getJson('/cards')->assertOk();
        $this->post('/admin/logout')->assertRedirect('/');
        $this->getJson('/cards')->assertForbidden();
    }

    public function test_admin_seeder_uses_server_config_and_skips_when_unset(): void
    {
        config(['admin.email' => null, 'admin.password' => null]);
        $this->seed(\Database\Seeders\AdminSeeder::class);
        $this->assertSame(0, User::where('is_admin', true)->count());

        config(['admin.email' => 'owner@example.test', 'admin.password' => 'short']);
        $this->seed(\Database\Seeders\AdminSeeder::class);
        $this->assertSame(0, User::where('is_admin', true)->count());

        config(['admin.email' => 'owner@example.test', 'admin.password' => 'a-long-seeded-password']);
        $this->seed(\Database\Seeders\AdminSeeder::class);
        $this->seed(\Database\Seeders\AdminSeeder::class);
        $admin = User::where('email', 'owner@example.test')->sole();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-long-seeded-password', $admin->password));
        $this->post('/admin/login', ['email' => 'owner@example.test', 'password' => 'a-long-seeded-password'])->assertRedirect('/admin');
    }

    public function test_admin_is_created_only_through_the_private_setup_command(): void
    {
        $this->artisan('draft:admin')
            ->expectsQuestion('Admin email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'a-secure-test-password')
            ->assertSuccessful();
        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-secure-test-password', $user->password));
        $this->get('/register')->assertNotFound();
    }
}
