<h1>{{ $reason === 'account_created' ? 'Votre compte parent a été créé' : 'Votre mot de passe a été réinitialisé' }}</h1>
<p>Bonjour {{ $parent->first_name ?: $parent->name }},</p>
<p>Utilisez le mot de passe temporaire ci-dessous pour vous connecter à l'application Easy School :</p>
<p><strong>{{ $temporaryPassword }}</strong></p>
<p>Ce mot de passe expire le {{ $parent->temporary_password_expires_at->format('d/m/Y à H:i') }}. L'application vous demandera de choisir immédiatement un nouveau mot de passe.</p>
<p>Si vous n'êtes pas à l'origine de cette demande, contactez votre établissement.</p>
