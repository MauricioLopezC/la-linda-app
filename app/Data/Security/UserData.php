<?php

declare(strict_types=1);

namespace App\Data\Security;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public string $role_label,
        public ?int $branch_id,
        public ?string $branch_name,
        public bool $is_active,
        public ?string $created_at,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role->value,
            role_label: $user->role->label(),
            branch_id: $user->branch_id,
            branch_name: $user->branch?->name,
            is_active: $user->is_active,
            created_at: $user->created_at?->toIso8601String(),
        );
    }
}
