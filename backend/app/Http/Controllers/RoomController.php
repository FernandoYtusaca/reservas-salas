<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Services\RoomAvailability;
use Illuminate\Support\Carbon;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        return Room::query()
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    public function show(Request $request, Room $room)
    {
        abort_if(! $room->is_active && ! $request->user()->isAdmin(), 404);

        return $room;
    }

    public function store(Request $request)
    {
        $room = Room::create($this->validated($request));

        return response()->json($room->refresh(), 201);
    }

    public function update(Request $request, Room $room)
    {
        $room->update($this->validated($request, $room));

        return $room->refresh();
    }

    public function destroy(Room $room)
    {
        if ($room->bookings()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una sala con reservas. Desactívala en su lugar.',
            ], 409);
        }

        $room->delete();

        return response()->noContent();
    }

    public function availability(Request $request, Room $room, RoomAvailability $availability)
    {
        $user = $request->user();
        abort_if(! $room->is_active && ! $user->isAdmin(), 404);
        $data = $request->validate([
        'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $day = Carbon::createFromFormat('!Y-m-d', $data['date'], config('app.timezone'));
        $result = $availability->forDay($room, $day);

        // Todos los instantes salen en UTC; el cliente los muestra en su zona horaria
        $iso = fn (Carbon $moment) => $moment->copy()->utc()->toIso8601ZuluString();

        return [
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'max_booking_minutes' => $room->max_booking_minutes,
            ],
            'date' => $data['date'],
            'timezone' => config('app.timezone'),
            'opens_at' => $iso($result['opens']),
            'closes_at' => $iso($result['closes']),
            'bookings' => $result['bookings']->map(fn ($booking) => [
                'id' => $booking->id,
                'starts_at' => $iso($booking->starts_at),
                'ends_at' => $iso($booking->ends_at),
                'mine' => $booking->user_id === $user->id,
                'title' => ($booking->user_id === $user->id || $user->isAdmin()) ? $booking->title : null,
            ])->values(),
            'free' => collect($result['free'])->map(fn ($range) => [
                'starts_at' => $iso($range[0]),
                'ends_at' => $iso($range[1]),
            ])->values(),
        ];
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('rooms', 'name')->ignore($room?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'max_booking_minutes' => ['required', 'integer', 'min:15', 'max:480', 'multiple_of:15'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
