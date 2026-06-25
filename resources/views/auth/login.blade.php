@extends('layouts.app')

@section('content')
<main class="login-page">
    <section class="login-shell">
        <aside class="login-art" style="background-image:url('https://images.unsplash.com/photo-1608198093002-ad4e005484ec?auto=format&fit=crop&w=1000&q=80')">
            <div>
                <h2>{{ __('messages.login_art_title') }}</h2>
                <p>{{ __('messages.login_art_copy') }}</p>
            </div>
        </aside>

        <section class="login-panel">
            <div class="logo">Ting Hao</div>
            <p class="sub-logo">{{ __('messages.login_sub_logo') }}</p>

            <h1>{{ __('messages.welcome_back') }}</h1>
            <p class="panel-copy">{{ __('messages.login_copy') }}</p>

            @if ($errors->any())
                <div class="form-alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="login-form" action="{{ route('login.store') }}" method="post">
                @csrf
                <label for="email">{{ __('messages.email_address') }}</label>
                <div class="field-wrap">
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="admin@tinghao.com" required>
                </div>

                <label for="password">{{ __('messages.password') }}</label>
                <div class="field-wrap">
                    <input id="password" name="password" type="password" placeholder="........" required>
                </div>

                <div class="row-between">
                    <label class="remember"><input type="checkbox" name="remember"> {{ __('messages.remember_me') }}</label>
                    <a href="#">{{ __('messages.forgot_password') }}</a>
                </div>

                <button type="submit" class="btn btn-primary full">{{ __('messages.sign_in') }}</button>
            </form>

            <div class="panel-footer">
                <span>{{ __('messages.encrypted_connection') }}</span>
                <a href="#">{{ __('messages.privacy_policy') }}</a>
                <a href="#">{{ __('messages.system_support') }}</a>
            </div>
        </section>
    </section>
</main>
@endsection
