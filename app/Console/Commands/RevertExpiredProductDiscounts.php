<?php

namespace App\Console\Commands;

use App\Models\ProductDiscount;
use App\Jobs\RevertProductDiscountJob;
use Carbon\Carbon;
use App\Services\ShopifyLoggerService;
use Illuminate\Console\Command;

class RevertExpiredProductDiscounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discounts:revert-expired-product-discounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revert expired product discounts to their original prices.';

    public function handle()
    {
        $expiredDiscounts = ProductDiscount::whereNotNull('end_date') // Ensure end_date is set
            ->where('end_date', '<', Carbon::now()) // Check if end_date has passed
            ->where('is_reverted', false) // Check if not already reverted
            ->get();

        ShopifyLoggerService::log(
            'revert_product_discounts',
            null,
            'start',
            'Revert Product Discounts Job',
            ['total_products_to_revert' => $expiredDiscounts->count()]
        );

        foreach ($expiredDiscounts as $discount) {
            // Dispatch the job for each expired discount
            RevertProductDiscountJob::dispatch($discount);
        }

        $this->info('Processed expired discounts successfully.');
    }
}
