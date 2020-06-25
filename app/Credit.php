<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    const STATUS_ANULADO = 0;
    const STATUS_ACTIVO = 1;
    const STATUS_FINALIZADO = 2;

    const PLAZO_SEMANAL = 'SEMANAL';
    const PLAZO_QUINCENAL = 'QUINCENAL';
    const PLAZO_MENSUAL = 'MENSUAL';
    const PLAZO_MES_Y_MEDIO = 'MES_Y_MEDIO';
    const PLAZO_OOS_MESES = 'DOS_MESES';

    const COBRO_DIARIO = 'DIARIO';
    const COBRO_SEMANAL = 'SEMANAL';
    const COBRO_QUINCENAL = 'QUINCENAL';
    const COBRO_MENSUAL = 'MENSUAL';

    public $timestamps = false;

    public $_fInicio;
    public $_fFin;
    public $_plazo;
    public $_cobro;
    public $_totalUtilidad;
    public $_total;
    public $_pagosDe;
    public $_pagosDeLast;
    public $_monto;
    public $_utilidad;

    public $_nPagos;
    public $pays = [];

    public function calcular()
    {

        if (!$this->_monto || !$this->_utilidad) return;
        if ($this->_monto <= 0) return;
        $this->_totalUtilidad = $this->_monto * ($this->_utilidad / 100);
        $this->_total = $this->_monto + $this->_totalUtilidad;
        $this->_pagosDeLast = 0;

        if ($this->_plazo == null || $this->_cobro == null)
            return;

        $this->_nPagos = intval($this->diasPlazo() / $this->diasCobro());
        $this->_pagosDe = round(($this->_total / $this->_nPagos), 2);

        $totalIdeal = $this->_pagosDe * $this->_nPagos;

        if ($totalIdeal !== $this->_total) {
            if ($totalIdeal < $this->_total) {
                $diferencia = $this->_total - $totalIdeal;
                $this->_pagosDeLast = $this->_pagosDe + $diferencia;
            } else {
                $diferencia = $totalIdeal - $this->_total;
                $this->_pagosDeLast = $this->_pagosDe - $diferencia;
            }
        }

        $this->calculaFechas();
        $this->parseToModel();
    }

    public function diasPlazo()
    {
        if ($this->_plazo == null) return 0;
        switch ($this->_plazo) {
            case self::PLAZO_SEMANAL:
                return $this->diasCobro() == 1 ? 6 : 7;
            case self::PLAZO_QUINCENAL:
                return 15;
            case self::PLAZO_MENSUAL:
                return 30;
            case self::PLAZO_MES_Y_MEDIO:
                return 45;
            case self::PLAZO_OOS_MESES:
                return 60;
        }
    }

    public function diasCobro()
    {
        if ($this->_cobro == null) return 0;
        switch ($this->_cobro) {
            case self::COBRO_DIARIO:
                return 1;
            case self::PLAZO_SEMANAL:
                return 7;
            case self::COBRO_QUINCENAL:
                return 15;
            case self::COBRO_MENSUAL:
                return 30;
        }
    }

    public function calculaFechas()
    {
        if ($this->_cobro === null || $this->_plazo === null) {
            $this->_fFin = null;
            return;
        }
        $diasPlazo = $this->diasPlazo();
        if ($this->_cobro === self::COBRO_DIARIO) {
            $diasPlazo = $diasPlazo - 1;
            $fIni = Carbon::now()->addDay();
            $fEnd = Carbon::now()->addDay();
            //adding initial date
            $this->pays[] = $fIni->format('Y-m-d');

            for ($i = 0; $i < $diasPlazo; $i++) {
                $newD = $this->remplaceWeekend($fEnd->addDays(1));
                $this->pays[] = $newD->format('Y-m-d');
                $fEnd = $newD;
            }

            $this->_fInicio = $fIni;
            $this->_fFin = $fEnd;
        } else {
            $this->_fInicio = Carbon::now();
            $this->_fFin = Carbon::now();
            for ($i = 0; $i < $this->_nPagos; $i++) {
                $newD = null;
                switch ($this->_cobro) {
                    case self::COBRO_SEMANAL:
                        $newD = $this->remplaceWeekend($this->_fFin->addDays(7));
                        break;
                    case self::COBRO_QUINCENAL:
                        $newD = $this->remplaceWeekend($this->_fFin->addDays(14));
                        break;
                    case self::COBRO_MENSUAL:
                        $newD = $this->remplaceWeekend($this->_fFin->addMonth());
                        break;
                }
                $this->pays[] = $newD->format('Y-m-d');
                $this->_fFin = $newD;
            }
        }
    }

    public function remplaceWeekend($date)
    {
        $d = Carbon::parse($date);
        if ($d->isSunday()) {
            $d = $d->addDays(1);
        }
        return $d;
    }

    public function parseToModel()
    {
        $this->attributes['f_inicio'] = $this->_fInicio;
        $this->attributes['f_fin'] = $this->_fFin;
        $this->attributes['plazo'] = $this->_plazo;
        $this->attributes['cobro'] = $this->_cobro;
        $this->attributes['total_utilidad'] = $this->_totalUtilidad;
        $this->attributes['total'] = $this->_total;
        $this->attributes['pagos_de'] = $this->_pagosDe;
        $this->attributes['pagos_de_last'] = $this->_pagosDeLast;
        $this->attributes['monto'] = $this->_monto;
        $this->attributes['utilidad'] = $this->_utilidad;
        $this->attributes['n_pagos'] = $this->_nPagos;

        if ($this->_pagosDeLast === 0) {
            $description = $this->_nPagos . ' pago(s) de ' . $this->_pagosDe;
        } else {
            $description = ($this->_nPagos - 1) . ' pago(s) de ' . $this->_pagosDe . ' + un pago de ' . $this->_pagosDeLast;
        }
        $description .= ' | Plazo: ' . $this->_plazo . ', Cobro: ' . $this->_cobro;

        $this->attributes['description'] = $description;
    }

    public function person()
    {
        return $this->belongsTo('App\Person');
    }
    /*public static function diasInicio($cobro, $init = null) {
        $dias = Credit::diasCobro($cobro);
        if ($init === null)
            return Credit::addDays($dias);
        else
            return Credit::addDays($dias, $init);
    }

    public static function diasCobro($cobro){
        switch ($cobro) {
            case self::COBRO_DIARIO:
                return 1;
            case self::PLAZO_SEMANAL:
                return 7;
            case self::COBRO_QUINCENAL:
                return 15;
            case self::COBRO_MENSUAL:
                return 30;
        }
    }

    public static function addDays($days = 1, $date = null) {
        if(!$date) {
            $d = Carbon::now();
        }
        else {
            $d = Carbon::parse($date);
        }
        switch($days) {
            case 1:
                if($d->isSaturday()){
                    $d = $d->addDays(2);
                }
                else if ($d->isSunday()) {
                    $d = $d->addDays(1);
                }
                else if(!$d->isSaturday() && !$d->isSunday()) {
                    $d = $d->addDay();
                }
                break;
            case 7:
                $dateInit = self::remplaceWeekend($d);
                $d = $dateInit->addDays(7);
                break;
            case 15:
                $dateInit = self::remplaceWeekend($d);
                $d = $dateInit->addDays(15);
                break;
            case 30:
                $dateInit = self::remplaceWeekend($d);
                $d = $dateInit->addDays(30);
                break;
        }
        return self::remplaceWeekend($d);
    }

    public static function remplaceWeekend($date)
    {
        $d = Carbon::parse($date);
        if($d->isSunday()) {
            $d = $d->addDays(1);
        }

        return $d;
    }

    public static function dateEnd($days=7, $date) {
        $d = Carbon::parse($date);

        if($d->isSaturday()){
            $d = $d->addDays(2);
        }

        for($i = 1; $i<$days; $i++) {
            $d = $d->addDay();
            if($d->isSaturday()){
                $d = $d->addDay();
            }
        }
        return $d;
    }

    public static function diasPlazo($plazo) {
        switch ($plazo) {
            case self::PLAZO_SEMANAL:
                return 6;
            case self::PLAZO_QUINCENAL:
                return 12;
            case self::PLAZO_MENSUAL:
                return 30;
            case self::PLAZO_MES_Y_MEDIO:
                return 45;
            case self::PLAZO_OOS_MESES:
                return 60;
        }
    }*/

}
