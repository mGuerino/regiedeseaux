<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\DocumentTemplate;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManageTemplates extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $title = "Gestion des Templates d'Attestation";

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 6;

    public ?array $data = [];

    public function getView(): string
    {
        return 'filament.pages.manage-templates';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DocumentTemplate::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle),

                IconColumn::make('is_default')
                    ->label('Par défaut')
                    ->boolean()
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedStar),

                TextColumn::make('variables')
                    ->label('Variables')
                    ->badge()
                    ->formatStateUsing(fn ($record) => count($record->variables ?? []) . ' variable(s)')
                    ->color('info'),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Créer un template')
                ->icon(Heroicon::Plus)
                ->color('success')
                ->fillForm([])
                ->mountUsing(function ($form) {
                    $form->fill([
                        'is_active' => true,
                        'is_default' => false,
                    ]);
                })
                ->form([
                    TextInput::make('name')
                        ->label('Nom du template')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ex: Attestation Standard'),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->placeholder('Description optionnelle du template'),

                    FileUpload::make('file')
                        ->label('Fichier Word (.docx)')
                        ->required()
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(5120)
                        ->disk('local')
                        ->directory('templates')
                        ->visibility('private'),

                    Checkbox::make('is_active')
                        ->label('Template actif')
                        ->default(true),

                    Checkbox::make('is_default')
                        ->label('Définir comme template par défaut')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    // Générer un nom de fichier unique
                    $nextId = DocumentTemplate::max('id') + 1;
                    $slug = Str::slug($data['name']);
                    $fileName = "template_{$nextId}_{$slug}.docx";
                    $filePath = "templates/{$fileName}";

                    // Renommer le fichier uploadé
                    $uploadedPath = $data['file'];
                    Storage::move($uploadedPath, $filePath);

                    // Détecter les variables
                    $fullPath = storage_path("app/{$filePath}");
                    $variables = [];
                    try {
                        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($fullPath);
                        $variables = $templateProcessor->getVariables();
                    } catch (\Exception) {
                        // Ignorer les erreurs
                    }

                    // Créer le template
                    $template = DocumentTemplate::create([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'file_path' => $filePath,
                        'is_active' => $data['is_active'],
                        'is_default' => false,
                        'variables' => $variables,
                        'variable_mappings' => [],
                    ]);

                    // Définir comme défaut si demandé
                    if ($data['is_default']) {
                        $template->setAsDefault();
                    }

                    Notification::make()
                        ->title('Template créé')
                        ->body(count($variables) . ' variable(s) détectée(s).')
                        ->success()
                        ->send();

                    // Si variables non mappées, proposer le mapping
                    if (count($template->getUnmappedVariables()) > 0) {
                        Notification::make()
                            ->title('Variables non mappées détectées')
                            ->body('Certaines variables ne sont pas reconnues. Utilisez le bouton "Mapper" pour les configurer.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
