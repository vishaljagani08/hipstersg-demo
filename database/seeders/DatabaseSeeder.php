<?php

namespace Database\Seeders;

use App\Models\User;
use HipstersgDemo\LaravelUserDiscountsPackage\Facades\Discounts;
use HipstersgDemo\LaravelUserDiscountsPackage\Models\Discount;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Create demo user
        $user = User::firstOrCreate(['email' => 'demo@example.com'], [
            'name' => 'Demo User',
            'password' => bcrypt('password')
        ]);


        // Create some discounts
        $discount1 = Discount::firstOrCreate([
            'code' => 'WELCOME10',
        ], [
            'type' => 'percentage',
            'value' => 10,
            'active' => true,
            'stacking_priority' => 10,
        ]);


        $discount2 = Discount::firstOrCreate([
            'code' => 'FLAT50',
        ], [
            'type' => 'fixed',
            'value' => 50,
            'active' => true,
            'stacking_priority' => 5,
        ]);


        // Assign discounts to user
        Discounts::assign($user, $discount1);
        Discounts::assign($user, $discount2);


        // Apply discounts to an example amount
        $result = Discounts::apply($user, 500.00, ['idempotency_key' => 'order-demo-001']);


        echo "Original Amount: 500.00\n";
        echo "Final Amount after discounts: {$result['amount']}\n";


        // Re-apply with same idempotency_key (should be idempotent)
        $repeat = Discounts::apply($user, 500.00, ['idempotency_key' => 'order-demo-001']);
        echo "Re-applied Amount (idempotent): {$repeat['amount']}\n";
    }
}
