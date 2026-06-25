@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.supplier_management') }}</p>
                <h1>{{ __('messages.add_supplier') }}</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('suppliers.store') }}" method="post">
            @include('suppliers._form', ['buttonLabel' => __('messages.add_supplier')])
        </form>
    </section>
</main>
@endsection
