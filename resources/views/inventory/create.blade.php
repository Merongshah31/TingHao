@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">INVENTORY</p>
                <h1>Add Ingredient</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('inventory.store') }}" method="post">
            @include('inventory._form', ['buttonLabel' => 'Add Ingredient'])
        </form>
    </section>
</main>
@endsection
