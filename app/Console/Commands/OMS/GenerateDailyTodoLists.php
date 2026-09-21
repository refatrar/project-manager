<?php

namespace App\Console\Commands\OMS;

use App\Actions\OMS\GenerateDailyTodoList;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateDailyTodoLists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oms:generate-daily-todo-lists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Generate each team member's daily list of open tasks due this week, so it exists before they open the page (FR-5.6)";

    /**
     * Execute the console command.
     */
    public function handle(GenerateDailyTodoList $generateDailyTodoList): int
    {
        $today = Carbon::now();
        $generated = 0;

        Team::query()->chunkById(50, function ($teams) use ($generateDailyTodoList, $today, &$generated): void {
            foreach ($teams as $team) {
                $team->members()->get(['users.id'])->each(function (User $user) use ($team, $generateDailyTodoList, $today, &$generated): void {
                    $generateDailyTodoList->handle($user, $team, $today);
                    $generated++;
                });
            }
        });

        $this->info("Generated {$generated} daily to-do list(s).");

        return self::SUCCESS;
    }
}
