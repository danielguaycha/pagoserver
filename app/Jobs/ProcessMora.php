<?php

namespace App\Jobs;

use App\Credit;
use App\Payment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMora implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $credit;

    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }

    public function handle()
    {
        $date = Carbon::now();

        $p = Payment::where([
            ['credit_id', $this->credit->id],
            ['date', '<=', $date->format('Y-m-d')]
        ])->get();

        $abono = $p->sum('abono');
        $total = $p->sum('total');

        if ($abono < $total) {
            $payMora = Payment::where([
                ['mora', true],
                ['credit_id', $this->credit->id],
            ])->orderBy('id', 'desc')->first();
            $payMora->dias_mora = $payMora->dias_mora + 1;
            $payMora->save();
        }

        if ($abono >= $total) {
            $payMora = Payment::where([
                ['mora', true],
                ['credit_id', $this->credit->id],
            ]);
            if ($payMora->count() > 0) {
                $payMora->update(['mora' => false]);
            }
        }
    }
}
