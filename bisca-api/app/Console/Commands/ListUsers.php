<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:list {--players-only} {--with-coins}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all users with their coin balances';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $query = User::query();

        if ($this->option('players-only')) {
            $query->where('type', 'P');
        }

        if ($this->option('with-coins')) {
            $query->where('coins_balance', '>', 0);
        }

        $users = $query->orderBy('coins_balance', 'desc')->get();

        if ($users->isEmpty()) {
            $this->info('No users found');
            return 0;
        }

        $this->info('📋 Users List:');
        $this->newLine();

        $headers = ['ID', 'Name', 'Email', 'Type', 'Coins', 'Status'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->type === 'P' ? 'Player' : 'Admin',
                $user->coins_balance,
                $user->blocked ? '🚫 Blocked' : '✅ Active'
            ];
        }

        $this->table($headers, $rows);

        $totalCoins = $users->sum('coins_balance');
        $playerCount = $users->where('type', 'P')->count();
        $adminCount = $users->where('type', 'A')->count();

        $this->newLine();
        $this->info("📊 Summary:");
        $this->info("   Players: {$playerCount}");
        $this->info("   Admins: {$adminCount}");
        $this->info("   Total Coins: {$totalCoins}");

        return 0;
    }
}
