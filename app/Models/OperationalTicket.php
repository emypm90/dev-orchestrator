<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'priority', 'status', 'due_date',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
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
            'ready_to_report' => 'Comunicá el resultado a la persona solicitante.',
            'reported' => 'Registrá las horas pendientes o cerrá el ticket.',
            'hours_pending' => 'Registrá las horas y cerrá el ticket.',
            'done' => 'Ticket cerrado. Conservá este contexto como historial operativo.',
        };
    }
}
