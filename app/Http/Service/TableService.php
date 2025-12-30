<?php

namespace App\Http\Service;

use App\Models\Table;
use Illuminate\Support\Collection;

final class TableService
{
    // Obtener todas las ubicaciones distintas de las mesas
    public function getAllUbications(): array
    {
        return Table::select('ubication')->distinct()->orderBy('ubication')->pluck('ubication')->toArray();
    }

    // Obtener todas las mesas ordenadas por ubicación y número de asientos
    public function getTablesOrderByUbicationsAndSeats(): Collection
    {
        return Table::orderBy('ubication')->orderByDesc('seats')->get();
    }

    // Mostrar detalles de una mesa por ID
    public function show(int $id): Table
    {
        return Table::findOrFail($id);
    }

    // Obtener mesas libres por números de mesa y ubicación
    public function getFreeTablesByTableNumber(array $tableNumbers, string $location): Collection
    {
        return Table::whereNotIn('number', $tableNumbers)->where('ubication', $location)->orderByDesc('seats')->get();
    }
}
