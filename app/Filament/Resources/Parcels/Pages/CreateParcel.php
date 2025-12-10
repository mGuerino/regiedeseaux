<?php

namespace App\Filament\Resources\Parcels\Pages;

use App\Filament\Resources\Parcels\ParcelResource;
use App\Models\Municipality;
use Filament\Resources\Pages\CreateRecord;

class CreateParcel extends CreateRecord
{
    protected static string $resource = ParcelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Récupérer la commune
        $municipality = Municipality::where('code_with_division', $data['codcomm'])->first();
        
        if ($municipality && isset($data['ccosec']) && isset($data['parcelle'])) {
            $section = $data['ccosec'];
            $dnuplaNumber = (int) $data['parcelle'];
            $dnupla = str_pad($dnuplaNumber, 4, '0', STR_PAD_LEFT);
            $ident = $section . $dnupla;
            $codcomm = $municipality->code_with_division;
            $codeident = str_pad($codcomm, 9, ' ', STR_PAD_RIGHT) . $ident;

            // Générer automatiquement tous les champs
            $data['ident'] = $ident;
            $data['dnupla'] = $dnupla;
            $data['sect_cad'] = $section;
            $data['codeident'] = $codeident;
            $data['parcelle'] = $dnuplaNumber;
            
            // Codes administratifs avec valeurs par défaut
            $data['ccocomm'] = 0;
            $data['ccodep'] = (int) substr($codcomm, 0, 2);
            $data['ccodir'] = 0;
            $data['ccoifp'] = 0;
            $data['ccopre'] = '';
            $data['ccovoi'] = '';
            $data['cprsecr'] = '';
        }

        return $data;
    }
}
