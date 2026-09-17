<?php

namespace Tests\Feature;

use App\Enums\ValidationStatus;
use App\Exceptions\PdfConversionException;
use App\Filament\Actions\GenerateWordAction;
use App\Filament\Actions\SendEmailFromRequestAction;
use App\Filament\Pages\ManageTemplates;
use App\Filament\Resources\Agents\AgentResource;
use App\Filament\Resources\Applicants\ApplicantResource;
use App\Filament\Resources\Contacts\ContactResource;
use App\Filament\Resources\Municipalities\MunicipalityResource;
use App\Filament\Resources\Parcels\ParcelResource;
use App\Filament\Resources\Requests\Pages\EditRequest;
use App\Filament\Resources\Requests\Pages\ValidateRequest;
use App\Filament\Resources\Requests\RequestResource;
use App\Filament\Resources\Roads\RoadResource;
use App\Filament\Resources\Users\UserResource;
use App\Mail\AttestationValidationRejected;
use App\Mail\AttestationValidationRequested;
use App\Mail\DocumentEmail;
use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\EmailLog;
use App\Models\Municipality;
use App\Models\Parcel;
use App\Models\Request;
use App\Models\Road;
use App\Models\User;
use App\Services\AttestationValidationService;
use App\Services\DocxToPdfConverter;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;
use Tests\TestCase;

class AttestationValidationWorkflowTest extends TestCase
{
    /**
     * Le schéma historique empêche RefreshDatabase (les migrations renomment
     * des tables legacy importées d'un dump SQL), on crée donc à la main
     * uniquement les tables nécessaires au workflow de validation.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('templates');
        Mail::fake();
        $this->fakePdfConverter();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_supervisor')->default(false);
            $table->string('profile_photo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('postal_code')->nullable();
            $table->string('code_with_division')->nullable();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('email')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('signature_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('parcels', function (Blueprint $table) {
            $table->increments('objectid');
            $table->string('codcomm')->nullable();
            $table->string('ccosec')->nullable();
            $table->string('ident')->nullable();
            $table->timestamps();
        });

        Schema::create('parcel_request', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('parcel_id')->nullable();
            $table->timestamps();
        });

        Schema::create('roads', function (Blueprint $table) {
            $table->bigIncrements('CDRURU');
            $table->string('municipality_code')->nullable();
            $table->string('name')->nullable();
            $table->softDeletes();
        });

        Schema::create('request_road', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('road_code');
            $table->string('road_name')->nullable();
            $table->timestamps();
        });

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();
            $table->date('request_date')->nullable();
            $table->date('response_date')->nullable();
            $table->integer('request_status')->default(1);
            $table->string('validation_status')->nullable();
            $table->timestamp('validation_requested_at')->nullable();
            $table->unsignedBigInteger('validation_requested_by')->nullable();
            $table->json('validation_notified_to')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('water_status')->default(false);
            $table->boolean('wastewater_status')->default(false);
            $table->text('observations')->nullable();
            $table->string('map_url')->nullable();
            $table->unsignedBigInteger('applicant_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('followed_by_user_id')->nullable();
            $table->unsignedBigInteger('signatory_id')->nullable();
            $table->unsignedBigInteger('certifier_id')->nullable();
            $table->unsignedBigInteger('contact_person_id')->nullable();
            $table->string('municipality_code')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_by')->nullable();
            $table->string('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->string('updated_by')->nullable();
            $table->date('updated_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('message');
            $table->json('recipients');
            $table->json('recipient_keys')->nullable();
            $table->json('document_ids');
            $table->string('sent_by');
            $table->unsignedInteger('recipients_count');
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('document_type')->nullable();
            $table->string('document_name')->nullable();
            $table->string('file_name')->nullable();
            $table->text('observations')->nullable();
            $table->string('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('variables')->nullable();
            $table->json('variable_mappings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Le convertisseur réel dépend de LibreOffice : on le remplace par un
     * double qui produit un PDF factice, la conversion étant couverte par
     * son propre test unitaire.
     */
    private function fakePdfConverter(): void
    {
        $this->app->instance(DocxToPdfConverter::class, new class extends DocxToPdfConverter
        {
            public function convert(string $sourcePath, string $outputDirectory): string
            {
                if (! is_dir($outputDirectory)) {
                    mkdir($outputDirectory, 0755, true);
                }

                $pdfPath = rtrim($outputDirectory, '/').'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';
                file_put_contents($pdfPath, '%PDF-1.4 contenu de test');

                return $pdfPath;
            }
        });
    }

    public function test_the_supervisors_to_notify_exclude_non_supervisors_and_users_without_email(): void
    {
        $this->createUser('agent@example.test');
        User::create(['name' => 'Zoe', 'email' => 'zoe@example.test', 'is_admin' => true, 'is_supervisor' => true]);
        User::create(['name' => 'Alice', 'email' => 'alice@example.test', 'is_admin' => true, 'is_supervisor' => true]);
        User::create(['name' => 'Sans email', 'email' => '', 'is_admin' => true, 'is_supervisor' => true]);

        $supervisors = app(AttestationValidationService::class)->supervisorsToNotify();

        // Triés par nom, sans l'agent ni le superviseur dépourvu d'adresse
        $this->assertSame(
            ['alice@example.test', 'zoe@example.test'],
            $supervisors->pluck('email')->all(),
        );
    }

    public function test_the_send_for_validation_modal_names_the_supervisors_who_will_be_notified(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);

        $request = $this->createRequest();

        $html = view('filament.actions.send-for-validation-modal', [
            'reference' => $request->reference,
            'supervisors' => app(AttestationValidationService::class)->supervisorsToNotify(),
        ])->render();

        $this->assertStringContainsString($request->reference, $html);
        $this->assertStringNotContainsString('Aucun superviseur', $html);

        // Les destinataires sont désormais proposés en cases à cocher, pour
        // n'alerter qu'une partie des superviseurs si l'agent le souhaite.
        $this->assertContains(
            $supervisor->id,
            app(AttestationValidationService::class)->supervisorsToNotify()->pluck('id')->all(),
        );
    }

    public function test_unchecking_every_supervisor_sends_for_validation_without_email(): void
    {
        Mail::fake();

        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();

        Livewire::test(EditRequest::class, ['record' => $request->id])
            ->callAction('send_for_validation', data: ['supervisor_ids' => []])
            ->assertHasNoActionErrors();

        Mail::assertNotSent(AttestationValidationRequested::class);

        // En attente malgré tout, et validable par n'importe quel superviseur.
        $this->assertTrue($request->fresh()->isAwaitingValidation());
        $this->assertSame([], $request->fresh()->validation_notified_to);
        $this->assertTrue($supervisor->canValidateAttestations());
    }

    public function test_only_the_selected_supervisors_receive_the_validation_email(): void
    {
        Mail::fake();

        $agent = $this->createUser('agent@example.test');
        $notified = $this->createUser('prevenu@example.test', isSupervisor: true);
        $ignored = $this->createUser('non-prevenu@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();

        app(AttestationValidationService::class)->sendForValidation($request, $agent, [$notified->id]);

        Mail::assertSent(
            AttestationValidationRequested::class,
            fn ($mail) => $mail->hasTo($notified->email),
        );
        Mail::assertNotSent(
            AttestationValidationRequested::class,
            fn ($mail) => $mail->hasTo($ignored->email),
        );

        // Le superviseur non prévenu garde le droit de valider : la sélection
        // ne concerne que l'email.
        $this->assertTrue($ignored->canValidateAttestations());
        $this->assertSame([$notified->id], $request->fresh()->validation_notified_to);
    }

    public function test_the_notified_supervisors_are_listed_on_the_request(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent, [$supervisor->id]);

        $notified = $request->fresh()->validationNotifiedUsers();

        $this->assertCount(1, $notified);
        $this->assertSame($supervisor->id, $notified->first()->id);
    }

    public function test_cancelling_a_validation_clears_the_notified_supervisors(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent, [$supervisor->id]);

        $request->fresh()->resetValidation();

        $this->assertNull($request->fresh()->validation_notified_to);
        $this->assertTrue($request->fresh()->validationNotifiedUsers()->isEmpty());
    }

    public function test_the_send_for_validation_modal_warns_when_no_supervisor_is_designated(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->actingAsPanelUser($agent);

        $request = $this->createRequest();

        $html = view('filament.actions.send-for-validation-modal', [
            'reference' => $request->reference,
            'supervisors' => app(AttestationValidationService::class)->supervisorsToNotify(),
        ])->render();

        $this->assertStringContainsString('Aucun superviseur', $html);
    }

    private function createUser(string $email, bool $isSupervisor = false, bool $isAdmin = true): User
    {
        return User::create([
            'name' => $isSupervisor ? 'Superviseur' : 'Agent',
            'email' => $email,
            'is_admin' => $isAdmin,
            'is_supervisor' => $isSupervisor,
        ]);
    }

    /**
     * Envoie la demande en validation puis la fait valider par le superviseur.
     */
    private function createValidatedRequest(User $agent, User $supervisor): Request
    {
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);
        $service->approve($request->fresh(), $supervisor);

        return $request->fresh();
    }

    private function failPdfConversion(): void
    {
        $this->app->instance(DocxToPdfConverter::class, new class extends DocxToPdfConverter
        {
            public function convert(string $sourcePath, string $outputDirectory): string
            {
                throw PdfConversionException::conversionFailed('LibreOffice indisponible');
            }
        });
    }

    private function actingAsPanelUser(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * Modèle Word minimal contenant la référence et le placeholder de signature.
     */
    private function createDefaultTemplate(): DocumentTemplate
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Attestation ${reference}');
        $section->addText('${signature}');

        $absolutePath = Storage::disk('templates')->path('template_test.docx');
        (new Word2007($phpWord))->save($absolutePath);

        return DocumentTemplate::create([
            'name' => 'Modèle de test',
            'file_path' => 'template_test.docx',
            'is_active' => true,
            'is_default' => true,
            'variables' => ['reference', 'signature'],
            'variable_mappings' => [],
        ]);
    }

    private function createSignatureImage(): string
    {
        $image = imagecreatetruecolor(300, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 10, 40, 'Signature', imagecolorallocate($image, 0, 0, 0));

        $temporaryPath = tempnam(sys_get_temp_dir(), 'sig').'.png';
        imagepng($image, $temporaryPath);

        $relativePath = 'signatures/signature-test.png';
        Storage::disk('public')->put($relativePath, file_get_contents($temporaryPath));
        @unlink($temporaryPath);

        return $relativePath;
    }

    /**
     * Signature enregistrée dans un format que Word ne sait pas afficher.
     */
    private function createWebpSignatureImage(): string
    {
        $image = imagecreatetruecolor(300, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        $temporaryPath = tempnam(sys_get_temp_dir(), 'sig').'.webp';
        imagewebp($image, $temporaryPath);

        $relativePath = 'signatures/signature-test.webp';
        Storage::disk('public')->put($relativePath, file_get_contents($temporaryPath));
        @unlink($temporaryPath);

        return $relativePath;
    }

    private function createRequest(bool $withSignatureImage = true): Request
    {
        Municipality::create(['code' => 'AIX', 'name' => 'AIX EN PROVENCE', 'code_with_division' => 'AIX000']);

        $applicant = Applicant::create([
            'last_name' => 'MARTIN',
            'first_name' => 'Claire',
            'email' => 'claire.martin@example.test',
        ]);

        $signatory = Agent::create([
            'type' => 'SIGNATAIRE',
            'name' => 'Jean DUPONT',
            'title' => 'Directeur',
            'signature_path' => $withSignatureImage ? $this->createSignatureImage() : null,
        ]);

        $contact = Contact::create([
            'last_name' => 'NOTAIRE',
            'first_name' => 'Paul',
            'email' => 'paul.notaire@example.test',
        ]);

        $request = Request::create([
            'reference' => 'ATT-2026-001',
            'request_date' => now()->toDateString(),
            'request_status' => 1,
            'applicant_id' => $applicant->id,
            'contact_id' => $contact->id,
            'followed_by_user_id' => auth()->id(),
            'signatory_id' => $signatory->id,
            'municipality_code' => 'AIX',
        ]);

        // Parcelle et rue de la commune, obligatoires dans le formulaire d'édition
        $parcel = Parcel::forceCreate(['codcomm' => 'AIX000', 'ccosec' => 'AB', 'ident' => 'AB0001']);
        $road = Road::forceCreate(['CDRURU' => 1, 'municipality_code' => 'AIX', 'name' => 'AVENUE DES CARDEURS']);

        $request->parcels()->attach($parcel->ident);
        $request->roads()->attach($road->getKey(), ['road_name' => $road->name]);

        return $request;
    }

    /**
     * Le document Word contient-il une image ?
     */
    private function documentContainsImage(Document $document): bool
    {
        $zip = new \ZipArchive;
        $this->assertTrue(
            $zip->open(Storage::disk('public')->path($document->file_name)),
            'Le document généré doit être une archive docx valide.'
        );

        $hasMedia = false;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (str_contains($zip->getNameIndex($index), 'word/media/')) {
                $hasMedia = true;
                break;
            }
        }
        $zip->close();

        return $hasMedia;
    }

    public function test_sending_for_validation_marks_the_request_pending_and_notifies_supervisors(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->createUser('autre-superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();

        $pdfDocument = app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $request->refresh();

        $this->assertSame(ValidationStatus::Pending, $request->validation_status);
        $this->assertNotNull($request->validation_requested_at);
        $this->assertSame($agent->id, $request->validation_requested_by);
        $this->assertStringEndsWith('.pdf', $pdfDocument->file_name);
        $this->assertTrue(Storage::disk('public')->exists($pdfDocument->file_name));

        Mail::assertSent(AttestationValidationRequested::class, 2);
    }

    public function test_no_signature_is_applied_before_validation(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();

        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $wordDocument = $request->latestGeneratedDocument('docx');

        $this->assertNotNull($wordDocument);
        $this->assertFalse(
            $this->documentContainsImage($wordDocument),
            'La signature ne doit pas être apposée avant la validation par un superviseur.'
        );
    }

    public function test_approving_applies_the_signature_and_produces_a_signed_pdf(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);
        $pdfDocument = $service->approve($request->fresh(), $supervisor);

        $request->refresh();

        $this->assertSame(ValidationStatus::Approved, $request->validation_status);
        $this->assertNotNull($request->validated_at);
        $this->assertSame($supervisor->id, $request->validated_by);
        $this->assertNull($request->rejection_reason);

        $this->assertTrue(Storage::disk('public')->exists($pdfDocument->file_name));
        $this->assertStringEndsWith('.pdf', $pdfDocument->file_name);

        $wordDocument = $request->latestGeneratedDocument('docx');
        $this->assertTrue(
            $this->documentContainsImage($wordDocument),
            "L'image de signature du signataire doit être apposée sur l'attestation validée."
        );
    }

    public function test_approving_without_signature_image_still_validates_the_request(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest(withSignatureImage: false);
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);
        $service->approve($request->fresh(), $supervisor);

        $request->refresh();

        $this->assertSame(ValidationStatus::Approved, $request->validation_status);
        $this->assertFalse(
            $this->documentContainsImage($request->latestGeneratedDocument('docx')),
            'Sans image de signature enregistrée, aucune image ne doit être insérée.'
        );
    }

    public function test_rejecting_records_the_reason_and_notifies_the_requesting_agent(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);
        $service->reject($request->fresh(), $supervisor, 'Le statut assainissement est erroné.');

        $request->refresh();

        $this->assertSame(ValidationStatus::Rejected, $request->validation_status);
        $this->assertSame('Le statut assainissement est erroné.', $request->rejection_reason);
        $this->assertNull($request->validated_at);
        $this->assertNull($request->validated_by);

        Mail::assertSent(AttestationValidationRejected::class, function ($mail) use ($agent) {
            return $mail->hasTo($agent->email)
                && $mail->reason === 'Le statut assainissement est erroné.';
        });
    }

    public function test_a_rejected_attestation_can_be_sent_for_validation_again(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);
        $service->reject($request->fresh(), $supervisor, 'À corriger.');

        $request->refresh();
        $this->assertTrue($request->canBeSentForValidation());

        $service->sendForValidation($request, $agent);
        $request->refresh();

        $this->assertSame(ValidationStatus::Pending, $request->validation_status);
        $this->assertNull($request->rejection_reason);
    }

    public function test_an_attestation_awaiting_validation_cannot_be_resubmitted(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->assertFalse($request->fresh()->canBeSentForValidation());
    }

    public function test_non_supervisor_cannot_open_the_validation_page(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->actingAsPanelUser($agent);

        $request = $this->createRequest();

        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->assertForbidden();
    }

    public function test_supervisor_can_open_the_validation_page(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);

        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->assertSuccessful()
            ->assertSee('ATT-2026-001');
    }

    public function test_the_validation_page_exposes_the_pdf_preview_url(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);

        $page = Livewire::test(ValidateRequest::class, ['record' => $request->id])->instance();

        $this->assertStringStartsWith(
            route('requests.attestation.preview', ['request' => $request->id]),
            $page->getPreviewUrl()
        );
    }

    public function test_the_preview_url_changes_once_the_attestation_is_signed(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);
        $beforeApproval = Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->instance()
            ->getPreviewUrl();

        // Le PDF signé écrase le précédent : sans marqueur de version dans
        // l'URL, le navigateur resservirait au superviseur la version non signée.
        $this->travel(1)->second();
        $service->approve($request->fresh(), $supervisor);

        $afterApproval = Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->instance()
            ->getPreviewUrl();

        $this->assertNotSame($beforeApproval, $afterApproval);
    }

    public function test_the_pdf_preview_route_is_protected_and_serves_the_pdf_inline(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $url = route('requests.attestation.preview', ['request' => $request->id]);

        $this->get($url)
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="Attestation - ATT-2026-001.pdf"');

        auth()->logout();

        $this->get($url)->assertRedirect();
    }

    public function test_the_pdf_preview_returns_404_when_no_pdf_exists(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->actingAsPanelUser($agent);

        $request = $this->createRequest();

        $this->get(route('requests.attestation.preview', ['request' => $request->id]))
            ->assertNotFound();
    }

    public function test_approving_from_the_page_chains_into_the_email_form_with_the_signed_pdf(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);

        $component = Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->callAction('approve')
            ->assertHasNoActionErrors()
            ->assertActionMounted('send_email');

        // Le PDF signé doit être le seul document présélectionné dans l'envoi
        $signedPdf = $request->fresh()->latestGeneratedDocument('pdf');

        $component->assertActionDataSet(['document_ids' => [$signedPdf->id]]);
    }

    public function test_an_attestation_can_be_validated_without_opening_the_email_form(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);

        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->callAction('approve', data: ['open_email_form' => false])
            ->assertHasNoActionErrors()
            ->assertActionNotMounted('send_email');

        // Validée et signée, l'attestation reste envoyable plus tard.
        $this->assertTrue($request->fresh()->isValidated());
    }

    public function test_sending_later_preselects_only_the_signed_pdf(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);
        $service->approve($request->fresh(), $supervisor);

        // Nouvelle visite de la page : l'identifiant du PDF signé mémorisé à la
        // validation n'existe plus.
        $this->actingAsPanelUser($supervisor);
        $signedPdf = $request->fresh()->latestGeneratedDocument('pdf');

        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->mountAction('send_email')
            ->assertActionDataSet(['document_ids' => [$signedPdf->id]]);
    }

    public function test_the_validation_history_shows_when_the_attestation_was_emailed(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        // Un envoi de la version non signée, avant validation, ne compte pas.
        $pdfId = $request->fresh()->latestGeneratedDocument('pdf')->id;
        EmailLog::create([
            'subject' => 'Avant validation', 'message' => '-', 'recipients' => ['avant@example.test'],
            'document_ids' => [$pdfId], 'sent_by' => 'Agent', 'recipients_count' => 1, 'success' => true,
        ]);

        $this->travel(1)->minute();
        $service->approve($request->fresh(), $supervisor);
        $this->assertNull($request->fresh()->lastAttestationEmail());

        $this->travel(1)->minute();
        EmailLog::create([
            'subject' => 'Attestation', 'message' => '-', 'recipients' => ['notaire@example.test'],
            'document_ids' => [(string) $pdfId], 'sent_by' => 'Superviseur', 'recipients_count' => 1, 'success' => true,
        ]);

        $email = $request->fresh()->lastAttestationEmail();

        $this->assertNotNull($email);
        $this->assertSame(['notaire@example.test'], $email->recipients);

        $this->actingAsPanelUser($supervisor);
        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->assertSee('Envoyée par email')
            ->assertSee('notaire@example.test');
    }

    public function test_the_email_form_copies_the_urbanism_mailbox_by_default(): void
    {
        $this->actingAsPanelUser($this->createUser('agent@example.test'));
        $request = $this->createRequest();

        $this->assertContains(
            SendEmailFromRequestAction::DEFAULT_EXTRA_EMAIL,
            SendEmailFromRequestAction::defaultFormData($request)['manual_emails'],
        );
    }

    public function test_the_email_form_stays_open_without_any_recipient(): void
    {
        Mail::fake();

        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);
        $service->approve($request->fresh(), $supervisor);

        $this->actingAsPanelUser($supervisor);

        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->mountAction('send_email')
            ->setActionData(['recipient_keys' => [], 'manual_emails' => []])
            ->callMountedAction()
            ->assertHasActionErrors(['recipient_keys' => 'required_without'])
            ->assertActionMounted('send_email');

        Mail::assertNotSent(DocumentEmail::class);
    }

    public function test_rejecting_from_the_page_requires_a_reason(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);

        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->callAction('reject', data: ['rejection_reason' => ''])
            ->assertHasActionErrors(['rejection_reason']);

        $this->assertSame(ValidationStatus::Pending, $request->fresh()->validation_status);
    }

    public function test_a_signature_in_a_format_word_cannot_display_is_reported_to_the_supervisor(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $request->signatory->update(['signature_path' => $this->createWebpSignatureImage()]);

        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        try {
            $service->approve($request->fresh(), $supervisor);
            $this->fail('Une signature au format WebP doit interrompre la validation.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('PNG ou JPEG', $e->getMessage());
        }

        $this->assertTrue(
            $request->fresh()->isAwaitingValidation(),
            'Une signature illisible doit laisser la demande en attente, pas la valider à moitié.'
        );
    }

    public function test_the_water_and_wastewater_statuses_are_not_swapped_in_the_attestation(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->actingAsPanelUser($agent);
        $template = $this->createDefaultTemplate();

        $mapping = $template->getFullMapping();

        // ${statut.adduction} précède « au réseau public d'Adduction d'Eau
        // Potable » dans le modèle, ${statut.reseauPublic} précède « d'Eaux Usées ».
        $this->assertSame('water_status_text', $mapping['statut.adduction']);
        $this->assertSame('wastewater_status_text', $mapping['statut.reseauPublic']);
    }

    public function test_the_signature_variable_is_not_reported_as_unmapped(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->actingAsPanelUser($agent);
        $template = $this->createDefaultTemplate();

        $template->update(['variables' => ['reference', 'signature', 'signataire.signature']]);

        $this->assertSame([], $template->getUnmappedVariables());
    }

    public function test_the_validation_page_sees_the_signatory_signature(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);

        // La page lit la demande via la requête du resource, qui ne préchargeait
        // du signataire que l'identifiant et le nom : hasSignature() répondait
        // « non » et l'écran annonçait une absence de signature alors qu'elle
        // était bien apposée sur le document.
        $record = Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->instance()
            ->getRecord();

        $this->assertTrue(
            $record->signatory->hasSignature(),
            'La page de validation doit voir l\'image de signature du signataire.'
        );
        $this->assertNotNull($record->signatory->title);
    }

    public function test_the_letter_date_freezes_once_the_attestation_is_validated(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);
        $service->approve($request->fresh(), $supervisor);

        $validatedAt = $request->fresh()->validated_at;

        // Une attestation régénérée des jours plus tard doit garder la date à
        // laquelle elle a été signée, pas celle du jour.
        $this->travel(10)->days();

        $mapping = (new \ReflectionClass(GenerateWordAction::class))->getMethod('buildDataMapping');
        $mapping->setAccessible(true);

        $this->assertSame(
            $validatedAt->format('d/m/Y'),
            $mapping->invoke(null, $request->fresh())['edition_date'],
        );
        $this->assertNotSame(now()->format('d/m/Y'), $mapping->invoke(null, $request->fresh())['edition_date']);
    }

    public function test_regenerating_a_validated_attestation_keeps_the_signature(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);
        $service->approve($request->fresh(), $supervisor);

        // Un agent régénère l'attestation depuis la fiche après validation
        $this->actingAsPanelUser($agent);
        GenerateWordAction::generate($request->fresh());

        $this->assertTrue(
            $this->documentContainsImage($request->fresh()->latestGeneratedDocument('docx')),
            'Une régénération après validation ne doit pas faire disparaître la signature.'
        );
    }

    public function test_a_failed_pdf_conversion_leaves_the_attestation_awaiting_validation_and_unsigned(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);
        $this->failPdfConversion();

        try {
            $service->approve($request->fresh(), $supervisor);
            $this->fail("L'échec de la conversion PDF doit interrompre la validation.");
        } catch (PdfConversionException) {
        }

        $request->refresh();

        $this->assertSame(ValidationStatus::Pending, $request->validation_status);
        $this->assertNull($request->validated_at);
        $this->assertNull($request->validated_by);
        $this->assertFalse(
            $this->documentContainsImage($request->latestGeneratedDocument('docx')),
            'Aucune attestation signée ne doit subsister pour une demande dont la validation a échoué.'
        );
    }

    public function test_approving_without_a_default_template_leaves_the_attestation_awaiting_validation(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        $service = app(AttestationValidationService::class);
        $service->sendForValidation($request, $agent);

        DocumentTemplate::query()->delete();
        $this->actingAsPanelUser($supervisor);

        try {
            $service->approve($request->fresh(), $supervisor);
            $this->fail('Sans modèle, la validation doit échouer.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(ValidationStatus::Pending, $request->fresh()->validation_status);
    }

    public function test_a_failed_approval_from_the_page_keeps_the_validation_actions(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        $this->actingAsPanelUser($supervisor);
        $this->failPdfConversion();

        Livewire::test(ValidateRequest::class, ['record' => $request->id])
            ->callAction('approve')
            ->assertNotified('Validation impossible')
            ->assertActionVisible('approve')
            ->assertActionVisible('reject')
            ->assertActionHidden('send_email');
    }

    public function test_editing_a_validated_attestation_cancels_its_validation(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $request = $this->createValidatedRequest($agent, $supervisor);

        $this->actingAsPanelUser($agent);

        Livewire::test(EditRequest::class, ['record' => $request->getRouteKey()])
            ->fillForm(['water_status' => true])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Validation annulée');

        $request->refresh();

        $this->assertNull($request->validation_status);
        $this->assertNull($request->validated_by);
        $this->assertTrue($request->canBeSentForValidation());

        GenerateWordAction::generate($request);

        $this->assertFalse(
            $this->documentContainsImage($request->fresh()->latestGeneratedDocument('docx')),
            'Une attestation modifiée après validation ne doit plus être signée.'
        );
    }

    public function test_editing_an_attestation_awaiting_validation_cancels_the_pending_validation(): void
    {
        $agent = $this->createUser('agent@example.test');
        $this->createUser('superviseur@example.test', isSupervisor: true);
        $this->actingAsPanelUser($agent);
        $this->createDefaultTemplate();

        $request = $this->createRequest();
        app(AttestationValidationService::class)->sendForValidation($request, $agent);

        Livewire::test(EditRequest::class, ['record' => $request->getRouteKey()])
            ->fillForm(['observations' => 'Branchement assainissement à confirmer.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($request->fresh()->validation_status);
    }

    public function test_saving_a_validated_attestation_without_changes_keeps_its_validation(): void
    {
        $agent = $this->createUser('agent@example.test');
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true);
        $request = $this->createValidatedRequest($agent, $supervisor);

        $this->actingAsPanelUser($agent);

        Livewire::test(EditRequest::class, ['record' => $request->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotNotified('Validation annulée');

        $this->assertSame(ValidationStatus::Approved, $request->fresh()->validation_status);
    }

    public function test_a_supervisor_who_is_not_an_administrator_only_accesses_requests(): void
    {
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true, isAdmin: false);
        $this->actingAsPanelUser($supervisor);

        $this->assertTrue(RequestResource::canAccess());

        foreach ([
            AgentResource::class,
            ApplicantResource::class,
            ContactResource::class,
            MunicipalityResource::class,
            ParcelResource::class,
            RoadResource::class,
            UserResource::class,
            ManageTemplates::class,
        ] as $administratorOnly) {
            $this->assertFalse($administratorOnly::canAccess(), "{$administratorOnly} doit être réservé aux administrateurs.");
        }
    }

    public function test_a_supervisor_who_is_not_an_administrator_cannot_edit_user_accounts(): void
    {
        $supervisor = $this->createUser('superviseur@example.test', isSupervisor: true, isAdmin: false);
        $otherUser = $this->createUser('agent@example.test', isAdmin: false);
        $this->actingAsPanelUser($supervisor);

        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(UserResource::getUrl('edit', ['record' => $otherUser]))->assertForbidden();

        $this->assertFalse($otherUser->fresh()->is_admin);
    }

    public function test_an_administrator_keeps_access_to_every_resource(): void
    {
        $administrator = $this->createUser('admin@example.test');
        $this->actingAsPanelUser($administrator);

        foreach ([
            RequestResource::class,
            AgentResource::class,
            UserResource::class,
            ManageTemplates::class,
        ] as $resource) {
            $this->assertTrue($resource::canAccess(), "{$resource} doit rester accessible aux administrateurs.");
        }
    }
}
