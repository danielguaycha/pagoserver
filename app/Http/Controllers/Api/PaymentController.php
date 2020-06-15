<?php

namespace App\Http\Controllers\Api;

use App\Credit;
use App\Http\Controllers\ApiController;
use App\Payment;
use App\Person;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends ApiController
{

    public function __construct() {
        $this->middleware("auth:api");
    }

    public function index(Request $request)
    {
        $date = Carbon::now()->format('Y-m-d');
        $only = 'all';
        $src = '';

        // validar los query's de date (Fecha del cobro), only(Solo un tipo de plazo de cobro), src (Para mapas)
        if($request->query('date')) {$date = $request->query('date');}
        if($request->query('only')) {$only = Str::lower($request->query('only'));}
        if($request->query('src')) { $src = Str::lower($request->query('src'));}


        if($only === 'all') { // en caso de que quieran todos los plazos

            return $this->showAll($this->getPayments($date, Credit::COBRO_DIARIO, $src, true));
        }

        // en caso de que el plazo sea uno en especifico
        if($only === 'diario') {
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
