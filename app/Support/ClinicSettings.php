<?php

namespace App\Support;

use App\Models\Clinic;
use Illuminate\Support\Facades\DB;

class ClinicSettings
{
    /**
     * @param  array{clinic: array{name: string, spec?: ?string, doctor?: ?string, phone?: ?string, address?: ?string, open: string, close: string}, fees: array<string, int|float|string>}  $data
     */
    public function update(Clinic $clinic, array $data): Clinic
    {
        DB::transaction(function () use ($clinic, $data): void {
            $clinic->fill([
                'name' => $data['clinic']['name'],
                'specialty' => $data['clinic']['spec'] ?? null,
                'doctor_name' => $data['clinic']['doctor'] ?? null,
                'phone' => $data['clinic']['phone'] ?? null,
                'address' => $data['clinic']['address'] ?? null,
                'open_time' => $data['clinic']['open'],
                'close_time' => $data['clinic']['close'],
                'fees' => array_map(fn (int|float|string $value): int|float => $value + 0, $data['fees']),
            ]);
            $clinic->touchSettings();
            $clinic->save();
        });

        return $clinic->fresh();
    }

    /**
     * @param  array{paper: string, top: int|float|string, right: int|float|string, bottom: int|float|string, left: int|float|string, fontScale: int|string, showHeader: bool|int|string, showDiagnosis: bool|int|string, printImage: bool|int|string}  $data
     */
    public function updateRx(Clinic $clinic, array $data): Clinic
    {
        DB::transaction(function () use ($clinic, $data): void {
            $clinic->rx_settings = array_map(fn (mixed $value): mixed => is_numeric($value) && ! is_bool($value) ? $value + 0 : $value, $data);
            $clinic->touchSettings();
            $clinic->save();
        });

        return $clinic->fresh();
    }
}
