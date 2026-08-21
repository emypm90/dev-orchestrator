<?php

namespace App\Http\Controllers;

use App\Models\OrchestratorProject;
use App\Services\ContextIngestion\ContextAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectContextAttachmentController extends Controller
{
    public function store(Request $request, OrchestratorProject $project, ContextAttachmentService $attachments): RedirectResponse
    {
        $data = $request->validate(ContextAttachmentService::validationRules());

        $attachments->storeUploaded($data['context_attachment'], $project);

        return redirect()->route('projects.show', $project);
    }
}
