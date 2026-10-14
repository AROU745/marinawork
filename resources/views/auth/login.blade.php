@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <section class="panel" style="max-width:420px;margin:2rem auto 0;">
        <h1>Connexion</h1>
        <p class="lead">Connectez-vous pour accéder à AngeLe WiFi.</p>

        <form action="{{ route('login.attempt') }}" method="POST" class="login-form">
            @csrf
            <label>
                E-mail
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>
            <label>
                Mot de passe
                <input type="password" name="password" required>
            </label>
            <label class="check">
                <input type="checkbox" name="remember" value="1">
                Se souvenir de moi
            </label>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Se connecter</button>
        </form>
    </section>
@endsection
