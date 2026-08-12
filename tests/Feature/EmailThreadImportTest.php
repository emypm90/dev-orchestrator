<?php

namespace Tests\Feature;

use App\Models\EmailThreadImport;
use App\Models\OperationalTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailThreadImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config()->set('services.openai.ticket_draft.key', null);
        config()->set('services.openai.ticket_draft.model', 'gpt-5.5');
        config()->set('services.openai.ticket_draft.enabled', true);

        parent::tearDown();
    }

    public function test_pasted_thread_draft_form_renders(): void
    {
        $this->get(route('email-thread-imports.create'))
            ->assertOk()
            ->assertSee('Creá un borrador desde una cadena pegada')
            ->assertSee('name="raw_thread_text"', false)
            ->assertSee('Generador local determinista');
    }

    public function test_submitting_a_pasted_thread_creates_a_draft_with_ticket_payload(): void
    {
        $response = $this->post(route('email-thread-imports.store'), $this->threadPayload());

        $import = EmailThreadImport::firstOrFail();
        $response->assertRedirect(route('email-thread-imports.show', $import));
        $this->assertDatabaseHas('email_thread_imports', [
            'id' => $import->id,
            'provider' => 'gmail',
            'subject' => 'Corregir formulario de contacto',
            'status' => 'draft',
        ]);
        $this->assertSame(['Lucía <lucia@example.com>', 'Equipo web'], $import->participants);
        $this->assertSame('Generador local determinista', $import->draft_generator);
        $this->assertSame('Corregir formulario de contacto', $import->proposed_ticket_payload['title']);
        $this->assertContains('El formulario debe mostrar el campo Empresa y guardar el dato.', $import->ai_expectations);
    }

    public function test_review_page_shows_summary_expectations_and_questions(): void
    {
        $this->post(route('email-thread-imports.store'), $this->threadPayload());
        $import = EmailThreadImport::firstOrFail();

        $this->get(route('email-thread-imports.show', $import))
            ->assertOk()
            ->assertSee('Resumen AI')
            ->assertSee('Expectativas funcionales')
            ->assertSee('Preguntas abiertas')
            ->assertSee('Generador local determinista')
            ->assertSee('El formulario debe mostrar el campo Empresa y guardar el dato.')
            ->assertSee('¿También debe ser obligatorio para todos los países?');
    }

    public function test_creating_ticket_creates_one_triage_ticket_with_expectations_in_objective(): void
    {
        $this->post(route('email-thread-imports.store'), $this->threadPayload());
        $import = EmailThreadImport::firstOrFail();

        $response = $this->post(route('email-thread-imports.create-ticket', $import));

        $ticket = OperationalTicket::firstOrFail();
        $response->assertRedirect(route('operational-tickets.show', $ticket));
        $this->assertDatabaseHas('operational_tickets', [
            'id' => $ticket->id,
            'source' => 'email',
            'status' => 'triage',
            'title' => 'Corregir formulario de contacto',
        ]);
        $this->assertStringContainsString('Expectativas funcionales:', $ticket->objective);
        $this->assertStringContainsString('El formulario debe mostrar el campo Empresa y guardar el dato.', $ticket->objective);
        $this->assertDatabaseHas('email_thread_imports', [
            'id' => $import->id,
            'operational_ticket_id' => $ticket->id,
            'status' => 'ticket_created',
        ]);
    }

    public function test_repeated_ticket_creation_does_not_duplicate_ticket(): void
    {
        $this->post(route('email-thread-imports.store'), $this->threadPayload());
        $import = EmailThreadImport::firstOrFail();

        $this->post(route('email-thread-imports.create-ticket', $import));
        $ticket = OperationalTicket::firstOrFail();
        $this->post(route('email-thread-imports.create-ticket', $import))
            ->assertRedirect(route('operational-tickets.show', $ticket));

        $this->assertDatabaseCount('operational_tickets', 1);
    }

    public function test_without_an_openai_key_the_deterministic_generator_is_used(): void
    {
        Http::fake();

        $this->post(route('email-thread-imports.store'), $this->threadPayload());

        $import = EmailThreadImport::firstOrFail();
        $this->assertSame('Corregir formulario de contacto', $import->proposed_ticket_payload['title']);
        $this->assertSame('normal', $import->proposed_ticket_payload['priority']);
        Http::assertNothingSent();
    }

    public function test_with_an_openai_key_the_structured_draft_is_stored(): void
    {
        $this->configureOpenAi();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [['content' => [['text' => json_encode($this->openAiDraft())]]]],
            ]),
        ]);

        $this->post(route('email-thread-imports.store'), $this->threadPayload());

        $import = EmailThreadImport::firstOrFail();
        $this->assertSame('Actualizar formulario para campaña', $import->proposed_ticket_payload['title']);
        $this->assertSame('high', $import->proposed_ticket_payload['priority']);
        $this->assertSame(['Guardar Empresa', 'Mostrar Empresa'], $import->ai_expectations);
        $this->assertSame('OpenAI (gpt-5.5)', $import->draft_generator);
        $this->assertSame($this->threadPayload()['raw_thread_text'], $import->proposed_ticket_payload['original_text']);
    }

    public function test_openai_failure_or_invalid_json_falls_back_without_blocking_the_draft(): void
    {
        $this->configureOpenAi();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['text' => '{invalid']]]]])]);

        $this->post(route('email-thread-imports.store'), $this->threadPayload());

        $import = EmailThreadImport::firstOrFail();
        $this->assertSame('Corregir formulario de contacto', $import->proposed_ticket_payload['title']);
        $this->assertSame('normal', $import->proposed_ticket_payload['priority']);
    }

    public function test_openai_request_uses_configured_model_and_requires_operational_fields(): void
    {
        $this->configureOpenAi('gpt-5.5');
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([], 500)]);

        $this->post(route('email-thread-imports.store'), $this->threadPayload());

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['model'] === 'gpt-5.5'
                && str_contains($request['input'], 'functional_expectations')
                && str_contains($request['input'], 'open_questions')
                && $request['text']['format']['type'] === 'json_schema';
        });

        $import = EmailThreadImport::firstOrFail();
        $this->assertSame('Corregir formulario de contacto', $import->proposed_ticket_payload['title']);
    }

    private function configureOpenAi(string $model = 'gpt-5.5'): void
    {
        config()->set('services.openai.ticket_draft.key', 'test-openai-key');
        config()->set('services.openai.ticket_draft.model', $model);
        config()->set('services.openai.ticket_draft.enabled', true);
    }

    private function openAiDraft(): array
    {
        return [
            'project_name' => 'Sitio web',
            'requester' => 'Lucía <lucia@example.com>',
            'title' => 'Actualizar formulario para campaña',
            'original_text' => 'No conservar este texto.',
            'context' => 'La campaña requiere el campo Empresa.',
            'objective' => 'Actualizar el formulario de contacto.',
            'priority' => 'high',
            'summary' => 'Agregar el campo Empresa antes de la campaña.',
            'functional_expectations' => ['Guardar Empresa', 'Mostrar Empresa'],
            'open_questions' => ['Confirmar si Empresa es obligatorio.'],
        ];
    }

    private function threadPayload(): array
    {
        return [
            'subject' => 'Corregir formulario de contacto',
            'participants' => 'Lucía <lucia@example.com>, Equipo web',
            'raw_thread_text' => "Lucía: Necesitamos actualizar el formulario antes de la campaña.\nEl formulario debe mostrar el campo Empresa y guardar el dato.\n¿También debe ser obligatorio para todos los países?",
        ];
    }
}
