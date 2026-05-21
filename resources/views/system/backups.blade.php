@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">SYSTEM MANAGEMENT</p>
                <h1>Backup System Data</h1>
                <p>Create a snapshot record of current system data counts for audit and recovery planning.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('system.settings') }}" class="btn btn-muted">Settings</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <form class="panel-form backup-form" action="{{ route('system.backups.create') }}" method="post">
            @csrf
            <label>
                <span>Backup Label</span>
                <input name="label" type="text" placeholder="Manual backup {{ now()->format('Y-m-d H:i') }}">
            </label>
            <button type="submit" class="btn btn-primary">Create Backup Snapshot</button>
        </form>

        <div class="table-card movement-preview">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Label</th>
                        <th>Summary</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr>
                            <td>{{ $backup->created_at->format('d M Y H:i') }}</td>
                            <td><strong>{{ $backup->label }}</strong></td>
                            <td>
                                <span>Ingredients: {{ $backup->summary['ingredients'] ?? 0 }}</span>
                                <span>Suppliers: {{ $backup->summary['suppliers'] ?? 0 }}</span>
                                <span>Stock Movements: {{ $backup->summary['stock_movements'] ?? 0 }}</span>
                            </td>
                            <td>{{ $backup->creator?->name ?? 'Unknown' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">No backup snapshots recorded yet.</td>
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
