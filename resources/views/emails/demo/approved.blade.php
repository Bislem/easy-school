<h1>Votre espace Easy School est prêt</h1>
<p>Bonjour {{ $demoRequest->contact_name }},</p>
<p>Votre démonstration pour <strong>{{ $demoRequest->school_name }}</strong> est active jusqu'au {{ $demoRequest->tenant->demo_expires_at->format('d/m/Y H:i') }}.</p>
<p>Connexion : <a href="{{ route('login') }}">{{ route('login') }}</a></p>
<p>E-mail : {{ $demoRequest->email }}<br>Mot de passe temporaire : <strong>{{ $password }}</strong></p>
<p>Pendant la démonstration, le mot de passe et les informations de l'école sont verrouillés.</p>
