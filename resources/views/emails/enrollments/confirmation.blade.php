<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Confirmation d'inscription</title></head>
<body style="margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a">
<div style="max-width:600px;margin:32px auto;background:white;border:1px solid #e2e8f0;border-radius:12px;padding:32px">
    <h1 style="font-size:24px;margin:0 0 16px">Confirmez votre inscription</h1>
    <p>Bonjour {{ $enrollment->first_name }},</p>
    <p>Vous avez demandé à vous inscrire à la formation <strong>{{ $enrollment->form->course->title }}</strong>.</p>
    <p>Pour terminer votre inscription, veuillez confirmer votre adresse e-mail :</p>
    <p style="margin:28px 0"><a href="{{ $confirmationUrl }}" style="background:#f4511e;color:white;text-decoration:none;padding:14px 22px;border-radius:8px;font-weight:bold">Confirmer mon inscription</a></p>
    <p style="font-size:13px;color:#64748b">Ce lien expire dans 48 heures. Si vous n'avez pas demandé cette inscription, ignorez cet e-mail.</p>
</div>
</body>
</html>
