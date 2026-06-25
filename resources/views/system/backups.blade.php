@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.system_management') }}</p>
                <h1>{{ __('messages.backup_system_data') }}</h1>
                <p>{{ __('messages.backups_intro') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                <a href="{{ route('system.settings') }}" class="btn btn-muted">{{ __('messages.settings') }}</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <div class="backup-overview">
            <div>
                <strong>{{ __('messages.total_backup_snapshots') }}: {{ $backupCount }}</strong>
                <p>{{ __('messages.backup_storage_note') }}</p>
            </div>
            <form action="{{ route('system.backups.cleanup') }}" method="post" onsubmit="return confirm('{{ __('messages.cleanup_backups_confirm') }}')">
                @csrf
                <button type="submit" class="btn btn-muted">{{ __('messages.clean_old_snapshots') }}</button>
            </form>
        </div>

        <form class="panel-form backup-form" action="{{ route('system.backups.create') }}" method="post">
            @csrf
            <label>
                <span>{{ __('messages.backup_label') }}</span>
                <input name="label" type="text" placeholder="Manual backup {{ now()->format('Y-m-d H:i') }}">
            </label>
            <button type="submit" class="btn btn-primary">{{ __('messages.create_backup_snapshot') }}</button>
        </form>

        <div class="table-card movement-preview">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.date') }}</th>
                        <th>{{ __('messages.backup_label') }}</th>
                        <th>{{ __('messages.summary') }}</th>
                        <th>{{ __('messages.recorded_by') }}</th>
                        <th>{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr>
                            <td>{{ $backup->created_at->format('d M Y H:i') }}</td>
                            <td><strong>{{ $backup->label }}</strong></td>
                            <td>
                                <span>{{ __('messages.ingredients') }}: {{ $backup->summary['ingredients'] ?? 0 }}</span>
                                <span>{{ __('messages.suppliers') }}: {{ $backup->summary['suppliers'] ?? 0 }}</span>
                                <span>{{ __('messages.stock_movement') }}: {{ $backup->summary['stock_movements'] ?? 0 }}</span>
                            </td>
                            <td>{{ $backup->creator?->name ?? __('messages.unknown') }}</td>
                            <td class="table-actions">
                                <form method="post" action="{{ route('system.backups.destroy', $backup) }}" onsubmit="return confirm('{{ __('messages.delete_backup_confirm') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-small">{{ __('messages.delete_backup') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">{{ __('messages.no_backup_snapshots') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $backups->links() }}
        </div>
    </section>
</main>
@endsection
