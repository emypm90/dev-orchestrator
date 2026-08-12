<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailThreadImport extends Model
{
    public const STATUSES = ['draft', 'ticket_created', 'discarded', 'error'];

    protected $fillable = [
        'email_account_id', 'provider', 'external_thread_id', 'subject', 'participants',
        'raw_thread_text', 'draft_generator', 'ai_summary', 'ai_expectations', 'ai_questions',
        'proposed_ticket_payload', 'operational_ticket_id', 'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
            'ai_expectations' => 'array',
            'ai_questions' => 'array',
            'proposed_ticket_payload' => 'array',
        ];
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function operationalTicket(): BelongsTo
    {
        return $this->belongsTo(OperationalTicket::class);
    }
}
