<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminDataManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_admin_can_download_and_restore_a_valid_backup(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_primary_admin' => true,
        ]);
        $archivedParticipant = User::factory()->create(['role' => 'individual']);

        $download = $this->actingAs($admin)
            ->post(route('admin.data.backup.download'));
        $download->assertDownload();
        $contents = $download->streamedContent();

        $this->assertStringContainsString('"format":"inlinesia-data-backup"', $contents);
        $this->assertStringContainsString('"users":[', $contents);

        $laterParticipant = User::factory()->create(['role' => 'individual']);
        $backupFile = UploadedFile::fake()->createWithContent('inlinesia-backup.json', $contents);

        $this->post(route('admin.data.restore'), [
            'backup' => $backupFile,
            'confirmed' => '1',
        ])
            ->assertRedirect(route('admin.data.backup'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $archivedParticipant->id]);
        $this->assertDatabaseMissing('users', ['id' => $laterParticipant->id]);
        $this->assertModelExists($admin);
    }

    public function test_restore_rejects_a_tampered_backup_without_changing_data(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Original',
            'role' => 'admin',
            'is_primary_admin' => true,
        ]);
        $backup = app(AdminDataManagementService::class)->makeBackup();
        $tamperedBackup = str_replace('"Admin Original"', '"Admin Modified"', $backup);

        $this->actingAs($admin)
            ->from(route('admin.data.backup'))
            ->post(route('admin.data.restore'), [
                'backup' => UploadedFile::fake()->createWithContent('tampered.json', $tamperedBackup),
                'confirmed' => '1',
            ])
            ->assertRedirect(route('admin.data.backup'))
            ->assertSessionHasErrors('backup');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Admin Original',
        ]);
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
