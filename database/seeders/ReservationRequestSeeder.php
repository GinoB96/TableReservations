<?php

namespace Database\Seeders;

use App\Models\ReservationRequest;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReservationRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ReservationRequest::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
