<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        
        <div style="background-color: #1f2937; color: #ffffff; padding: 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">Document(s) joint(s)</h1>
        </div>
        
        <div style="padding: 30px;">
            <div style="margin-bottom: 25px;">
                <p style="margin: 0 0 15px 0; white-space: pre-line;">{{ $messageContent }}</p>
            </div>
            
            @if($documents->count() > 0)
                <div style="background-color: #f9fafb; border-left: 4px solid #3b82f6; padding: 15px; margin-top: 25px; border-radius: 4px;">
                    <h2 style="margin: 0 0 10px 0; font-size: 16px; color: #1f2937;">Documents joints :</h2>
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach($documents as $document)
                            <li style="margin-bottom: 5px; color: #4b5563;">
                                <strong>{{ $document->document_name }}</strong>
                                @if($document->document_type)
                                    <span style="font-size: 12px; color: #6b7280;">({{ ucfirst($document->document_type) }})</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        
        <div style="background-color: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0;">Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
