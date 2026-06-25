<?php

namespace App\Http\Controllers;

use App\Models\BackupRecord;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\RestockRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function settings(): View
    {
        return view('system.settings', [
            'title' => 'Ting Hao | System Settings',
            'settings' => [
                'shop_name' => SystemSetting::valueFor('shop_name', 'Ting Hao'),
                'shop_phone' => SystemSetting::valueFor('shop_phone', '+1 (555) 0123 4567'),
                'shop_email' => SystemSetting::valueFor('shop_email', 'hello@tinghao.com'),
                'shop_address' => SystemSetting::valueFor('shop_address', '88 Baker Street, Flour District'),
                'low_stock_buffer_days' => SystemSetting::valueFor('low_stock_buffer_days', '7'),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'shop_phone' => ['nullable', 'string', 'max:80'],
            'shop_email' => ['nullable', 'email', 'max:255'],
            'shop_address' => ['nullable', 'string', 'max:1000'],
            'low_stock_buffer_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'group' => 'general']
            );
        }

        return back()->with('status', 'System settings updated.');
    }

    public function backups(): View
    {
        return view('system.backups', [
            'title' => 'Ting Hao | Backup System Data',
            'backups' => BackupRecord::with('creator')->latest()->paginate(10),
            'backupCount' => BackupRecord::count(),
        ]);
    }

    public function createBackup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        BackupRecord::create([
            'label' => $data['label'] ?: 'Manual backup '.now()->format('Y-m-d H:i'),
            'summary' => [
                'users' => User::count(),
                'categories' => Category::count(),
                'ingredients' => Ingredient::count(),
                'suppliers' => Supplier::count(),
                'stock_movements' => StockMovement::count(),
                'restock_requests' => RestockRequest::count(),
                'created_at' => now()->toDateTimeString(),
            ],
            'created_by' => $request->user()->id,
        ]);

        $this->pruneOldBackups(50);

        return back()->with('status', __('messages.backup_snapshot_recorded'));
    }

    public function destroyBackup(BackupRecord $backupRecord): RedirectResponse
    {
        $backupRecord->delete();

        return redirect()
            ->route('system.backups')
            ->with('status', __('messages.backup_deleted_successfully'));
    }

    public function cleanupBackups(): RedirectResponse
    {
        $deletedCount = $this->pruneOldBackups(10);

        return redirect()
            ->route('system.backups')
            ->with('status', __('messages.old_backup_snapshots_cleaned_successfully', [
                'count' => $deletedCount,
            ]));
    }

    private function pruneOldBackups(int $keepLatest): int
    {
        $keepIds = BackupRecord::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($keepLatest)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return 0;
        }

        return BackupRecord::query()
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
