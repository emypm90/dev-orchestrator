<?php

namespace Tests\Feature;

use App\Models\EmailThreadImport;
use App\Models\OperationalTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailThreadImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasted_thread_draft_form_renders(): void
    {
        $this->get(route('email-thread-imports.create'))
            ->assertOk()
            ->assertSee('Creá un borrador desde una cadena pegada')
            ->assertSee('name="raw_thread_text"', false)
            ->assertSee('ni se llama a un proveedor de IA');
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

    private function threadPayload(): array
    {
        return [
            'subject' => 'Corregir formulario de contacto',
            'participants' => 'Lucía <lucia@example.com>, Equipo web',
            'raw_thread_text' => "Lucía: Necesitamos actualizar el formulario antes de la campaña.\nEl formulario debe mostrar el campo Empresa y guardar el dato.\n¿También debe ser obligatorio para todos los países?",
        ];
    }
}
