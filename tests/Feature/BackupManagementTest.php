<?php

namespace Tests\Feature;

use App\Models\BackupRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_backup_snapshot(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $backup = $this->backupRecord($admin, 'Delete me');

        $this->actingAs($admin)
            ->delete(route('system.backups.destroy', $backup))
            ->assertRedirect(route('system.backups'))
            ->assertSessionHas('status', __('messages.backup_deleted_successfully'));

        $this->assertDatabaseMissing('backup_records', [
            'id' => $backup->id,
        ]);
    }

    public function test_cleanup_keeps_latest_ten_backup_snapshots(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $oldestIds = [];
        $latestIds = [];

        for ($i = 0; $i < 12; $i++) {
            $backup = $this->backupRecord(
                $admin,
                'Snapshot '.$i,
                Carbon::parse('2026-06-25 08:00:00')->addMinutes($i)
            );

            if ($i < 2) {
                $oldestIds[] = $backup->id;
            } else {
                $latestIds[] = $backup->id;
            }
        }

        $this->actingAs($admin)
            ->post(route('system.backups.cleanup'))
            ->assertRedirect(route('system.backups'))
            ->assertSessionHas('status', __('messages.old_backup_snapshots_cleaned_successfully', ['count' => 2]));

        $this->assertSame(10, BackupRecord::count());

        foreach ($oldestIds as $id) {
            $this->assertDatabaseMissing('backup_records', ['id' => $id]);
        }

        foreach ($latestIds as $id) {
            $this->assertDatabaseHas('backup_records', ['id' => $id]);
        }
    }

    public function test_creating_backup_auto_keeps_latest_fifty_snapshots(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        for ($i = 0; $i < 50; $i++) {
            $this->backupRecord($admin, 'Existing '.$i, Carbon::parse('2026-06-25 08:00:00')->addMinutes($i));
        }

        $oldest = BackupRecord::oldest('created_at')->firstOrFail();

        Carbon::setTestNow('2026-06-25 09:00:00');

        $this->actingAs($admin)
            ->post(route('system.backups.create'), [
                'label' => 'Fresh snapshot',
            ])
            ->assertRedirect();

        Carbon::setTestNow();

        $this->assertSame(50, BackupRecord::count());
        $this->assertDatabaseMissing('backup_records', ['id' => $oldest->id]);
        $this->assertDatabaseHas('backup_records', ['label' => 'Fresh snapshot']);
    }

    public function test_staff_cannot_manage_backup_snapshots(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $backup = $this->backupRecord($admin, 'Protected');

        $this->actingAs($staff)->get(route('system.backups'))->assertForbidden();
        $this->actingAs($staff)->post(route('system.backups.cleanup'))->assertForbidden();
        $this->actingAs($staff)->delete(route('system.backups.destroy', $backup))->assertForbidden();

        $this->assertDatabaseHas('backup_records', [
            'id' => $backup->id,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function backupRecord(User $creator, string $label, ?Carbon $createdAt = null): BackupRecord
    {
        $backupRecord = BackupRecord::create([
            'label' => $label,
            'summary' => [
                'ingredients' => 0,
                'suppliers' => 0,
                'stock_movements' => 0,
            ],
            'created_by' => $creator->id,
        ]);

        if ($createdAt) {
            $backupRecord->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        return $backupRecord;
    }
}
