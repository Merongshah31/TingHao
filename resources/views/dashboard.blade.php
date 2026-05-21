@extends('layouts.app')

@section('content')
<main class="dashboard-page">
    <section class="dashboard-shell">
        <div>
            <p class="eyebrow">TING HAO SYSTEM</p>
            <h1>Welcome, {{ auth()->user()->name }}</h1>
            <p>This is the protected dashboard area. Inventory, supplier, and report modules can be added here next.</p>
        </div>

        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit" class="btn btn-primary">Logout</button>
        </form>
    </section>
</main>
@endsection
