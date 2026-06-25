@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.inventory') }}</p>
                <h1>{{ __('messages.add_ingredient') }}</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('inventory.store') }}" method="post">
            @include('inventory._form', ['buttonLabel' => __('messages.add_ingredient')])
        </form>
    </section>
</main>
@endsection
