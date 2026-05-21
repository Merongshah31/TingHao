@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">SYSTEM MANAGEMENT</p>
                <h1>System Settings</h1>
                <p>Manage core shop details and system-level defaults.</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">Dashboard</a>
                <a href="{{ route('system.backups') }}" class="btn btn-primary">Backups</a>
            </div>
        </div>

        @if (session('status'))
            <div class="success-alert">{{ session('status') }}</div>
        @endif

        <form class="panel-form" action="{{ route('system.settings.update') }}" method="post">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <label>
                    <span>Shop Name</span>
                    <input name="shop_name" value="{{ old('shop_name', $settings['shop_name']) }}" type="text" required>
                    @error('shop_name') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Phone</span>
                    <input name="shop_phone" value="{{ old('shop_phone', $settings['shop_phone']) }}" type="text">
                    @error('shop_phone') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Email</span>
                    <input name="shop_email" value="{{ old('shop_email', $settings['shop_email']) }}" type="email">
                    @error('shop_email') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Low Stock Buffer Days</span>
                    <input name="low_stock_buffer_days" value="{{ old('low_stock_buffer_days', $settings['low_stock_buffer_days']) }}" type="number" min="0" max="365" required>
                    @error('low_stock_buffer_days') <small>{{ $message }}</small> @enderror
                </label>
                <label class="form-wide">
                    <span>Address</span>
                    <textarea name="shop_address" rows="3">{{ old('shop_address', $settings['shop_address']) }}</textarea>
                    @error('shop_address') <small>{{ $message }}</small> @enderror
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </section>
</main>
@endsection
