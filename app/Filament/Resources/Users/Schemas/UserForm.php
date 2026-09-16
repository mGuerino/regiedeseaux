<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->description('Nom et coordonnées affichés dans l\'application et sur les attestations.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('Prénom')
                                    ->placeholder('Claire')
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('name')
                                    ->label('Nom')
                                    ->placeholder('COQUERY')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('email')
                                    ->label('Adresse email')
                                    ->placeholder('prenom.nom@eauxdupaysdaix.fr')
                                    ->prefixIcon(Heroicon::OutlinedEnvelope)
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Sert d\'identifiant de connexion et reçoit les notifications.')
                                    // Pleine largeur : une adresse de service tient
                                    // rarement dans une demi-colonne sans être coupée.
                                    ->columnSpanFull(),

                                TextInput::make('phone')
                                    ->label('Téléphone')
                                    ->placeholder('04 13 57 39 20')
                                    ->prefixIcon(Heroicon::OutlinedPhone)
                                    ->tel()
                                    ->maxLength(20)
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->columnSpan(1),

                Section::make('Rôles et accès')
                    ->description('Ce que cette personne peut faire dans l\'application.')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Administrateur')
                            ->helperText('Accès à l\'ensemble de l\'application, y compris les référentiels et les modèles d\'attestation.'),

                        Toggle::make('is_supervisor')
                            ->label('Superviseur')
                            ->helperText('Valide ou refuse les attestations avant leur envoi au demandeur, et reçoit les demandes de validation par email.'),
                    ])
                    ->columnSpan(1),

                Section::make('Photo de profil')
                    ->description('Apparaît dans le menu et en tête de l\'application.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        FileUpload::make('profile_photo_path')
                            ->hiddenLabel()
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('Image carrée, 2 Mo maximum. Facultative.'),
                    ])
                    ->columnSpan(1),

                Section::make('Mot de passe')
                    ->description(fn (string $operation): string => $operation === 'create'
                        ? 'Choisissez un mot de passe de connexion.'
                        : 'Laissez ces deux champs vides pour conserver le mot de passe actuel.')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->schema([
                        TextInput::make('password')
                            ->label('Nouveau mot de passe')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->maxLength(255),

                        TextInput::make('password_confirmation')
                            ->label('Confirmer le mot de passe')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->same('password')
                            // Exigée dès que le mot de passe est saisi, pour ne pas
                            // enregistrer une frappe malheureuse sur le compte d'un tiers.
                            ->required(fn (string $operation, $get) => $operation === 'create' || filled($get('password'))),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
