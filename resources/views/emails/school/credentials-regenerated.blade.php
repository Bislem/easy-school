<h1>Vos nouveaux identifiants Easy School</h1>
<p>Bonjour {{ $administrator->name }},</p>
<p>Les identifiants d'accès de <strong>{{ $school->name }}</strong> ont été régénérés.</p>
<p>Connexion : <a href="{{ route('login') }}">{{ route('login') }}</a></p>
<p>E-mail de connexion : {{ $administrator->email }}<br>Mot de passe temporaire : <strong>{{ $temporaryPassword }}</strong></p>
<p>Votre ancien mot de passe ne fonctionne plus.</p>
