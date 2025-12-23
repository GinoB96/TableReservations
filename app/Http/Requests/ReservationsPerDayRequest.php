<?php

namespace App\Http\Requests;

use App\Rules\ValidReservationDay;
use Illuminate\Foundation\Http\FormRequest;

class ReservationsPerDayRequest extends FormRequest
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
        ];
    }
}
