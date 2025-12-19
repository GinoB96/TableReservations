<?php

namespace App\Http\Requests;

use App\Rules\ValidReservationDay;
use Illuminate\Foundation\Http\FormRequest;

final class ReservationRequestStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day' => [
                'required',
                'date',
                'after_or_equal:' . now('GMT-3')->format('Y-m-d'),
                'before_or_equal:' . now('GMT-3')->addDays(7)->format('Y-m-d'),
                new ValidReservationDay($this->input('hour')),
            ],
            'hour' => [
                'required',
                'date_format:H:i',
            ],
            'number_of_people' => [
                'required',
                'integer',
                'min:1',
                'max:20'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'day.required' => 'La fecha es requerida.',
            'day.date' => 'La fecha debe ser un formato de fecha válido.',
            'day.after_or_equal' => 'La fecha debe ser a partir de hoy.',
            'day.before_or_equal' => 'La fecha no puede ser más de 7 días en el futuro.',
            'hour.required' => 'La hora es requerida.',
            'hour.date_format' => 'La hora debe tener el formato HH:mm (ej: 14:30).',
            'number_of_people.required' => 'La cantidad de personas es requerida.',
            'number_of_people.integer' => 'La cantidad de personas debe ser un número entero.',
            'number_of_people.min' => 'Debe haber al menos 1 persona para hacer una reserva.',
            'number_of_people.max' => 'No se pueden reservar más de 20 personas.',
        ];
    }
}
