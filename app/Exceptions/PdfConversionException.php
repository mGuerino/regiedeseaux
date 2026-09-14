<?php

namespace App\Exceptions;

use Exception;

class PdfConversionException extends Exception
{
    public static function binaryNotFound(string $binary): self
    {
        return new self(
            "LibreOffice est introuvable (binaire « {$binary} »). ".
            'Installez libreoffice-writer sur le serveur ou renseignez LIBREOFFICE_PATH dans le fichier .env.'
        );
    }

    public static function sourceMissing(string $path): self
    {
        return new self("Le document à convertir est introuvable : {$path}");
    }

    public static function conversionFailed(string $errorOutput): self
    {
        return new self('La conversion en PDF a échoué. '.trim($errorOutput));
    }

    public static function outputMissing(): self
    {
        return new self('La conversion en PDF n\'a produit aucun fichier.');
    }
}
