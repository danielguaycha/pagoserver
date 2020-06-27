<?php

namespace App\Http\Controllers\Api;

use App\Credit;
use App\Http\Controllers\ApiController;
use App\Jobs\ProcessMora;
use App\Payment;
use App\Person;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends ApiController
{

    public function __construct()
    {
        $this->middleware("auth:api");
    }

    // payments - cobros
    public function listByCredit($creditId)
    {
        $credit = Credit::join('persons', 'persons.id', 'credits.person_id')
            ->select('persons.name',
                'credits.id', 'credits.f_inicio', 'credits.f_fin',
                'credits.total', 'credits.status')
            ->where('credits.id', $creditId)->first();

        $payments = Payment::select('id', 'abono', 'status', 'mora', 'date', 'number', 'dias_mora')
            ->where('credit_id', $creditId)->orderBy('date', 'asc')->get();

        $totales = $payments->where('status', Payment::STATUS_PAID)->sum('abono');
        $credit->total_pagado = $totales;
        $credit->payments = $payments;

        return $this->data($credit);
    }

    public function pay(Request $request, $creditId)
    {
        $request->validate(['pays' => 'array|required']);
        $returnPays = [];
        $c = Credit::find($creditId);
        if (!$c) {
            return $this->err("El crédito no existe");
        }

        $count = 0;
        foreach ($request->pays as $r) {
            $pay = Payment::find($r['pay']);
            if ($pay) {
                $pay->status = Payment::STATUS_PAID;
                $pay->user_id = $request->user()->id;
                $pay->date_payment = Carbon::now();
                $pay->description = 'PAGO EXITOSO';
                $count++;
                $pay->save();
                array_push($returnPays, $pay);
            }
        }
        ProcessMora::dispatchAfterResponse($c);
        return $this->data($returnPays);
    }

    public function abono(Request $request, $creditId)
    {
        $request->validate(['abono' => 'required']);
        $returnPays = [];
        $c = Credit::find($creditId);
        if (!$c) {
            return $this->err("El crédito no existe");
        }

        //* Valida si el crédito ya se ha terminado de pagar
        $sum = Payment::where([
            ['credit_id', $creditId],
            ['status', Payment::STATUS_PAID]
        ])->select('abono')->sum('abono');
        $deuda = $c->total - $sum;

        if ($deuda === 0.0) {
            return $this->err("Los cobros de este crédito están completos");
        }

        //*Validamos que el abono que viene no sea mayor a la deuda
        if ($request->abono > $deuda) {
            return $this->err("El abono es mucho mayor a la deuda total de $$deuda");
        }

        //Hay dos caminos, puede existir deuda pero ya todos los pagos por defecto se han cobrado
        $lastPay = $this->lastPay($creditId);
        if (!$lastPay && $deuda > 0) {
            $p = Payment::create([
                'credit_id' => $c->id,
                'abono' => $request->abono,
                'status' => Payment::STATUS_PAID,
                'date' => Carbon::now(),
                'total' => $deuda,
                'date_payment' => Carbon::now(),
                'user_id' => $request->user()->id,
                'description' => 'PAGO ADICIONAL'
            ]);
            array_push($returnPays, $p);
            return $this->data($returnPays);
        }

        // El otro caso es cuando aun hay pagos, disponibles que actualizar
        // calculo de pagos aun no procesados|actualizados
        $abono = doubleval($request->abono);
        $cuota = $lastPay->total;
        $userId = $request->user()->id;

        // si es menor, puede ser que sea un atraso
        if ($abono < $cuota) {
            $p = $this->savePay($userId, $creditId, $abono, true);
            array_push($returnPays, $p);
        } else if ($abono > $cuota) {
            $mora = Payment::where([
                ['credit_id', $creditId],
                ['mora', true]
            ])->count();

            if ($mora > 0) { // si tiene mora no se puede considerar un adelanto
                $p = $this->savePay($userId, $creditId, $abono);
                array_push($returnPays, $p);
            } else { // si no tiene mora, es un adelanto
                $nPagos = $abono / $cuota; // numero de pagos
                for ($i = 0; $i < intval($nPagos); $i++) {
                    if ($i === 0) {
                        $tempAbono = intval($nPagos) * $cuota;
                        $abono = $abono - $tempAbono;
                        $p = $this->savePay($userId, $creditId, $tempAbono);
                        array_push($returnPays, $p);
                    } else {
                        $p = $this->savePay($userId, $creditId, 0);
                        array_push($returnPays, $p);
                    }
                }
                if ($abono > 0) {
                    $p = $this->savePay($userId, $creditId, $abono);
                    array_push($returnPays, $p);
                }
            }
        } else {
            $p = $this->savePay($userId, $creditId);
            array_push($returnPays, $p);
        }
        ProcessMora::dispatchAfterResponse($c);
        return $this->data($returnPays);
    }

    private function savePay($userId, $creditId, $abono = null, $mora = false)
    {
        $p = $this->lastPay($creditId);
        $p->status = Payment::STATUS_PAID;
        if ($abono !== null) {
            $p->abono = $abono;
        }
        if ($mora) {
            $p->mora = 1;
            $p->description = "COBRO CON MORA";
        } else {
            $p->description = "COBRO EXITOSO";
        }
        $p->user_id = $userId;
        $p->date_payment = Carbon::now();
        $p->save();

        return $p;
    }

    private function lastPay($creditId)
    {
        return Payment::where([
            ['status', '<>', Payment::STATUS_PAID],
            ['credit_id', $creditId]
        ])->orderBy('id', 'asc')->first();
    }


    // old code
    public function index(Request $request)
    {
        $date = Carbon::now()->format('Y-m-d');
        $only = 'all';
        $src = '';

        // validar los query's de date (Fecha del cobro), only(Solo un tipo de plazo de cobro), src (Para mapas)
        if ($request->query('date')) {
            $date = $request->query('date');
        }
        if ($request->query('only')) {
            $only = Str::lower($request->query('only'));
        }
        if ($request->query('src')) {
            $src = Str::lower($request->query('src'));
        }


        if ($only === 'all') { // en caso de que quieran todos los plazos

            return $this->showAll($this->getPayments($date, Credit::COBRO_DIARIO, $src, true));
        }

        // en caso de que el plazo sea uno en especifico
        if ($only === 'diario') {
            return $this->ok(['diario' => $this->getPayments($date, Credit::COBRO_DIARIO, $src)]);
        }

        if($only === 'semanal') {
            return $this->ok(['semanal' => $this->getPayments($date, Credit::COBRO_SEMANAL, $src)]);
        }

        if($only === 'quincenal') {
            return $this->ok(['quincenal' => $this->getPayments($date, Credit::COBRO_QUINCENAL, $src)]);
        }

        if($only === 'mensual') {
            return $this->ok(['mensual' => $this->getPayments($date, Credit::COBRO_MENSUAL, $src)]);
        }
    }

    public function getPayments ($date, $cobro = Credit::COBRO_DIARIO, $src = '', $all = false) {
        // consulta base
        $payments = Payment::join('credits', 'credits.id', 'payments.credit_id')
            ->join('persons', 'persons.id', 'credits.person_id')
            ->whereDate('payments.date', $date);

        if($src === 'map') { // selección solo para mapas
            $payments->select('payments.credit_id', 'payments.id', 'persons.name as client_name');
        } else { // selección para vista normal
            $payments->select('credits.cobro',
                'payments.id', 'payments.credit_id',
                'payments.total', 'payments.status',
                'payments.mora', 'payments.number',
                'payments.description',
                'persons.id as client_id',
                'persons.name as client_name',
                'persons.address_a', 'persons.city_a');
        }
        if(!$all)
            return $payments->where('credits.cobro', $cobro)->get();
        else
            return $payments->get();
    }

    public function store(Request $request)
    {

    }

    public function show($id)
    {
        // $id = credit_id
        $credit = Credit::join('persons', 'persons.id', 'credits.person_id')
            ->select('persons.name',
                'credits.id', 'credits.monto', 'credits.utilidad',
                'credits.total' , 'credits.description', 'credits.status')
            ->where('credits.id', $id)->first();


        $payments = Payment::select('id', 'total', 'status', 'mora', 'date', 'number')
            ->where('credit_id', $id)->orderBy('date', 'asc')->get();

        $totales = $payments->where('status', Payment::STATUS_PAID)->sum('total');
        $nPagos = $payments->where('status', Payment::STATUS_PAID)->count('id');

        $credit->pagados =  $nPagos;
        $credit->total_pagado = $totales;
        $credit->payments = $payments;

        return $this->data($credit);
    }

    public function showByCredit($id) {
        $payments = Payment::where('credit_id', $id)
            ->select('payments.*')
            ->orderBy('payments.date', 'asc')->get();

        $totales = $payments->where('status', Payment::STATUS_PAID);

        return $this->data([
            'pagado' =>  $totales->sum('total'),
            'n_pagos' => $totales->count(),
            'pays' => $payments,
        ]);
    }

    public function update(Request $request, $id) {
        $request->validate([
            'status' => 'required|in:2,-1',
            'description' => 'nullable|string|max:100'
        ]);

        $payment = Payment::findOrFail($id);

        if($payment->status === 2) {return $this->err('Este pago ya fue procesado la fecha '.$payment->date_payment); }

        $payment->status = $request->get('status');

        if($payment->status == 2) {
            $payment->description = ($request->get('description') ? $request->get('description') : 'Cobro exitoso');
        }

        if($payment->status == -1) {
            $payment->mora =  true;
            $payment->description = ($request->get('description') ? $request->get('description') : 'Registrado con atraso o mora');
            // Moratoria
            $this->calificar($payment->credit_id);
        }

        $payment->date_payment = Carbon::now()->format('Y-m-d');
        $payment->user_id = $request->user()->id;

        if($payment->save()) {
            return $this->showOne($payment);
        }
        else {
            return $this->err('No he podido procesar el pago');
        }
    }

    public function destroy(Request $request, $id) {

        $request->validate([
            'description' => 'required|string|max:100'
        ], [
            'description.required' => 'Ingrese el motivo para anular!'
        ]);

        $payment = Payment::findOrFail($id);

        if($payment->status !== Payment::STATUS_PAID) {
            return $this->err('Solo es posible anular pagos procesados!');
        }

        $payment->status = Payment::STATUS_ACTIVE;
        $payment->description = $request->description;

        if($payment->save()) {
            return $this->success("Anulado exitosamente");
        }
        return $this->err('No se ha podido anular el pago');
    }

    private function calificar($creditId) {
        $c = Credit::findOrFail($creditId);
        $p = Person::findOrFail($c->person_id);

        $p->rank = ($p->rank - Payment::POINT_BY_MORA);

        $p->save();
    }
}
