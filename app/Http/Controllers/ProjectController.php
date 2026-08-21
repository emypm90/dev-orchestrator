<?php

namespace App\Http\Controllers;

use App\Models\OrchestratorProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        return view('projects.index', [
            'projects' => OrchestratorProject::query()->withCount('developmentRuns')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:orchestrator_projects,name'],
            'repo_path' => ['required', 'string', 'max:1024', 'unique:orchestrator_projects,repo_path'],
            'rules' => ['nullable', 'string'],
        ]);

        $project = OrchestratorProject::create($data);

        return redirect()->route('projects.show', $project);
    }

    public function edit(OrchestratorProject $project)
    {
        return view('projects.edit', ['project' => $project]);
    }

    public function update(Request $request, OrchestratorProject $project): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('orchestrator_projects', 'name')->ignore($project)],
            'repo_path' => ['required', 'string', 'max:1024', Rule::unique('orchestrator_projects', 'repo_path')->ignore($project)],
            'rules' => ['nullable', 'string'],
        ]);

        $project->update($data);

        return redirect()->route('projects.show', $project);
    }

    public function show(OrchestratorProject $project)
    {
        return view('projects.show', [
            'project' => $project->load([
                'contextAttachments' => fn ($query) => $query->latest(),
                'developmentRuns' => fn ($query) => $query->latest('started_at'),
            ]),
        ]);
    }
}
