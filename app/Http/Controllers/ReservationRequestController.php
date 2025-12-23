<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationRequestStoreRequest;
use App\Http\Requests\ReservationsPerDayRequest;
use App\Http\Service\ReservationRequestService;

final class ReservationRequestController extends Controller
{
    public function __construct(private ReservationRequestService $reservationRequestService)
    {
    }

    /**
     *  Get reservations per day.
     */
    public function getReservationsPerDay(ReservationsPerDayRequest $request)
    {
        $validated = $request->validated();

        $result = $this->reservationRequestService->getReservationsPerDayAndUbication($validated['day']);

        return response()->json([
            'message' => 'Reservations retrieved successfully.',
            'data' => $result,
        ]);
    }

    /**
     * Store a new reservation request.
     */
    public function store(ReservationRequestStoreRequest $request)
    {
        $validated = $request->validated();

        $reservationRequestId = $this->reservationRequestService->createReservationRequest(
            $validated['day'],
            $validated['hour'],
            $validated['number_of_people']
        );

        return response()->json([
            'message' => 'Reservation request created successfully.',
            'reservation_request' => $reservationRequestId,
        ], 201);
    }
}
