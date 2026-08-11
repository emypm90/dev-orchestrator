<?php

namespace App\Http\Controllers;

use App\Models\OperationalTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationalTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = OperationalTicket::query()->orderByDesc('updated_at');

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($source = $request->string('source')->trim()->toString()) {
            $query->where('source', $source);
        }

        if ($request->boolean('attention')) {
            $query->where(function (Builder $attentionQuery): void {
                $attentionQuery->where('status', 'needs_attention')
                    ->orWhere('priority', 'urgent')
                    ->orWhere(fn (Builder $dueQuery) => $dueQuery->whereNotNull('due_date')->whereDate('due_date', '<=', today()));
            });
        }

        $tickets = $query->get();

        return view('operational-tickets.index', [
            'tickets' => $tickets,
            'statusCounts' => $tickets->countBy('status')->sortKeys(),
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
        return view('operational-tickets.show', ['ticket' => $operationalTicket]);
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
}
