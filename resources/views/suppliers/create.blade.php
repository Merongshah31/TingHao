@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">SUPPLIER MANAGEMENT</p>
                <h1>Add Supplier</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('suppliers.store') }}" method="post">
            @include('suppliers._form', ['buttonLabel' => 'Add Supplier'])
        </form>
    </section>
</main>
@endsection
