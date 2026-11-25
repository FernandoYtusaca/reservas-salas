<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
