<?php

namespace App\Http\Controllers\Api;

use App\Credit;
use App\Http\Controllers\ApiController;
use App\Payment;
use App\Person;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends ApiController
{

    public function __construct()
    {
        $this->middleware("auth:api");
    }

    // payments - cobros
    public function listByCredit($creditId, Request $request)
    {
        $credit = Credit::join('persons', 'persons.id', 'credits.person_id')
            ->select('persons.name',
                'credits.id', 'credits.f_inicio', 'credits.f_fin', 'credits.monto',
                'credits.total', 'credits.status')
            ->where('credits.id', $creditId)->first();

        $select = ['id', 'total', 'abono', 'status', 'date', 'description', 'date_payment', 'pending'];

        if ($request->query('all')) {
            $payments = Payment::select($select)
                ->where([
                    ['credit_id', $creditId],
                ])->orderBy('date', 'asc')->get();
        } else {
            $payments = Payment::select($select)
                ->where([
                    ['credit_id', $creditId],
                    ['date', '<=', Carbon::now()->format('Y-m-d')]
                ])->orderBy('date', 'asc')->get();
        }


        $totales = $payments->where('status', '>=', Payment::STATUS_PAID)->sum('abono');
        $credit->total_pagado = $totales;
        $credit->payments = $payments;

        return $this->data($credit);
    }

    public function pay(Request $request, $creditId)
    {
        $request->validate([
            'pays' => 'array|nullable',
            'pay' => 'nullable|numeric'
        ]);

        $returnPays = [];
        $c = Credit::find($creditId);
        if (!$c) {
            return $this->err("El crédito no existe");
        }
        $pendiente = $this->getMoraTotal($creditId);

        if ($request->has('pay')) {
            $pay = Payment::findOrFail($request->pay);
            if (!$pay) return $this->err("El pago no existe");
            if ($pay->status !== Payment::STATUS_ACTIVE) return $this->err("El pago ya fué procesado");

            $pay->status = Payment::STATUS_PAID;
            $pay->user_id = $request->user()->id;
            $pay->date_payment = Carbon::now();
            $pay->description = Carbon::now()->format('Y-m-d');
            $pay->pending = $pendiente;
            $pay->save();
            array_push($returnPays, $pay);

            return $this->data($returnPays);
        }

        // array de pagos
        $count = 0;
        foreach ($request->pays as $r) {
            $pay = Payment::find($r);
            if ($pay && $pay->status === Payment::STATUS_ACTIVE) {
                $pay->status = Payment::STATUS_PAID;
                $pay->user_id = $request->user()->id;
                $pay->date_payment = Carbon::now();
                if ($pendiente <= 0)
                    $pay->description = Carbon::now()->format('d-m-Y');
                else
                    $pay->description = "Pendiente $$pendiente";
                $pay->pending = $pendiente;
                $count++;
                $pay->save();
                array_push($returnPays, $pay);
            }
        }
        //ProcessMora::dispatchAfterResponse($c);
        return $this->data($returnPays);
    }

    public function abono(Request $request, $creditId)
    {
        $request->validate(['abono' => 'required', 'pay_id' => 'nullable']);
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

        if ($request->abono > $deuda) {//*Validamos que el abono que viene no sea mayor a la deuda
            return $this->err("El abono es mucho mayor a la deuda total de $$deuda");
        }

        if ($request->has('pay_id') && $request->get('pay_id') !== null) {
            return $this->advance($request->pay_id, $request->abono, $request->user()->id);
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
                'description' => "Pago adicional \n" . Carbon::now()->format('m-d-Y')
            ]);
            array_push($returnPays, $p);
            return $this->data($returnPays);
        }

        // El otro caso es cuando aun hay pagos, disponibles que actualizar
        // calculo de pagos aun no procesados|actualizados
        $abono = doubleval($request->abono);
        $cuota = $lastPay->abono;
        $userId = $request->user()->id;

        // si es menor, puede ser que sea un atraso
        if ($abono < $cuota) {
            DB::beginTransaction();
            $moraTotal = $this->getMoraTotal($creditId); // obtenemos la mora total para los cálculos
            if ($abono <= 0.0) {
                $status = Payment::STATUS_NO_PAID;
                $pendiente = ($moraTotal + $cuota);
                $desc = "No pagó"; // mora total
            } else {
                $pendiente = ($moraTotal + ($cuota - $abono));
                $desc = "Incompleto";
                $status = Payment::STATUS_PARTIAL_PAID;
            }

            // guardando el nuevo pago
            $p = $this->savePay($userId, $creditId, $abono, $pendiente, true, $status, $desc);
            array_push($returnPays, $p);
            DB::commit();
        } else if ($abono > $cuota) { // procesar adelanto
            return $this->procesarAdelanto($creditId, $abono, $cuota, $userId);
        } else {
            $moraTotal = $this->getMoraTotal($creditId);
            $p = $this->savePay($userId, $creditId, null, $moraTotal);
            array_push($returnPays, $p);
        }
        return $this->data($returnPays);
    }

    // functions
    public function advance($payId, $abono, $userId)
    {
        $returnPays = [];
        $p = Payment::findOrFail($payId);

        $now = Carbon::now()->format('Y-m-d');
        $limit = Carbon::parse($p->date);

        if ($limit->isBefore($now)) {
            return $this->err("No se puede adelantar un pago antiguo");
        }

        if ($p->status !== Payment::STATUS_PAID) {
            return $this->err("No puedes abonar en este pago");
        }

        // primero este abono se le agrega al pago que viene
        $p->abono = $p->abono + $abono;
        $p->save();
        array_push($returnPays, $p);

        $pays = Payment::where([
            ['credit_id', $p->credit_id],
            ['status', Payment::STATUS_ACTIVE]
        ])->get();

        foreach ($pays as $p) {
            if ($abono === 0) {
                break;
            }
            if ($abono >= $p->abono) { // 14 -> 12 | 10 | 8 | 6 | 4 | 2
                $abono = $abono - $p->abono; // reducimos el abono

                $p->abono = 0;
                $p->status = Payment::STATUS_PAID;
                $p->date_payment = Carbon::now();
                $p->description = "Adelanto $$p->total\n" . Carbon::now()->format("d-m-Y");
                $p->user_id = $userId;
                $p->save();
                array_push($returnPays, $p);
            }
            if ($abono < $p->abono) {
                $p->abono = $p->abono - $abono;
                $p->description = "Adelanto $" . ($abono) . " \n" . Carbon::now()->format("d-m-Y");
                $p->save();
                $abono = 0;
                array_push($returnPays, $p);
            }
        }

        return $this->ok($returnPays);
    }

    private function procesarAdelanto($creditId, $abono, $cuota, $userId)
    {
        $returnPays = [];
        $mora = Payment::where([
            ['credit_id', $creditId],
            ['status', '<>', Payment::STATUS_ACTIVE]
        ])->get();


        $totalIdeal = $mora->sum('total');
        $totalAbonado = $mora->sum('abono');
        $totalPendiente = $totalIdeal - $totalAbonado;


        if ($totalPendiente <= 0) {
            // no hay mora
            $p = $this->savePay($userId, $creditId, $abono);
            array_push($returnPays, $p);

            $adelanto = $abono - $cuota;
            $nPagos = $adelanto / $cuota; // numero de pagos

            for ($i = 0; $i < intval($nPagos); $i++) {
                $adelanto = $adelanto - $cuota;
                $p = $this->savePay($userId, $creditId, 0, 0, false, null,
                    "Adelanto $$cuota\n" . Carbon::now()->format("d-m-Y"));
                array_push($returnPays, $p);
            }
            if ($adelanto > 0) {
                $p = $this->savePay($userId, $creditId, ($cuota - $adelanto), 0, false,
                    Payment::STATUS_ACTIVE,
                    "Adelanto $" . ($cuota - $adelanto) . " \n" . Carbon::now()->format("d-m-Y"));
                array_push($returnPays, $p);
            }

            return $this->ok($returnPays);
        }

        $diferencia = $totalPendiente - ($abono - $cuota);

        if ($diferencia > 0) { // aun queda la mora | se guarda normal
            $p = $this->savePay($userId, $creditId, $abono, $diferencia);
            array_push($returnPays, $p);
            //"message" => "Con este adelanto queda $$diferencia pendientes aun",
            return $this->ok($returnPays);
        }

        if ($diferencia == 0) {
            // guardamos como un pago normal sin adelanto de ningún tipo
            $p = $this->savePay($userId, $creditId, $abono, 0, false, null, "Mora saldada\n" . Carbon::now()->format("d-m-Y"));
            array_push($returnPays, $p);
            //"message" => "Todas las deudas quedan saldadas",
            return $this->ok($returnPays);
        }

        if ($diferencia < 0) {
            // quiere decir que se puede considerar un adelanto lo sobrante

            // guardamos un pago normal aquí
            $p = $this->savePay($userId, $creditId, $abono, 0);
            array_push($returnPays, $p);

            $adelanto = ($abono - $totalPendiente) - $cuota;

            // guardamos el adelanto
            $nPagos = $adelanto / $cuota; // numero de pagos
            $abonoInicial = $adelanto;
            for ($i = 0; $i < intval($nPagos); $i++) {
                $adelanto = $adelanto - $cuota;
                $p = $this->savePay($userId, $creditId, 0, 0, false, null,
                    "Adelanto $$cuota\n" . Carbon::now()->format("d-m-Y"));
                array_push($returnPays, $p);
            }
            if ($adelanto > 0) {
                $p = $this->savePay($userId, $creditId, ($cuota - $adelanto), 0, false, Payment::STATUS_ACTIVE,
                    "Adelanto $" . ($cuota - $adelanto) . " \n" . Carbon::now()->format("d-m-Y"));
                array_push($returnPays, $p);
            }
            //"message" => "Tienes un valor de $$abonoInicial adelantado",
            return $this->ok($returnPays);
        }

        return $this->ok([
            $totalIdeal, $totalAbonado, $totalPendiente, $diferencia
        ]);
    }

    private function getMoraTotal($creditId)
    {
        $mora = Payment::where([
            ['credit_id', $creditId],
            ['status', '<>', Payment::STATUS_ACTIVE]
        ])->get();

        $totalIdeal = $mora->sum('total');
        $totalAbonado = $mora->sum('abono');
        return $totalIdeal - $totalAbonado;
    }

    private function getPending($creditId)
    {
        return Payment::select("pending")->where([
            ['credit_id', $creditId],
            ['status', '<>', Payment::STATUS_ACTIVE]
        ])->orderBy('id', 'desc')->first()->pending;
    }

    private function removeDescInLastPay($creditId)
    {
        $mora = Payment::where([
            ['credit_id', $creditId],
            ['status', '<>', Payment::STATUS_ACTIVE]
        ])->orderBy('id', 'desc')->limit(1)->first();

        if ($mora) {
            switch ($mora->status) {
                case Payment::STATUS_PARTIAL_PAID:
                    $mora->description = 'Incompleto';
                    break;
                case Payment::STATUS_NO_PAID:
                    $mora->description = "No pagó";
                    break;
            }
            $mora->save();

            return $mora;
        }

        return null;
    }

    private function savePay($userId, $creditId, $abono = null, $pending = 0, $mora = false, $status = null, $description = null)
    {
        $p = $this->lastPay($creditId);
        if ($status === null) {
            $p->status = Payment::STATUS_PAID;
        } else {
            $p->status = $status;
        }
        if ($abono !== null) {
            $p->abono = $abono;
        }
        if ($mora) {
            $p->mora = 1;
            if (!$description)
                $p->description = "COBRO CON MORA";
            else
                $p->description = $description;
        } else {
            if (!$description)
                $p->description = Carbon::now()->format('Y-m-d');
            else
                $p->description = $description;
        }
        $p->user_id = $userId;
        $p->date_payment = Carbon::now();
        $p->pending = $pending;
        $p->save();

        return $p;
    }

    private function lastPay($creditId)
    {
        return Payment::where([
            ['status', '=', Payment::STATUS_ACTIVE],
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
                'payments.mora',
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


        $payments = Payment::select('id', 'total', 'status', 'mora', 'date')
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

        if ($payment->save()) {
            return $this->success("Anulado exitosamente");
        }
        return $this->err('No se ha podido anular el pago');
    }

    private function calificar($creditId)
    {
        $c = Credit::findOrFail($creditId);
        $p = Person::findOrFail($c->person_id);

        $p->rank = ($p->rank - Payment::POINT_BY_MORA);

        $p->save();
    }

    /*

     if ($mora > 0) { // si tiene mora no se puede considerar un adelanto
                $p = $this->savePay($userId, $creditId, $abono);
                array_push($returnPays, $p);
            } else { // si no tiene mora, es un adelanto
                $nPagos = $abono / $cuota; // numero de pagos
                $abonoInicial = $abono;
                for ($i = 0; $i < intval($nPagos); $i++) {
                    if ($i === 0) {
                        $tempAbono = intval($nPagos) * $cuota;
                        $abono = $abono - $tempAbono;
                        $p = $this->savePay($userId, $creditId, $abonoInicial);
                        array_push($returnPays, $p);
                    } else {
                        $p = $this->savePay($userId, $creditId, 0);
                        array_push($returnPays, $p);
                    }
                }
                if ($abono > 0) {
                    $p = $this->savePay($userId, $creditId, ($cuota - $abono), false, Payment::STATUS_ACTIVE);
                    array_push($returnPays, $p);
                }
            }

     * */
}
