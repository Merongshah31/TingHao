@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.inventory') }}</p>
                <h1>{{ __('messages.edit_ingredient') }}</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('inventory.update', $ingredient) }}" method="post">
            @method('PUT')
            @include('inventory._form', ['buttonLabel' => __('messages.save_changes')])
        </form>
    </section>
</main>
@endsection
