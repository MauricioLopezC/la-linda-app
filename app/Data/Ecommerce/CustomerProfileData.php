<?php

namespace App\Data\Ecommerce;

use App\Models\User;
use Spatie\LaravelData\Data;

class CustomerProfileData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $id_number,
        public ?string $created_at,
    ) {}

    public static function fromUser(User $user): self
    {
        $customer = $user->customer;

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $customer?->phone,
            address: $customer?->address,
            id_number: $customer?->id_number,
            created_at: $user->created_at?->toIso8601String(),
        );
    }
}
