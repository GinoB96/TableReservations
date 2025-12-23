<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidReservationDay implements ValidationRule
{
    protected ?string $hour = null;

    public function __construct(?string $hour = null)
    {
        $this->hour = $hour;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d H:i', "{$value} {$this->hour}", 'GMT-3');
            $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday

            // Verificar 15 minutos de anticipación si es hoy
            $today = Carbon::now('GMT-3');
            if ($date->isSameDay($today) && $today->diffInMinutes($date) < 15) {
                $fail("Las reservas para hoy deben hacerse con al menos 15 minutos de anticipación.");
            }

            // Si se proporciona hora, validar horarios según el día
            if ($this->hour) {
                $hourInt = (int) Carbon::createFromFormat('H:i', $this->hour, 'GMT-3')->format('H');

                // Validar según el día de la semana:
                // L-V (1-5): 10 a 24 (23:59)
                // Sábado (6): 22 a 2AM (23:59 y 0-1)
                // Domingo (0): 12 a 16 (15:59)

                $isValidTime = match($dayOfWeek) {
                    0 => $hourInt >= 12 && $hourInt < 16,  // Domingo: 12 a 16
                    1, 2, 3, 4, 5 => $hourInt >= 10 && $hourInt < 24,  // Lunes a Viernes: 10 a 24
                    6 => ($hourInt >= 22 && $hourInt < 24) || ($hourInt >= 0 && $hourInt < 2),  // Sábado: 22 a 2AM
                    default => false,
                };

                if (!$isValidTime) {
                    $dayName = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$dayOfWeek];
                    $hours = match($dayOfWeek) {
                        0 => '12:00 a 16:00',
                        1, 2, 3, 4, 5 => '10:00 a 24:00',
                        6 => '22:00 a 02:00',
                    };
                    $fail("El $dayName solo está disponible de $hours.");
                }
            }
        } catch (\Exception $e) {
            $fail('La fecha u hora no son válidas.');
        }
    }
}
