<?php

namespace App\Events\Sales;

use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Sale $sale,
        public User $user,
    ) {}
}
