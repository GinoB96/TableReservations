<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class ReservationRequest extends Model
{
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ubication',
        'number_of_people',
        'reservation_date',
        'start_time',
        'end_time',
    ];

    private function user()
    {
        return $this->belongsTo(User::class);
    }

    private function reservationRequestTables()
    {
        return $this->hasMany(ReservationRequestTable::class);
    }
}
