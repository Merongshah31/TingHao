@extends('layouts.app')

@section('content')
<main class="admin-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">SUPPLIER MANAGEMENT</p>
                <h1>Edit Supplier</h1>
            </div>
        </div>

        <form class="panel-form" action="{{ route('suppliers.update', $supplier) }}" method="post">
            @method('PUT')
            @include('suppliers._form', ['buttonLabel' => 'Save Changes'])
        </form>
    </section>
</main>
@endsection
