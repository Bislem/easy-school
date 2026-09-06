<h1>Mise à jour de votre demande de démonstration</h1>

<p>Bonjour {{ $demoRequest->contact_name }},</p>

<p>Après étude de votre demande pour <strong>{{ $demoRequest->school_name }}</strong>, nous ne sommes malheureusement pas en mesure de l'approuver pour le moment.</p>

<p><strong>Motif du refus :</strong></p>
<div style="margin: 16px 0; padding: 16px; border-left: 4px solid #12cbb2; background: #f1f9f8; white-space: pre-line;">
    {{ $demoRequest->rejection_reason }}
</div>

<p>Vous pouvez nous contacter si vous souhaitez obtenir des précisions ou soumettre une nouvelle demande après avoir corrigé les éléments indiqués.</p>

<p>Cordialement,<br>L'équipe Easy School</p>
