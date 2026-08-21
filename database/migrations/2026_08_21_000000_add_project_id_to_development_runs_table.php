<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('development_runs', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->after('project')
                ->constrained('orchestrator_projects')
                ->nullOnDelete();
        });

        DB::table('development_runs')
            ->whereNull('project_id')
            ->orderBy('id')
            ->get(['id', 'project', 'repository'])
            ->each(function (object $run): void {
                $project = null;

                if (filled($run->project)) {
                    $project = DB::table('orchestrator_projects')->where('name', $run->project)->first(['id']);
                }

                if (! $project && filled($run->repository)) {
                    $project = DB::table('orchestrator_projects')->where('repo_path', $run->repository)->first(['id']);
                }

                if ($project) {
                    DB::table('development_runs')->where('id', $run->id)->update(['project_id' => $project->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('development_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
