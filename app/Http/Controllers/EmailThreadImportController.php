<?php

namespace App\Http\Controllers;

use App\Models\EmailThreadImport;
use App\Models\OperationalTicket;
use App\Services\Gmail\ThreadTicketDraftGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailThreadImportController extends Controller
{
    public function create(ThreadTicketDraftGenerator $generator)
    {
        return view('email-thread-imports.create', ['draftProvider' => $generator->providerLabel()]);
    }

    public function store(Request $request, ThreadTicketDraftGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'participants' => ['nullable', 'string', 'max:2000'],
            'raw_thread_text' => ['required', 'string'],
        ]);
        $participants = collect(explode(',', $data['participants'] ?? ''))
            ->map(fn (string $participant) => trim($participant))
            ->filter()
            ->values()
            ->all();
        $draft = $generator->generate($data['subject'], $participants, $data['raw_thread_text']);
        $import = EmailThreadImport::create([
            'provider' => 'gmail',
            'subject' => $data['subject'],
            'participants' => $participants ?: null,
            'raw_thread_text' => $data['raw_thread_text'],
            'draft_generator' => $generator->providerLabel(),
            'ai_summary' => $draft['summary'],
            'ai_expectations' => $draft['functional_expectations'],
            'ai_questions' => $draft['open_questions'],
            'proposed_ticket_payload' => $draft,
        ]);

        return redirect()->route('email-thread-imports.show', $import);
    }

    public function show(EmailThreadImport $emailThreadImport)
    {
        return view('email-thread-imports.show', ['import' => $emailThreadImport->load('operationalTicket')]);
    }

    public function createTicket(EmailThreadImport $emailThreadImport): RedirectResponse
    {
        return DB::transaction(function () use ($emailThreadImport) {
            $import = EmailThreadImport::query()->lockForUpdate()->findOrFail($emailThreadImport->id);

            if ($import->operational_ticket_id !== null) {
                return redirect()->route('operational-tickets.show', $import->operational_ticket_id)
                    ->with('success', 'Este borrador ya tiene un ticket operativo vinculado.');
            }

            $payload = $import->proposed_ticket_payload;
            $expectations = implode("\n", array_map(fn (string $item) => "- {$item}", $payload['functional_expectations'] ?? []));
            $questions = implode("\n", array_map(fn (string $item) => "- {$item}", $payload['open_questions'] ?? []));
            $ticket = OperationalTicket::create([
                'project_name' => $payload['project_name'] ?: 'Sin clasificar',
                'source' => 'email',
                'requester' => $payload['requester'] ?? null,
                'title' => $payload['title'],
                'original_text' => $payload['original_text'],
                'objective' => $payload['objective']."\n\nExpectativas funcionales:\n{$expectations}\n\nPreguntas abiertas:\n{$questions}",
                'priority' => $payload['priority'],
                'status' => 'triage',
            ]);
            $import->update(['operational_ticket_id' => $ticket->id, 'status' => 'ticket_created']);

            return redirect()->route('operational-tickets.show', $ticket)
                ->with('success', "El borrador revisado creó el ticket operativo #{$ticket->id} en triage.");
        });
    }
}
