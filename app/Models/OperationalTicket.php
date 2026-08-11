<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalTicket extends Model
{
    public const SOURCES = ['manual', 'email', 'whatsapp', 'meeting'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const STATUSES = [
        'inbox', 'triage', 'ready', 'implementing', 'needs_attention',
        'testing', 'ready_to_report', 'reported', 'hours_pending', 'done',
    ];

    protected $fillable = [
        'project_name', 'source', 'requester', 'title', 'original_text', 'objective',
        'priority', 'status', 'due_date', 'orchestrator_task_id', 'report_message',
        'reported_at', 'hours_estimate', 'hours_notes', 'hours_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'reported_at' => 'datetime',
            'hours_estimate' => 'decimal:2',
            'hours_recorded_at' => 'datetime',
        ];
    }

    public static function sourceLabel(string $source): string
    {
        return ['manual' => 'Manual', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'meeting' => 'Reunión'][$source] ?? $source;
    }

    public static function priorityLabel(string $priority): string
    {
        return ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'][$priority] ?? $priority;
    }

    public static function statusLabel(string $status): string
    {
        return [
            'inbox' => 'Bandeja de entrada', 'triage' => 'En triage', 'ready' => 'Lista',
            'implementing' => 'En implementación', 'needs_attention' => 'Requiere atención',
            'testing' => 'En pruebas', 'ready_to_report' => 'Lista para informar',
            'reported' => 'Informada', 'hours_pending' => 'Horas pendientes', 'done' => 'Hecha',
        ][$status] ?? $status;
    }

    public function nextOperationalStep(): string
    {
        return match ($this->status) {
            'inbox' => 'Hacé triage: confirmá contexto, urgencia y responsable.',
            'triage' => 'Definí objetivo, prioridad y si puede pasar a implementación.',
            'ready' => 'Convertí el pedido refinado en una tarea de ejecución cuando corresponda.',
            'implementing' => 'SeguÍ el avance y registrá bloqueos o cambios de alcance.',
            'needs_attention' => 'Resolvé el bloqueo o pedí la definición que falta.',
            'testing' => 'Validá el resultado antes de preparar el informe.',
            'ready_to_report' => 'Prepará el informe y envialo manualmente a la persona solicitante.',
            'reported' => 'Registrá las horas manualmente para cerrar el ticket.',
            'hours_pending' => 'Registrá las horas manualmente para cerrar el ticket.',
            'done' => 'Ticket cerrado.',
        };
    }

    public function supportsReportingAndHours(): bool
    {
        return in_array($this->status, ['ready_to_report', 'reported', 'hours_pending', 'done'], true);
    }

    public function defaultReportMessage(): string
    {
        $lines = [
            'Hola'.($this->requester ? " {$this->requester}" : '').',',
            '',
            "Quedó completado el pedido \"{$this->title}\" para {$this->project_name}.",
            'Objetivo: '.($this->objective ?: 'sin objetivo operativo registrado.'),
        ];

        if ($this->orchestratorTask !== null) {
            $lines[] = "Tarea de ejecución vinculada: #{$this->orchestratorTask->id} ({$this->orchestratorTask->title}).";
            $lines[] = 'Decisión de revisión: '.($this->orchestratorTask->review_decision ?: 'sin decisión registrada').'.';
        }

        $lines[] = '';
        $lines[] = 'Quedo atento/a a cualquier ajuste.';

        return implode("\n", $lines);
    }

    public function orchestratorTask(): BelongsTo
    {
        return $this->belongsTo(OrchestratorTask::class);
    }
}
