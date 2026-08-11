<?php

namespace Tests\Feature;

use App\Models\OperationalTicket;
use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
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
            ->assertSee('Definí objetivo, prioridad y si puede pasar a implementación.')
            ->assertSee('Triage y actualización')
            ->assertSee('Los tickets crudos deben pasar por triage');
    }

    public function test_ticket_triage_update_persists_editable_fields(): void
    {
        $ticket = $this->ticket();

        $this->patch(route('operational-tickets.update', $ticket), [
            'project_name' => 'portal-cliente',
            'source' => 'email',
            'requester' => 'Sofía',
            'title' => 'Actualizar condiciones comerciales',
            'original_text' => 'Sofía envió las condiciones nuevas para publicar.',
            'objective' => 'Publicar las condiciones antes del viernes.',
            'priority' => 'urgent',
            'status' => 'ready',
            'due_date' => '2026-08-21',
        ])->assertRedirect(route('operational-tickets.show', $ticket));

        $this->assertDatabaseHas('operational_tickets', [
            'id' => $ticket->id,
            'project_name' => 'portal-cliente',
            'source' => 'email',
            'requester' => 'Sofía',
            'title' => 'Actualizar condiciones comerciales',
            'original_text' => 'Sofía envió las condiciones nuevas para publicar.',
            'objective' => 'Publicar las condiciones antes del viernes.',
            'priority' => 'urgent',
            'status' => 'ready',
            'due_date' => '2026-08-21 00:00:00',
        ]);
    }

    public function test_ticket_triage_update_rejects_invalid_enums(): void
    {
        $ticket = $this->ticket();

        $this->from(route('operational-tickets.show', $ticket))
            ->patch(route('operational-tickets.update', $ticket), array_merge($this->ticketPayload(), [
                'source' => 'slack',
                'priority' => 'critical',
                'status' => 'queued',
            ]))
            ->assertRedirect(route('operational-tickets.show', $ticket))
            ->assertSessionHasErrors(['source', 'priority', 'status']);

        $this->assertDatabaseHas('operational_tickets', [
            'id' => $ticket->id,
            'source' => 'whatsapp',
            'priority' => 'high',
            'status' => 'needs_attention',
        ]);
    }

    public function test_ticket_conversion_is_blocked_until_it_is_ready(): void
    {
        $ticket = $this->ticket(['status' => 'triage']);

        $this->post(route('operational-tickets.convert', $ticket))
            ->assertRedirect(route('operational-tickets.show', $ticket))
            ->assertSessionHasErrors('conversion');

        $this->assertDatabaseCount('orchestrator_tasks', 0);
        $this->assertDatabaseHas('operational_tickets', ['id' => $ticket->id, 'status' => 'triage', 'orchestrator_task_id' => null]);
    }

    public function test_attention_filter_includes_operational_action_states_and_excludes_closed_items_without_signals(): void
    {
        $this->ticket(['title' => 'Inbox ticket', 'status' => 'inbox', 'priority' => 'normal', 'due_date' => null]);
        $this->ticket(['title' => 'Triage ticket', 'status' => 'triage', 'priority' => 'normal', 'due_date' => null]);
        $this->ticket(['title' => 'Ready ticket', 'status' => 'ready', 'priority' => 'normal', 'due_date' => null]);
        $this->ticket(['title' => 'Needs attention ticket', 'status' => 'needs_attention', 'priority' => 'normal', 'due_date' => null]);
        $this->ticket(['title' => 'Urgent reported ticket', 'status' => 'reported', 'priority' => 'urgent', 'due_date' => null]);
        $this->ticket(['title' => 'Overdue reported ticket', 'status' => 'reported', 'priority' => 'normal', 'due_date' => today()->subDay()->toDateString()]);
        $this->ticket(['title' => 'Done without signal', 'status' => 'done', 'priority' => 'normal', 'due_date' => null]);
        $this->ticket(['title' => 'Reported without signal', 'status' => 'reported', 'priority' => 'normal', 'due_date' => null]);

        $this->get(route('operational-tickets.index', ['attention' => 1]))
            ->assertOk()
            ->assertSee('Inbox ticket')
            ->assertSee('Triage ticket')
            ->assertSee('Ready ticket')
            ->assertSee('Needs attention ticket')
            ->assertSee('Urgent reported ticket')
            ->assertSee('Overdue reported ticket')
            ->assertDontSee('Done without signal')
            ->assertDontSee('Reported without signal')
            ->assertSee(route('operational-tickets.index', ['attention' => 1]));
    }

    public function test_ready_ticket_converts_to_one_linked_execution_task(): void
    {
        $project = $this->project('sitio-cliente');
        $ticket = $this->ticket(['status' => 'ready', 'objective' => 'Restaurar el enlace legal antes del lanzamiento.']);

        $response = $this->post(route('operational-tickets.convert', $ticket));

        $task = OrchestratorTask::firstOrFail();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertDatabaseCount('orchestrator_tasks', 1);
        $this->assertDatabaseHas('orchestrator_tasks', [
            'id' => $task->id,
            'project_id' => $project->id,
            'title' => $ticket->title,
            'status' => 'draft',
            'autonomy' => 'medium',
        ]);
        $this->assertDatabaseHas('operational_tickets', [
            'id' => $ticket->id,
            'orchestrator_task_id' => $task->id,
            'status' => 'implementing',
        ]);
        $this->assertStringContainsString("Título: {$ticket->title}", $task->description);
        $this->assertStringContainsString("Proyecto: {$ticket->project_name}", $task->description);
        $this->assertStringContainsString('Objetivo:', $task->description);
        $this->assertStringContainsString($ticket->original_text, $task->description);
        $this->assertStringContainsString("Solicitante: {$ticket->requester}", $task->description);
        $this->assertStringContainsString('Prioridad: Alta', $task->description);
        $this->assertStringContainsString('Fecha límite: 2026-08-12', $task->description);
    }

    public function test_repeated_ticket_conversion_redirects_to_the_existing_task_without_duplication(): void
    {
        $this->project('sitio-cliente');
        $ticket = $this->ticket(['status' => 'ready']);

        $this->post(route('operational-tickets.convert', $ticket));
        $task = OrchestratorTask::firstOrFail();

        $this->post(route('operational-tickets.convert', $ticket))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertDatabaseCount('orchestrator_tasks', 1);
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

    private function project(string $name): OrchestratorProject
    {
        return OrchestratorProject::create([
            'name' => $name,
            'repo_path' => "C:\\workspace\\{$name}",
            'default_branch' => 'main',
        ]);
    }

    private function ticketPayload(): array
    {
        return [
            'project_name' => 'sitio-cliente',
            'source' => 'whatsapp',
            'requester' => 'Marina',
            'title' => 'Corregir pie de página',
            'original_text' => 'Marina avisó que el enlace legal está roto.',
            'objective' => 'Restaurar el enlace.',
            'priority' => 'high',
            'status' => 'needs_attention',
            'due_date' => '2026-08-12',
        ];
    }
}
