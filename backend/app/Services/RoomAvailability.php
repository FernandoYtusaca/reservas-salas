<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomAvailability
{
    //Horario de apertura, reservas confirmadas y tramos libres de una sala en un día.
    public function forDay(Room $room, Carbon $day): array
    {
        $opens = $day->copy()->startOfDay()->setTimeFromTimeString($room->opens_at);
        $closes = $day->copy()->startOfDay()->setTimeFromTimeString($room->closes_at);

        $bookings = Booking::where('room_id', $room->id)
            ->confirmed()
            ->overlapping($opens, $closes)
            ->orderBy('starts_at')
            ->get();

        return [
            'opens' => $opens,
            'closes' => $closes,
            'bookings' => $bookings,
            'free' => $this->freeRanges($opens, $closes, $bookings, now()),
        ];
    }


    public function freeRanges(Carbon $opens, Carbon $closes, Collection $bookings, Carbon $now): array
    {
        // Si ya se reservo ya no se puede reservar
        // se empieza en el próximo múltiplo de 15 minutos
        $cursor = $opens->copy()->max($now->copy()->ceilMinutes(15));

        $free = [];

        foreach ($bookings as $booking) {
            if ($booking->starts_at->gt($cursor)) {
                $free[] = [$cursor->copy(), $booking->starts_at->copy()];
            }

            if ($booking->ends_at->gt($cursor)) {
                $cursor = $booking->ends_at->copy();
            }
        }

        if ($cursor->lt($closes)) {
            $free[] = [$cursor->copy(), $closes->copy()];
        }

        return $free;
    }
}
