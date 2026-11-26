<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    public function view(User $user, Booking $booking): Response
    {
        return $user->isAdmin() || $booking->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function cancel(User $user, Booking $booking): Response
    {
        return $this->view($user, $booking);
    }
}
