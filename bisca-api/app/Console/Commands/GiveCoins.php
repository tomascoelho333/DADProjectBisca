<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\CoinTransaction;

class GiveCoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coins:give {email} {amount} {--reason=Admin bonus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give coins to a user by email';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');
        $amount = (int) $this->argument('amount');
        $reason = $this->option('reason');

        if ($amount <= 0) {
            $this->error('Amount must be a positive number');
            return 1;
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User with email '{$email}' not found");
            return 1;
        }

        if ($user->type !== 'P') {
            $this->error('Cannot give coins to administrators');
            return 1;
        }

        $oldBalance = $user->coins_balance;
        $user->coins_balance += $amount;
        $user->save();

        // Create coin transaction
        CoinTransaction::create([
            'transaction_datetime' => now(),
            'user_id' => $user->id,
            'game_id' => null,
            'coin_transaction_type_id' => 1, // Bonus
            'coins' => $amount,
            'custom' => json_encode([
                'description' => $reason,
                'admin_action' => true,
                'previous_balance' => $oldBalance,
                'new_balance' => $user->coins_balance
            ])
        ]);

        $this->info("✅ Successfully gave {$amount} coins to {$user->name} ({$email})");
        $this->info("💰 Balance: {$oldBalance} → {$user->coins_balance}");
        $this->info("📝 Reason: {$reason}");

        return 0;
    }
}
