@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">INVENTORY</p>
                <h1>Edit Ingredient</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('inventory.update', $ingredient) }}" method="post">
            @method('PUT')
            @include('inventory._form', ['buttonLabel' => 'Save Changes'])
        </form>
    </section>
</main>
@endsection
