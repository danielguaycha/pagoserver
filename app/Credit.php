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

    public $f_inicio;
    public $f_fin;
    public $plazo;
    public $cobro;
    public $total_utilidad;
    public $total;
    public $pagos_de;
    public $pagos_de_last;
    public $monto;
    public $utilidad;

    public $n_pagos;
    public $pays = [];

    public function calcular()
    {

        if (!$this->monto || !$this->utilidad) return;
        if ($this->monto <= 0) return;
        $this->total_utilidad = $this->monto * ($this->utilidad / 100);
        $this->total = $this->monto + $this->total_utilidad;
        $this->pagos_de_last = 0;

        if ($this->plazo == null || $this->cobro == null)
            return;

        $this->n_pagos = intval($this->diasPlazo() / $this->diasCobro());
        $this->pagos_de = round(($this->total / $this->n_pagos), 2);

        $totalIdeal = $this->pagos_de * $this->n_pagos;

        if ($totalIdeal !== $this->total) {
            if ($totalIdeal < $this->total) {
                $diferencia = $this->total - $totalIdeal;
                $this->pagos_de_last = $this->pagos_de + $diferencia;
            } else {
                $diferencia = $totalIdeal - $this->total;
                $this->pagos_de_last = $this->pagos_de - $diferencia;
            }
        }

        $this->calculaFechas();
        $this->parseToModel();
    }

    public function diasPlazo()
    {
        if ($this->plazo == null) return 0;
        switch ($this->plazo) {
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
        if ($this->cobro == null) return 0;
        switch ($this->cobro) {
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
        if ($this->cobro === null || $this->plazo === null) {
            $this->f_fin = null;
            return;
        }
        $diasPlazo = $this->diasPlazo();
        if ($this->cobro === self::COBRO_DIARIO) {
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

            $this->f_inicio = $fIni;
            $this->f_fin = $fEnd;
        } else {
            $this->f_inicio = Carbon::now();
            $this->f_fin = Carbon::now();
            for ($i = 0; $i < $this->n_pagos; $i++) {
                $newD = null;
                switch ($this->cobro) {
                    case self::COBRO_SEMANAL:
                        $newD = $this->remplaceWeekend($this->f_fin->addDays(7));
                        break;
                    case self::COBRO_QUINCENAL:
                        $newD = $this->remplaceWeekend($this->f_fin->addDays(14));
                        break;
                    case self::COBRO_MENSUAL:
                        $newD = $this->remplaceWeekend($this->f_fin->addMonth());
                        break;
                }
                $this->pays[] = $newD->format('Y-m-d');
                $this->f_fin = $newD;
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
        $this->attributes['f_inicio'] = $this->f_inicio;
        $this->attributes['f_fin'] = $this->f_fin;
        $this->attributes['plazo'] = $this->plazo;
        $this->attributes['cobro'] = $this->cobro;
        $this->attributes['total_utilidad'] = $this->total_utilidad;
        $this->attributes['total'] = $this->total;
        $this->attributes['pagos_de'] = $this->pagos_de;
        $this->attributes['pagos_de_last'] = $this->pagos_de_last;
        $this->attributes['monto'] = $this->monto;
        $this->attributes['utilidad'] = $this->utilidad;
        $this->attributes['n_pagos'] = $this->n_pagos;

        if ($this->pagos_de_last === 0) {
            $description = $this->n_pagos . ' pago(s) de ' . $this->pagos_de;
        } else {
            $description = ($this->n_pagos - 1) . ' pago(s) de ' . $this->pagos_de . ' + un pago de ' . $this->pagos_de_last;
        }
        $description .= ' | Plazo: ' . $this->plazo . ', Cobro: ' . $this->cobro;

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
