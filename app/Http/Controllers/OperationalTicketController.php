<?php

namespace App\Http\Controllers;

use App\Models\OperationalTicket;
use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\AttentionSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationalTicketController extends Controller
{
    public function index(Request $request, AttentionSummary $attention)
    {
        $query = OperationalTicket::query()->orderByDesc('updated_at');

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($source = $request->string('source')->trim()->toString()) {
            $query->where('source', $source);
        }

        if ($request->boolean('attention')) {
            $query->whereIn('id', $attention->operationalTicketQuery()->select('id'));
        }

        $tickets = $query->get();

        return view('operational-tickets.index', [
            'tickets' => $tickets,
            'statusCounts' => $tickets->countBy('status')->sortKeys(),
            'attentionSummary' => $attention->forDashboard(),
        ]);
    }

    public function create()
    {
        return view('operational-tickets.create');
    }

    public function store(Request $request)
    {
        $ticket = OperationalTicket::create($this->validatedTicket($request));

        return redirect()->route('operational-tickets.show', $ticket)
            ->with('success', "El ticket #{$ticket->id} quedó registrado en la bandeja de entrada.");
    }

    public function show(OperationalTicket $operationalTicket)
    {
        return view('operational-tickets.show', ['ticket' => $operationalTicket->load('orchestratorTask')]);
    }

    public function update(Request $request, OperationalTicket $operationalTicket)
    {
        $operationalTicket->update($this->validatedTicket($request));

        return redirect()->route('operational-tickets.show', $operationalTicket)
            ->with('success', "El ticket #{$operationalTicket->id} quedó actualizado.");
    }

    public function convert(OperationalTicket $operationalTicket)
    {
        return DB::transaction(function () use ($operationalTicket) {
            $ticket = OperationalTicket::query()->lockForUpdate()->findOrFail($operationalTicket->id);

            if ($ticket->orchestrator_task_id !== null) {
                return redirect()->route('tasks.show', $ticket->orchestrator_task_id)
                    ->with('success', "El ticket #{$ticket->id} ya tiene una tarea de ejecución vinculada.");
            }

            if ($ticket->status !== 'ready') {
                return redirect()->route('operational-tickets.show', $ticket)
                    ->withErrors(['conversion' => 'Primero completá el triage y marcá el ticket como listo antes de crear una tarea de ejecución.']);
            }

            $project = OrchestratorProject::query()->where('name', $ticket->project_name)->first();
            if ($project === null) {
                return redirect()->route('operational-tickets.show', $ticket)
                    ->withErrors(['conversion' => "No hay un proyecto registrado con el nombre \"{$ticket->project_name}\"."]);
            }

            $task = OrchestratorTask::create([
                'project_id' => $project->id,
                'title' => $ticket->title,
                'description' => $this->taskDescription($ticket),
                'autonomy' => 'medium',
            ]);

            $ticket->update([
                'orchestrator_task_id' => $task->id,
                'status' => 'implementing',
            ]);

            return redirect()->route('tasks.show', $task)
                ->with('success', "El ticket #{$ticket->id} ahora se ejecuta como la tarea #{$task->id}.");
        });
    }

    public function updateReport(Request $request, OperationalTicket $operationalTicket)
    {
        $operationalTicket->update($request->validate([
            'report_message' => ['required', 'string'],
        ]));

        return redirect()->route('operational-tickets.show', $operationalTicket)
            ->with('success', 'El mensaje quedó preparado localmente. Enviarlo sigue siendo una acción manual.');
    }

    public function markReported(Request $request, OperationalTicket $operationalTicket)
    {
        $data = $request->validate([
            'report_message' => ['required', 'string'],
        ]);

        $operationalTicket->update([
            'report_message' => $data['report_message'],
            'reported_at' => now(),
            'status' => $operationalTicket->status === 'done' ? 'done' : 'hours_pending',
        ]);

        return redirect()->route('operational-tickets.show', $operationalTicket)
            ->with('success', 'El informe quedó marcado como enviado manualmente. Ahora registrá las horas localmente.');
    }

    public function updateHours(Request $request, OperationalTicket $operationalTicket)
    {
        $operationalTicket->update($this->validatedHours($request));

        return redirect()->route('operational-tickets.show', $operationalTicket)
            ->with('success', 'Las horas quedaron guardadas localmente. No se subieron a ninguna plataforma.');
    }

    public function markHoursRecorded(Request $request, OperationalTicket $operationalTicket)
    {
        $operationalTicket->update(array_merge($this->validatedHours($request), [
            'hours_recorded_at' => now(),
            'status' => 'done',
        ]));

        return redirect()->route('operational-tickets.show', $operationalTicket)
            ->with('success', 'Las horas quedaron marcadas como registradas localmente y el ticket se cerró.');
    }

    private function validatedTicket(Request $request): array
    {
        return $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'source' => ['required', Rule::in(OperationalTicket::SOURCES)],
            'requester' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'original_text' => ['required', 'string'],
            'objective' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(OperationalTicket::PRIORITIES)],
            'status' => ['required', Rule::in(OperationalTicket::STATUSES)],
            'due_date' => ['nullable', 'date'],
        ], [
            'project_name.required' => 'Indicá el proyecto.',
            'title.required' => 'Indicá un título para el pedido.',
            'original_text.required' => 'Pegá el pedido o contexto original.',
        ]);
    }

    private function taskDescription(OperationalTicket $ticket): string
    {
        return implode("\n", [
            "Ticket operativo #{$ticket->id}",
            "Título: {$ticket->title}",
            "Proyecto: {$ticket->project_name}",
            'Solicitante: '.($ticket->requester ?: 'Sin indicar'),
            'Prioridad: '.OperationalTicket::priorityLabel($ticket->priority),
            'Fecha límite: '.($ticket->due_date?->format('Y-m-d') ?: 'Sin fecha'),
            '',
            'Objetivo:',
            $ticket->objective ?: 'Sin definir.',
            '',
            'Contexto original:',
            $ticket->original_text,
        ]);
    }

    private function validatedHours(Request $request): array
    {
        return $request->validate([
            'hours_estimate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'hours_notes' => ['nullable', 'string'],
        ]);
    }
}
