<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">

        <div style="background-color: #003143; color: #ffffff; padding: 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">Attestation à valider</h1>
        </div>

        <div style="padding: 30px;">
            <p style="margin: 0 0 20px 0;">Bonjour,</p>

            <p style="margin: 0 0 25px 0;">
                {{ $requestedBy->getFilamentName() }} vous demande de valider l'attestation de la demande
                <strong>{{ $request->reference }}</strong>.
            </p>

            <div style="background-color: #f9fafb; border-left: 4px solid #003143; padding: 15px; margin-bottom: 25px; border-radius: 4px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Référence</td>
                        <td style="padding: 4px 0; font-weight: bold;">{{ $request->reference }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Demandeur</td>
                        <td style="padding: 4px 0;">{{ trim(($request->applicant->first_name ?? '').' '.($request->applicant->last_name ?? '')) ?: 'Non renseigné' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Commune</td>
                        <td style="padding: 4px 0;">{{ $request->municipality->name ?? 'Non renseignée' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Date de demande</td>
                        <td style="padding: 4px 0;">{{ $request->request_date?->format('d/m/Y') ?? 'Non renseignée' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #6b7280;">Signataire</td>
                        <td style="padding: 4px 0;">{{ $request->signatory->name ?? 'Non renseigné' }}</td>
                    </tr>
                </table>
            </div>

            <div style="text-align: center; margin-bottom: 25px;">
                <a href="{{ $validationUrl }}" style="display: inline-block; background-color: #003143; color: #ffffff; padding: 14px 28px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                    Consulter et valider l'attestation
                </a>
            </div>

            <p style="margin: 0 0 25px 0; font-size: 14px; color: #4b5563;">
                Avant de valider, vérifiez sur l'attestation : la commune et les parcelles concernées,
                les mentions de raccordement à l'eau potable et à l'assainissement, ainsi que le nom du
                signataire. En cas d'erreur, refusez l'attestation en précisant le motif : l'agent sera
                prévenu et pourra la corriger.
            </p>

            <p style="margin: 0; font-size: 13px; color: #6b7280;">
                Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                <span style="word-break: break-all;">{{ $validationUrl }}</span>
            </p>
        </div>

        <div style="background-color: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0;">Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
