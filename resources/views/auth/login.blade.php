@extends('layouts.app')

@section('content')
<main class="login-page">
    <section class="login-shell">
        <aside class="login-art" style="background-image:url('https://images.unsplash.com/photo-1608198093002-ad4e005484ec?auto=format&fit=crop&w=1000&q=80')">
            <div>
                <h2>Mastering the Craft of Inventory</h2>
                <p>Precision in every batch, from raw ingredients to the final artisanal loaf.</p>
            </div>
        </aside>

        <section class="login-panel">
            <div class="logo">Ting Hao</div>
            <p class="sub-logo">Access the Artisanal Ledger</p>

            <h1>Welcome Back</h1>
            <p class="panel-copy">Please enter your credentials to manage the bakery ecosystem.</p>

            @if ($errors->any())
                <div class="form-alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="login-form" action="{{ route('login.store') }}" method="post">
                @csrf
                <label for="email">Email Address</label>
                <div class="field-wrap">
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="admin@tinghao.com" required>
                </div>

                <label for="password">Password</label>
                <div class="field-wrap">
                    <input id="password" name="password" type="password" placeholder="........" required>
                </div>

                <div class="row-between">
                    <label class="remember"><input type="checkbox" name="remember"> Remember Me</label>
                    <a href="#">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary full">Sign In</button>
            </form>

            <div class="panel-footer">
                <span>Encrypted Connection</span>
                <a href="#">Privacy Policy</a>
                <a href="#">System Support</a>
            </div>
        </section>
    </section>
</main>
@endsection
