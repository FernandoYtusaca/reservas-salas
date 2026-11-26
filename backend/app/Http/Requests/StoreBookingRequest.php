<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $room = Room::findOrFail($this->integer('room_id'));
                $start = $this->start();
                $end = $this->end();

                if (! $start->isSameDay($end)) {
                    $validator->errors()->add('ends_at', 'La reserva debe empezar y terminar el mismo día.');

                    return;
                }

                foreach (['starts_at' => $start, 'ends_at' => $end] as $field => $moment) {
                    if ($moment->minute % 15 !== 0 || $moment->second !== 0) {
                        $validator->errors()->add($field, 'Las horas deben ser múltiplos de 15 minutos.');

                        return;
                    }
                }

                $opens = $start->copy()->setTimeFromTimeString($room->opens_at);
                $closes = $start->copy()->setTimeFromTimeString($room->closes_at);

                if ($start->lt($opens) || $end->gt($closes)) {
                    $validator->errors()->add('starts_at', "La sala solo abre de {$room->opens_at} a {$room->closes_at}.");

                    return;
                }

                if (abs($start->diffInMinutes($end)) > $room->max_booking_minutes) {
                    $validator->errors()->add('ends_at', "La duración máxima en esta sala es de {$room->max_booking_minutes} minutos.");
                }
            },
        ];
    }

    public function start(): Carbon
    {
        return Carbon::parse($this->input('starts_at'))->setTimezone(config('app.timezone'));
    }

    public function end(): Carbon
    {
        return Carbon::parse($this->input('ends_at'))->setTimezone(config('app.timezone'));
    }
}
