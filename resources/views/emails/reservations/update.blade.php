<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;background:#f3f4f6;padding:24px;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;">
        <tr><td style="padding:32px;">
            <h1 style="margin:0 0 16px;font-size:22px;">{{ $agency->trading_name }} — Mise à jour de la réservation</h1>
            <p style="margin:0 0 16px;">Bonjour {{ $reservation->user->name }},</p>
            <p style="margin:0 0 16px;line-height:1.5;">{{ $messageText }}</p>
            <p style="margin:0 0 16px;line-height:1.5;">
                Référence : <strong>{{ $reservation->reservation_number }}</strong><br>
                Statut actuel : <strong>{{ collect(\App\Enums\ReservationStatus::getMeta())->firstWhere('value', $reservation->status->value)['label'] ?? $reservation->status->value }}</strong>
            </p>
            <p style="margin:0;line-height:1.5;">Votre contrat de réservation mis à jour est joint au format PDF.</p>
        </td></tr>
    </table>
</body>
</html>
