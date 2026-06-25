@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.supplier_management') }}</p>
                <h1>{{ __('messages.edit_supplier') }}</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('suppliers.update', $supplier) }}" method="post">
            @method('PUT')
            @include('suppliers._form', ['buttonLabel' => __('messages.save_changes')])
        </form>
    </section>
</main>
@endsection
