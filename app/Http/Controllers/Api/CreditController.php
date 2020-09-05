<?php

namespace App\Http\Controllers\Api;

use App\Credit;
use App\Http\Controllers\ApiController;
use App\Payment;
use App\Person;
use App\Traits\UploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends ApiController
{
    use UploadTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    //*Http
    public function store(Request $request)
    {//store a new credit

        $request->validate([
            'person_id' => 'required',
            'monto' => 'required',
            'utilidad' => 'required',
            'plazo' => 'required|in:' . $this->getValidTerms(),
            'cobro' => 'required|in:' . $this->getValidPays(),
            'prenda_img' => 'nullable|image|mimes:jpeg,png,jpg',
            'prenda_detail' => 'nullable|string|max:150',
            'f_inicio' => 'nullable|date_format:Y-m-d'
        ], $this->messages());


        if(!Person::find($request->get('person_id'))) {
            return $this->err('El cliente seleccionado no existe');
        }

        DB::beginTransaction();

        if ($this->hasActiveCredit($request->get('person_id'))) {
            return $this->err("Este cliente ya tiene un crédito activo");
        }

        $c = new Credit();
        $c->_monto = $request->get('monto');
        $c->_utilidad = $request->get('utilidad');
        $c->_plazo = $request->get('plazo');
        $c->_cobro = $request->get('cobro');
        $c->status = Credit::STATUS_ACTIVO;

        $c->person_id = $request->get('person_id');
        $c->user_id = $request->user()->id;
        $c->zone_id = $request->user()->zone_id;
        $c->guarantor_id = $request->guarantor_id;

        if ($request->hasFile('prenda_img')) {
            $c->prenda_img = $this->uploadOne($request->file('prenda_img'), '/prenda', 'public');
        }
        $c->prenda_detail = $request->get('prenda_detail');

        // CALCULO DE VALORES Y FECHAS
        $c->calcular();

        if ($c->save()) {
            //$this->storePayments($c->id, $calc, $c->f_inicio, $c->f_fin, $c->cobro);
            $this->setPayments($c);
            DB::commit();
            return $this->showOne($c);
        } else {
            DB::rollBack();
            return $this->err("No se ha podido procesar el crédito");
        }
    }

    public function showForClient($clientId)
    {
        $c = Person::findOrFail($clientId);

        $credits = Credit::where([
            ['person_id', $c->id],
        ])->select('f_inicio', 'f_fin', 'mora', 'total', 'plazo', 'cobro', "status")->get();

        return $this->showAll($credits);
    }

    //*Validations
    private function getValidTerms()
    {  // plazos validos
        return
            Credit::PLAZO_SEMANAL . ',' .
            Credit::PLAZO_QUINCENAL . ',' .
            Credit::PLAZO_MENSUAL . ',' .
            Credit::PLAZO_MES_Y_MEDIO . ',' .
            Credit::PLAZO_OOS_MESES . '';
    }

    private function getValidPays()
    {
        return
            Credit::COBRO_DIARIO . ',' .
            Credit::COBRO_MENSUAL . ',' .
            Credit::COBRO_SEMANAL . ',' .
            Credit::COBRO_QUINCENAL;
    }

    private function messages()
    {
        return [
            'person_id.required' => 'No se ha proporcionado un cliente',
            'plazo.required' => 'Es necesario definir el plazo',
            'plazo.in' => 'El plazo no es válido',
            'cobro.required' => 'El necesario definir el tipo de cobro',
            'monto.required' => 'El monto es necesario',
            'utilidad.required' => 'El porcentaje de ganancias es requerido',
            'cobro.in' => 'El tipo de cobro no es válido'
        ];
    }

    //*Functions
    private function hasActiveCredit($person_id)
    {
        $c = Credit::select('id')->where([
            ['person_id', $person_id],
            ['status', Credit::STATUS_ACTIVO]
        ])->first();

        return ($c != null);
    }

    public function setPayments(Credit $c)
    {
        $nPagos = count($c->pays);

        if ($c->_pagosDeLast !== 0) {
            $nPagos = $nPagos - 1;
        }

        for ($i = 0; $i < $nPagos; $i++) {
            Payment::create([
                'credit_id' => $c->id,
                'total' => $c->_pagosDe,
                'abono' => $c->_pagosDe,
                'status' => Payment::STATUS_ACTIVE,
                'date' => $c->pays[$i],
                'description' => 'Pendiente'
            ]);
        }

        if ($c->_pagosDeLast !== 0) {
            $np = count($c->pays);
            Payment::create([
                'credit_id' => $c->id,
                'total' => $c->_pagosDeLast,
                'abono' => $c->_pagosDeLast,
                'status' => Payment::STATUS_ACTIVE,
                'date' => $c->pays[$np - 1],
                'description' => 'Pendiente'
            ]);
        }
    }
}
