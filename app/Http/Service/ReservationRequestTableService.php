<?php

namespace App\Http\Service;

use App\Models\ReservationRequestTable;

final class ReservationRequestTableService
{
    // Asociar mesas a una solicitud de reserva
    public function store(int $reservationRequestId, array $tableIds): void
    {
        $rows = array_map(fn ($tableId) => [
            'reservation_request_id' => $reservationRequestId,
            'table_id' => $tableId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $tableIds);

        ReservationRequestTable::insert($rows);
    }
}
