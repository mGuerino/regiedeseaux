<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">

        <div style="background-color: #b91c1c; color: #ffffff; padding: 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">Attestation refusée</h1>
        </div>

        <div style="padding: 30px;">
            <p style="margin: 0 0 20px 0;">Bonjour,</p>

            <p style="margin: 0 0 25px 0;">
                {{ $rejectedBy->getFilamentName() }} a refusé l'attestation de la demande
                <strong>{{ $request->reference }}</strong>.
            </p>

            <div style="background-color: #fef2f2; border-left: 4px solid #b91c1c; padding: 15px; margin-bottom: 25px; border-radius: 4px;">
                <h2 style="margin: 0 0 10px 0; font-size: 15px; color: #1f2937;">Motif du refus</h2>
                <p style="margin: 0; white-space: pre-line;">{{ $reason }}</p>
            </div>

            <p style="margin: 0 0 25px 0;">
                Merci de corriger la demande puis de la renvoyer en validation.
            </p>

            <div style="text-align: center; margin-bottom: 25px;">
                <a href="{{ $requestUrl }}" style="display: inline-block; background-color: #1f2937; color: #ffffff; padding: 14px 28px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                    Ouvrir la demande
                </a>
            </div>

            <p style="margin: 0; font-size: 13px; color: #6b7280;">
                Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                <span style="word-break: break-all;">{{ $requestUrl }}</span>
            </p>
        </div>

        <div style="background-color: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0;">Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
