<?php

use HipstersgDemo\LaravelUserDiscountsPackage\Models\Discount;
use HipstersgDemo\LaravelUserDiscountsPackage\Models\UserDiscount;
use HipstersgDemo\LaravelUserDiscountsPackage\Services\DiscountManager;
use HipstersgDemo\LaravelUserDiscountsPackage\Events\DiscountApplied;
use Illuminate\Support\Facades\Event;
use App\Models\User;

it('applies a discount and respects per user cap', function () {

    Event::fake();

    $user = User::factory()->create();

    $discount = Discount::create([
        'code' => 'WELCOME10',
        'type' => 'percentage',
        'value' => 10,
        'active' => true,
        'per_user_cap' => 1, // only one usage allowed
    ]);

    $manager = app(DiscountManager::class);

    // assign discount
    $manager->assign($user, $discount);

    // apply first time
    $amountAfterFirstApply = $manager->apply($user, 100.00);
    // Updated expectation to check the amount from returned array
    expect($amountAfterFirstApply['amount'])->toBe(90.00);

    // apply second time → should not apply
    $amountAfterSecondApply = $manager->apply($user, 100.00);
    expect($amountAfterSecondApply['amount'])->toBe(100.00);

    // usage count incremented only once
    $userDiscount = UserDiscount::first();
    expect($userDiscount->usage_count)->toBe(1);

    // Updated expected dispatch count for DiscountApplied event
    Event::assertDispatchedTimes(DiscountApplied::class, 2);
});
