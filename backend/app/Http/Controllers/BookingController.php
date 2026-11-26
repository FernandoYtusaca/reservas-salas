<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'scope' => ['nullable', Rule::in(['upcoming', 'past'])],
            'all' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $showAll = $user->isAdmin() && $request->boolean('all');

        $query = Booking::query()
            ->with(['room:id,name', 'user:id,name'])
            ->when(! $showAll, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status));

        if ($request->scope === 'upcoming') {
            $query->where('ends_at', '>', now())->orderBy('starts_at');
        } elseif ($request->scope === 'past') {
            $query->where('ends_at', '<=', now())->orderByDesc('starts_at');
        } else {
            $query->orderByDesc('starts_at');
        }

        return $query->paginate(15);
    }

    public function store(StoreBookingRequest $request)
    {
        $room = Room::findOrFail($request->integer('room_id'));
        $start = $request->start();
        $end = $request->end();

        $booking = DB::transaction(function () use ($request, $room, $start, $end) {
            // 1) Bloquear la fila de la sala: otra reserva para la misma sala espera aquí
            Room::whereKey($room->id)->lockForUpdate()->first();

            // 2) Con el bloqueo ya tomado, comprobar solapamientos
            $overlaps = Booking::where('room_id', $room->id)
                ->confirmed()
                ->overlapping($start, $end)
                ->exists();

            abort_if($overlaps, 409, 'La sala ya está reservada en ese horario.');

            // 3) Guardar
            return $request->user()->bookings()->create([
                'room_id' => $room->id,
                'title' => $request->input('title'),
                'starts_at' => $start,
                'ends_at' => $end,
            ]);
        });

        return response()->json($booking->refresh()->load('room:id,name'), 201);
    }

    public function show(Booking $booking)
    {
        Gate::authorize('view', $booking);

        return $booking->load(['room:id,name', 'user:id,name']);
    }

    public function cancel(Request $request, Booking $booking)
    {
        Gate::authorize('cancel', $booking);

        if ($booking->status === BookingStatus::Cancelled) {
            return response()->json(['message' => 'La reserva ya está cancelada.'], 409);
        }

        // Los usuarios cancelan solo reservas que no han empezado; el administrador, cualquiera
        if (! $request->user()->isAdmin() && $booking->starts_at->isPast()) {
            return response()->json(['message' => 'No se puede cancelar una reserva que ya empezó.'], 422);
        }

        $booking->cancel();

        return $booking->load('room:id,name');
    }
}
