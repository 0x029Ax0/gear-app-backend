<?php

namespace App\Policies;

use App\Models\GearItem;
use App\Models\User;

class GearItemPolicy
{
    public function view(User $user, GearItem $gearItem): bool
    {
        return $gearItem->user_id === $user->id;
    }

    public function update(User $user, GearItem $gearItem): bool
    {
        return $this->view($user, $gearItem);
    }

    public function delete(User $user, GearItem $gearItem): bool
    {
        return $this->view($user, $gearItem);
    }
}
