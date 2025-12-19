<?php

namespace App\Http\Service;

use App\Models\ReservationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class ReservationRequestService
{
    public function createReservationRequest(string $day, string $hour, int $numberOfPeople)
    {
        $canSeat = $this->checkAvailability($day, $hour, $numberOfPeople);
        if (!$canSeat) {
            throw new \Exception('No hay disponibilidad para la fecha y hora solicitadas.');
        }

        $ubication = DB::table('tables')->whereIn('id', $canSeat)->first()->ubication ?? null;

        //Crear reserva
        $reservationRequest = ReservationRequest::create([
            'user_id' => User::inRandomOrder()->first()->id,
            'ubication' => $ubication,
            'number_of_people' => $numberOfPeople,
            'reservation_date' => $day,
            'start_time' => $hour,
            'end_time' => (clone Carbon::parse($hour))->addHours(2),
        ]);

        // Asociar las mesas a la reserva
        foreach ($canSeat as $tableId) {
            DB::table('reservation_requests_tables')->insert([
                'reservation_request_id' => $reservationRequest->id,
                'table_id' => $tableId,
            ]);
        }
    }

    // Obtener reservas por día y ubicación, con caching
    public function getReservationsPerDayAndUbication(string $day): array
    {
        $dateKey = Carbon::parse($day, 'GMT-3')->format('Y-m-d');

        $cacheKey = "reservations:day:{$dateKey}";

        // Intentar obtener del caché, si no existe, actualizarlo
        return Cache::rememberForever("reservations:day:{$dateKey}", function () use ($cacheKey, $dateKey) {
            return $this->updateCachePerDay($cacheKey, $dateKey);
        });
    }

    // Actualizar caché para un día específico
    private function updateCachePerDay(string $cacheKey, string $dateKey): array
    {
        return Cache::store(config('cache.default'))->remember($cacheKey, 60, function () use ($cacheKey, $dateKey) {
            return $this->getReservationsPerDayByUbication($cacheKey, $dateKey);
        });
    }

    // Obtener reservas por día y ubicación desde la base de datos
    private function getReservationsPerDayByUbication(string $dateKey): array
    {
            $query = DB::table('reservation_requests as rr')
                    ->join('reservation_requests_tables as rrt', 'rr.id', '=', 'rrt.reservation_request_id')
                    ->join('tables as t', 'rrt.table_id', '=', 't.id')
                    ->select(
                        'rr.id as reservation_request_id',
                        'rr.ubication',
                        'rr.number_of_people',
                        'rr.reservation_date',
                        'rr.start_time',
                        'rr.end_time',
                        DB::raw('GROUP_CONCAT(t.number) as table_numbers'),
                        DB::raw('SUM(t.seats) as total_seats')
                    )
                    ->whereDate('rr.reservation_date', $dateKey)
                    ->groupBy(
                        'rr.id',
                        'rr.ubication',
                        'rr.number_of_people',
                        'rr.reservation_date',
                        'rr.start_time',
                        'rr.end_time'
                    )
                    ->orderBy('rr.ubication', 'asc');

        return $this->groupReservationsByUbication($query->get());
    }

    // Agrupar reservas por ubicación
    private function groupReservationsByUbication(Collection $row): array
    {
        return $row->groupBy('ubication')->map(function ($reservas) {
                return $reservas->map(function ($reserva) {
                    return [
                        'reservation_request_id' => $reserva->reservation_request_id,
                        'number_of_people' => $reserva->number_of_people,
                        'reservation_date' => $reserva->reservation_date,
                        'start_time' => $reserva->start_time,
                        'end_time' => $reserva->end_time,
                        'table_numbers' => array_map('intval', explode(',', $reserva->table_numbers ?? '')),
                        'total_seats' => $reserva->total_seats,
                    ];
                })->values();
            })->toArray();
    }

    // Verificar disponibilidad para una solicitud específica
    private function checkAvailability(string $day, string $hour, int $numberOfPeople): array|bool
    {
        $dayRequest = Carbon::createFromFormat('Y-m-d H:i', "{$day} {$hour}", 'GMT-3');
        $requestStart = Carbon::createFromFormat('H:i', $hour);
        $requestEnd = (clone $requestStart)->addHours(2); // Default 2h

        // Verificar 15 minutos de anticipación si es hoy
        $today = Carbon::now('GMT-3');
        if ($dayRequest->isSameDay($today) && $today->diffInMinutes($dayRequest) < 15) {
            return false;
        }

        // Obtener reservas del día agrupadas por ubicación
        $reservationsByUbication = $this->getReservationsPerDayAndUbication($day);

        // Obtener ubicaciones de la tabla tables en orden alfabético
        $locations = DB::table('tables')
            ->select('ubication')
            ->distinct()
            ->orderBy('ubication', 'asc')
            ->pluck('ubication')
            ->toArray();

        // Obtener asientos por ubicación
        $seatsByUbication = DB::table('tables')
            ->select('ubication', 'id', 'number', 'seats')
            ->orderBy('ubication')
            ->orderByDesc('seats')
            ->get();

        // Calcular capacidad máxima por ubicación (top 3 mesas)
        $maxSeatsByUbication = $seatsByUbication->groupBy('ubication')
            ->map(function ($tablesByUbication) {
                $topTables = $tablesByUbication->take(3);

                return [
                    'total_seats' => $topTables->sum('seats'),
                ];
            });

        // Verificar disponibilidad en cada ubicación
        foreach ($locations as $location) {
            // 1. Obtener capacidad total de asientos en esta ubicación
            $maxSeatsInLocation = $maxSeatsByUbication[$location]['total_seats'] ?? 0;

            if ($maxSeatsInLocation < $numberOfPeople) {
                continue; // No alcanza capacidad total, próxima ubicación
            }

            // 2. Obtener reservas en esta ubicación
            $reservationsHere = $reservationsByUbication[$location] ?? [];

            // 3. Calcular asientos ocupados por solapamiento de horarios
            $occupiedTables = [];
            foreach ($reservationsHere as $reservation) {
                $resStart = Carbon::createFromFormat('H:i:s', $reservation['start_time']);
                $resEnd = Carbon::createFromFormat('H:i:s', $reservation['end_time']);

                // Verificar solapamiento
                if ($this->hasTimeOverlap($requestStart, $requestEnd, $resStart, $resEnd)) {
                    $occupiedTables = array_merge($occupiedTables, $reservation['table_numbers']);
                }
            }

            // Obtener mesas libres en esta ubicación
            $freeTables = DB::table('tables')
                ->where('ubication', $location)
                ->whereNotIn('number', $occupiedTables)
                ->orderByDesc('seats')
                ->get();

            // Obtener asientos disponibles
            $canSeat = $this->getTablesForGuests($freeTables, $numberOfPeople);

            // Verificar si hay suficientes asientos disponibles
            if ($canSeat !== null) {
                return $canSeat; // Disponibilidad encontrada
            }
        }

        return false; // Ninguna ubicación tiene disponibilidad
    }

    // Seleccionar mesas para acomodar a los invitados
    private function getTablesForGuests($freeTables, int $guests): ?array
    {
        $selected = [];
        $totalSeats = 0;

        // Seleccionar mesas hasta alcanzar la cantidad de invitados
        foreach ($freeTables as $table) {
            if (count($selected) === 3) {
                break;
            }

            // Seleccionar mesa
            $selected[] = $table;
            $totalSeats += $table->seats;

            // Verificar si se alcanzó la cantidad de invitados
            if ($totalSeats >= $guests) {
                return $selected; // Mesas seleccionadas
            }
        }

        // No se pudo acomodar a todos los invitados
        return null;
    }

    /**
     * Verifica si dos rangos de tiempo se solapan.
     */
    private function hasTimeOverlap(\DateTime $start1, \DateTime $end1, \DateTime $start2, \DateTime $end2): bool
    {
        return $start1 < $end2 && $start2 < $end1;
    }
}
