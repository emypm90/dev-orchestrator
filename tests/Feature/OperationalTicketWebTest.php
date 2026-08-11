<?php

namespace Tests\Feature;

use App\Models\OperationalTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalTicketWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_board_renders_persisted_ticket_data(): void
    {
        $ticket = $this->ticket();

        $this->get(route('operational-tickets.index'))
            ->assertOk()
            ->assertSee('Tickets operativos')
            ->assertSee($ticket->title)
            ->assertSee($ticket->project_name)
            ->assertSee('WhatsApp')
            ->assertSee('Alta')
            ->assertSee('Requiere atención');
    }

    public function test_create_ticket_form_renders(): void
    {
        $this->get(route('operational-tickets.create'))
            ->assertOk()
            ->assertSee('Cargá el pedido tal como llegó')
            ->assertSee('name="original_text"', false)
            ->assertSee(route('operational-tickets.store'));
    }

    public function test_valid_manual_ticket_is_stored_and_redirects_to_detail(): void
    {
        $response = $this->post(route('operational-tickets.store'), [
            'project_name' => 'cliente-web',
            'source' => 'manual',
            'requester' => 'Lucía',
            'title' => 'Actualizar formulario de contacto',
            'original_text' => 'Lucía pidió agregar el campo empresa al formulario.',
            'objective' => 'Tener el campo disponible antes de la campaña.',
            'priority' => 'high',
            'status' => 'inbox',
            'due_date' => '2026-08-20',
        ]);

        $ticket = OperationalTicket::firstOrFail();
        $response->assertRedirect(route('operational-tickets.show', $ticket));
        $this->assertDatabaseHas('operational_tickets', [
            'project_name' => 'cliente-web',
            'source' => 'manual',
            'requester' => 'Lucía',
            'title' => 'Actualizar formulario de contacto',
            'original_text' => 'Lucía pidió agregar el campo empresa al formulario.',
            'priority' => 'high',
            'status' => 'inbox',
            'due_date' => '2026-08-20 00:00:00',
        ]);
    }

    public function test_ticket_validation_rejects_missing_required_fields(): void
    {
        $this->from(route('operational-tickets.create'))
            ->post(route('operational-tickets.store'), [])
            ->assertRedirect(route('operational-tickets.create'))
            ->assertSessionHasErrors(['project_name', 'source', 'title', 'original_text', 'priority', 'status']);

        $this->assertDatabaseCount('operational_tickets', 0);
    }

    public function test_ticket_detail_renders_persisted_context_and_next_step(): void
    {
        $ticket = $this->ticket(['status' => 'triage']);

        $this->get(route('operational-tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->title)
            ->assertSee($ticket->original_text)
            ->assertSee('Próximo paso operativo')
            ->assertSee('Definí objetivo, prioridad y si puede pasar a implementación.');
    }

    private function ticket(array $attributes = []): OperationalTicket
    {
        return OperationalTicket::create(array_merge([
            'project_name' => 'sitio-cliente',
            'source' => 'whatsapp',
            'requester' => 'Marina',
            'title' => 'Corregir pie de página',
            'original_text' => 'Marina avisó que el enlace legal está roto.',
            'priority' => 'high',
            'status' => 'needs_attention',
            'due_date' => '2026-08-12',
        ], $attributes));
    }
}
