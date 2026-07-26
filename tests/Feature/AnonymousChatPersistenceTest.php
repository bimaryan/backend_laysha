<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnonymousChatPersistenceTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->artisan('migrate:fresh');
    }

    public function test_anonymous_non_k5_chat_is_not_saved_to_database(): void
    {
        Http::fake([
            'https://fastapi-laysha.safetalkai.my.id/api/chat' => Http::response([
                'kategori_prediksi' => 'NON_KDRT',
                'balasan_bot' => 'Halo, kami mendengarkan Anda.',
            ], 200),
        ]);

        $response = $this->withHeader('X-Session-ID', 'anon-session-1')
            ->postJson('/api/chat/send', ['message' => 'Saya sedang stres']);

        $response->assertOk();
        $response->assertJsonPath('persist_to_db', false);
        $response->assertJsonPath('store_locally', true);
        $this->assertDatabaseCount('chat_rooms', 0);
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_anonymous_k5_chat_is_saved_and_visible_to_admin_only(): void
    {
        Http::fake([
            'https://fastapi-laysha.safetalkai.my.id/api/chat' => Http::response([
                'kategori_prediksi' => 'K5',
                'balasan_bot' => 'Kami akan membantu segera.',
            ], 200),
        ]);

        $response = $this->withHeader('X-Session-ID', 'anon-session-2')
            ->postJson('/api/chat/send', ['message' => 'Saya diancam']);

        $response->assertOk();
        $response->assertJsonPath('persist_to_db', true);

        $this->assertDatabaseHas('chat_rooms', [
            'session_id' => 'anon-session-2',
            'user_id' => null,
            'latest_category' => 'K5',
        ]);

        $admin = User::create([
            'nama_lengkap' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'tanggal_lahir' => '1990-01-01',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonFragment(['latest_category' => 'K5']);
    }
}
