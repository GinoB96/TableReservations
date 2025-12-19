<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class ReservationRequestTable extends Model
{
    use HasFactory;
    use Notifiable;

    protected $table = 'reservation_requests_tables';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reservation_request_id',
        'table_id',
    ];

    public function reservationRequest()
    {
        return $this->belongsTo(ReservationRequest::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }
}
