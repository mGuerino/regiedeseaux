@source
<div class="flex flex-wrap gap-1.5">
    @foreach($parcels->take(5) as $parcel)
        <x-filament::badge>
            {{ $parcel->ident }}
        </x-filament::badge>
    @endforeach
    
    @if($parcels->count() > 5)
        <x-filament::badge>
            +{{ $parcels->count() - 5 }} autres
        </x-filament::badge>
    @endif
</div>

