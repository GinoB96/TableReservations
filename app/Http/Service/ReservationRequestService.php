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
    public function __construct(
        private TableService $tableService,
        private ReservationRequestTableService $reservationRequestTableService,
    ) {
        $this->tableService = $tableService;
        $this->reservationRequestTableService = $reservationRequestTableService;
    }

    public function createReservationRequest(string $day, string $hour, int $numberOfPeople): ReservationRequest
    {
        $canSeat = $this->checkAvailability($day, $hour, $numberOfPeople);
        if (null === $canSeat) {
            throw new \Exception('No hay disponibilidad para la fecha y hora solicitadas.');
        }

        $ubication = $this->tableService->show($canSeat[0]->id)->ubication ?? null;

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
        $this->reservationRequestTableService->store(
            $reservationRequest->id,
            array_map(fn ($table) => $table->id, $canSeat)
        );

        return $reservationRequest;
    }

    // Obtener reservas por día y ubicación, con caching
    public function getReservationsPerDayAndUbication(string $day): array
    {
        $dateKey = Carbon::parse($day, 'GMT-3')->format('Y-m-d');

        $cacheKey = "reservations:day:{$dateKey}";

        // Intentar obtener del caché, si no existe, actualizarlo
        return Cache::rememberForever($cacheKey, function () use ($cacheKey, $dateKey) {
            return $this->updateCachePerDay($cacheKey, $dateKey);
        });
    }

    // Actualizar caché para un día específico
    private function updateCachePerDay(string $cacheKey, string $dateKey): array
    {
        $reservations = $this->getReservationsPerDayByUbication($dateKey);

        Cache::put($cacheKey, $reservations, 60);

        return $reservations;
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
    private function checkAvailability(string $day, string $hour, int $numberOfPeople): ?array
    {
        $dayRequest = Carbon::createFromFormat('Y-m-d H:i', "{$day} {$hour}", 'GMT-3');
        $requestEnd = (clone $dayRequest)->addHours(2); // Default 2h

        // Obtener reservas del día agrupadas por ubicación
        $reservationsByUbication = $this->getReservationsPerDayAndUbication($day);

        // Obtener ubicaciones de la tabla tables en orden alfabético
        $locations = $this->tableService->getAllUbications();

        // Obtener asientos por ubicación
        $seatsByUbication = $this->tableService->getTablesOrderByUbicationsAndSeats();

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
                if ($this->hasTimeOverlap($dayRequest, $requestEnd, $resStart, $resEnd)) {
                    $occupiedTables = array_merge($occupiedTables, $reservation['table_numbers']);
                }
            }

            // Obtener mesas libres en esta ubicación
            $freeTables = $this->tableService->getFreeTablesByTableNumber($occupiedTables, $location);

            // Obtener asientos disponibles
            $canSeat = $this->getTablesForGuests($freeTables, $numberOfPeople);

            // Verificar si hay suficientes asientos disponibles
            if ($canSeat !== null) {
                return $canSeat; // Disponibilidad encontrada
            }
        }

        return null; // Ninguna ubicación tiene disponibilidad
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
    private function hasTimeOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        $start1 = Carbon::parse('2025-12-22 18:30:00');
        $end1   = Carbon::parse('2025-12-22 19:00:00');

        $start2 = Carbon::parse('2025-12-22 17:00:00');
        $end2   = Carbon::parse('2025-12-22 20:30:00');
        return $start1 < $end2 && $start2 < $end1;
    }
}
