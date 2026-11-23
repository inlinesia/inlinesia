<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminDataManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_data_management_pages(): void
    {
        $this->get(route('admin.data.backup'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_data_management_pages(): void
    {
        $participant = User::factory()->create(['role' => 'individual']);
        $this->actingAs($participant)
            ->get(route('admin.data.delete'))
            ->assertForbidden();
    }

    public function test_admin_can_review_and_delete_a_selected_catalog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->insertNotification($admin->id);

        $this->actingAs($admin)
            ->get(route('admin.data.delete'))
            ->assertSee('Notifikasi')
            ->assertSee('Tinjau penghapusan');

        $this->post(route('admin.data.delete.review'), [
            'selected' => ['notifications'],
        ])
            ->assertSee('Konfirmasi penghapusan')
            ->assertSee('1 data')
            ->assertSee('Notifikasi');

        $this->post(route('admin.data.delete.destroy'), [
            'selected' => ['notifications'],
            'confirmed' => '1',
        ])
            ->assertRedirect(route('admin.data.delete'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_deletion_requires_a_fresh_review_when_catalog_contents_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->insertNotification($admin->id);

        $this->actingAs($admin)->post(route('admin.data.delete.review'), [
            'selected' => ['notifications'],
        ])->assertSee('Konfirmasi penghapusan');

        $this->insertNotification($admin->id);

        $this->post(route('admin.data.delete.destroy'), [
            'selected' => ['notifications'],
            'confirmed' => '1',
        ])->assertStatus(409);

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_user_catalog_deletion_preserves_admin_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $participant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($admin)->post(route('admin.data.delete.review'), [
            'selected' => ['users'],
        ])->assertSee('Konfirmasi penghapusan');

        $this->post(route('admin.data.delete.destroy'), [
            'selected' => ['users'],
            'confirmed' => '1',
        ])->assertRedirect(route('admin.data.delete'));

        $this->assertModelExists($admin);
        $this->assertDatabaseMissing('users', ['id' => $participant->id]);
    }

    public function test_selected_catalog_is_not_deleted_without_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->insertNotification($admin->id);

        $this->actingAs($admin)->post(route('admin.data.delete.review'), [
            'selected' => ['notifications'],
        ])->assertSee('Konfirmasi penghapusan');

        $this->post(route('admin.data.delete.destroy'), [
            'selected' => ['notifications'],
        ])->assertSessionHasErrors('confirmed');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_admin_can_create_list_and_restore_a_server_backup(): void
    {
        Storage::fake('local');
        $admin = $this->primaryAdmin();
        $archivedParticipant = User::factory()->create(['role' => 'individual']);

        $this->actingAs($admin)
            ->post(route('admin.data.backup.create'))
            ->assertRedirect(route('admin.data.backup'))
            ->assertSessionHas('status');

        $backups = app(AdminDataManagementService::class)->listBackups();
        $this->assertCount(1, $backups);

        $name = $backups[0]['name'];
        $sql = Storage::disk('local')->get('backups/'.$name.'/database.sql');
        $this->assertIsString($sql);
        $this->assertStringContainsString('INSERT INTO', $sql);

        $laterParticipant = User::factory()->create(['role' => 'individual']);

        $this->post(route('admin.data.restore'), [
            'backup' => $name,
            'confirmed' => '1',
        ])
            ->assertRedirect(route('admin.data.backup'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $archivedParticipant->id]);
        $this->assertDatabaseMissing('users', ['id' => $laterParticipant->id]);
        $this->assertModelExists($admin);
    }

    public function test_restore_rejects_a_tampered_server_backup_without_changing_data(): void
    {
        Storage::fake('local');
        $admin = $this->primaryAdmin(['name' => 'Admin Original']);

        $name = app(AdminDataManagementService::class)->createBackup();
        $path = 'backups/'.$name.'/database.sql';
        Storage::disk('local')->put($path, str_replace('Admin Original', 'Admin Modified', (string) Storage::disk('local')->get($path)));

        $this->actingAs($admin)
            ->from(route('admin.data.backup'))
            ->post(route('admin.data.restore'), [
                'backup' => $name,
                'confirmed' => '1',
            ])
            ->assertRedirect(route('admin.data.backup'))
            ->assertSessionHasErrors('backup');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Admin Original',
        ]);
    }

    public function test_restore_rejects_an_unknown_backup_name(): void
    {
        Storage::fake('local');
        $admin = $this->primaryAdmin();

        $this->actingAs($admin)
            ->from(route('admin.data.backup'))
            ->post(route('admin.data.restore'), [
                'backup' => 'inlinesia-20260101-000000-aaaa',
                'confirmed' => '1',
            ])
            ->assertSessionHasErrors('backup');
    }

    public function test_backup_and_restore_include_payment_proofs(): void
    {
        Storage::fake('local');
        $admin = $this->primaryAdmin();
        Storage::disk('local')->put('payment-proofs/bukti-1.png', 'isi-bukti');

        $name = app(AdminDataManagementService::class)->createBackup();
        $this->assertTrue(Storage::disk('local')->exists('backups/'.$name.'/payment-proofs/bukti-1.png'));

        Storage::disk('local')->delete('payment-proofs/bukti-1.png');

        $this->actingAs($admin)
            ->post(route('admin.data.restore'), [
                'backup' => $name,
                'confirmed' => '1',
            ])
            ->assertSessionHas('status');

        $this->assertTrue(Storage::disk('local')->exists('payment-proofs/bukti-1.png'));
    }

    public function test_backup_preserves_multiline_text_values(): void
    {
        Storage::fake('local');
        $admin = $this->primaryAdmin(['name' => "Admin\nDua Baris"]);

        $name = app(AdminDataManagementService::class)->createBackup();
        $admin->update(['name' => 'Admin Berubah']);

        $this->actingAs($admin)
            ->post(route('admin.data.restore'), [
                'backup' => $name,
                'confirmed' => '1',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => "Admin\nDua Baris",
        ]);
    }

    public function test_admin_can_view_backup_page_listing_backups(): void
    {
        Storage::fake('local');
        $admin = $this->primaryAdmin();

        $this->actingAs($admin)
            ->get(route('admin.data.backup'))
            ->assertOk()
            ->assertSee('Buat backup di server');

        $name = app(AdminDataManagementService::class)->createBackup();

        $this->actingAs($admin)
            ->get(route('admin.data.backup'))
            ->assertOk()
            ->assertSee($name)
            ->assertSee(route('admin.data.restore'), false)
            ->assertSee(route('admin.data.backup.destroy'), false);
    }

    public function test_admin_can_delete_a_saved_backup(): void
    {
        Storage::fake('local');
        $admin = $this->primaryAdmin();
        $name = app(AdminDataManagementService::class)->createBackup();

        $this->actingAs($admin)
            ->post(route('admin.data.backup.destroy'), [
                'backup' => $name,
                'confirmed' => '1',
            ])
            ->assertRedirect(route('admin.data.backup'))
            ->assertSessionHas('status');

        $this->assertFalse(Storage::disk('local')->exists('backups/'.$name));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function primaryAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'admin',
            'is_primary_admin' => true,
            'email' => config('primary-admin.email'),
        ], $attributes));
    }

    private function insertNotification(int $userId): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'data' => '{}',
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
