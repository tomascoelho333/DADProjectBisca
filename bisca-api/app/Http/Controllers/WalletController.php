<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class WalletController extends Controller
{
    //Purchase Coins
    public function purchase(Request $request){
        $user = $request->user();

        if($user->type !== 'P'){
            return response(json([
                'message' => 'Only players can purchase coins.'
            ], 403));
        }

        $request -> validate([
            'type' => 'required|in:MBWAY,PAYPAL,IBAN,MB,VISA',
            'reference' => 'required|string',
            'value' => 'required|integer|min:1|max:9999'
        ]);

        $response = Http::withoutVerifying()->post('https://dad-payments-api.vercel.app/api/debit', [
            'type' => $request->type,
            'reference' => $request->reference,
            'value' => $request->value
        ]);

        if($response->status() == 201){
            $coinsToAdd = $request->value * 10;

            DB::transaction(function () use ($user, $request, $coinsToAdd){

                $transactionId = DB::table('coin_transactions')->insertGetId([
                    'transaction_datetime' => now(),
                    'user_id' => $user->id,
                    'coin_transaction_type_id' => 2,
                    'coins' => $coinsToAdd,
                ]);

                DB::table('coin_purchases')->insert([
                    'user_id' => $user->id,
                    'coin_transaction_id' => $transactionId,
                    'euros' => $request->value,
                    'purchase_datetime' => now(),
                    'payment_type' => $request->type,
                    'payment_reference' => $request->reference
                ]);

                //Update balance
                $user->increment('coins_balance', $coinsToAdd);
            });

            return response()->json(['message' => 'Success!', 'new_balance' => $user->coins_balance], 201);

        }

        return response()->json(['error' => 'External payment failed.'], 422);
    }

    public function history(Request $request){
        $user = $request->user();

        $query = DB::table('coin_purchases')
            ->join('users', 'coin_purchases.user_id', '=', 'users.id')
            ->select('coin_purchases.*', 'users.nickname');

        if($user->type !== 'A'){
            $query->where('coin_purchases.user_id', $user->id);
        }

        return response()->json($query->orderBy('purchase_datetime', 'desc')->get());

    }

}
