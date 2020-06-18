<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Payment;
use App\Person;
use App\Traits\UploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientController extends ApiController
{
    use UploadTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|max:100',
            'phones_a' => 'nullable|string|max:13',
            'phones_b' => 'nullable|string|max:13',
            'address_a' => 'nullable|string|max:100',
            'address_b' => 'nullable|string|max:100',
            'fb'=> 'nullable'
        ], $this->messages());

        if (!$request->has('phone_a') && !$request->has('phone_b')) {
            return $this->err('Ingrese al menos un teléfono');
        }

        if (!$request->has('address_a') && !$request->has('address_b')) {
            return $this->err('Ingrese al menos una dirección');
        }

        $p = new Person();
        $p->name = Str::upper($request->name);
        $p->fb = $request->fb;
        $p->phone_a = $request->phone_a;
        $p->phone_b = $request->phone_b;
        // dirección personal
        $p->address_a = $request->address_a;
        $p->city_a = $request->city_a;
        $p->lat_a = $request->lat_a;
        $p->lng_a = $request->lng_a;
        // dirección de trabajo
        $p->address_b = $request->address_b;
        $p->city_b = $request->city_b;
        $p->lat_b = $request->lat_b;
        $p->lng_b = $request->lng_b;

        if($request->hasFile('ref_a')){
            $p->ref_a = $this->uploadOne($request->file('ref_a'), '/client', 'public');
        }

        if($request->hasFile('ref_b')){
            $p->ref_b = $this->uploadOne($request->file('ref_b'), '/client', 'public');
        }

        $p->user_id = $request->user()->id;

        $p->save();

        return $this->showOne($p);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:100',
            'phones_a' => 'nullable|string|max:13',
            'phones_b' => 'nullable|string|max:13',
            'address_a' => 'nullable|string|max:100',
            'address_b' => 'nullable|string|max:100',
            'fb' => 'nullable'
        ], $this->messages());

        if (!$request->has('phone_a') && !$request->has('phone_b')) {
            return $this->err('Ingrese al menos un teléfono');
        }

        if (!$request->has('address_a') && !$request->has('address_b')) {
            return $this->err('Ingrese al menos una dirección');
        }

        $p = Person::findOrFail($id);
        if ($request->name)
            $p->name = Str::upper($request->name);
        if ($request->fb)
            $p->fb = $request->fb;
        if ($request->phone_a)
            $p->phone_a = $request->phone_a;
        if ($request->phone_b)
            $p->phone_b = $request->phone_b;
        // dirección personal
        if ($request->address_a)
            $p->address_a = $request->address_a;
        if ($request->city_a)
            $p->city_a = $request->city_a;
        // latitud y longitud de casa
        if ($request->lat_a && $request->lng_a) {
            $p->lat_a = $request->lat_a;
            $p->lng_a = $request->lng_a;
        }
        // dirección de trabajo
        if ($request->address_b)
            $p->address_b = $request->address_b;
        if ($request->city_b)
            $p->city_b = $request->city_b;
        if ($request->lat_b && $request->lng_b) {
            $p->lat_b = $request->lat_b;
            $p->lng_b = $request->lng_b;
        }

        if ($request->hasFile('ref_a')) {
            if (Storage::disk('public')->exists($p->ref_a)) {
                Storage::disk('public')->delete($p->ref_a);
            }
            $p->ref_a = $this->uploadOne($request->file('ref_a'), '/client', 'public');
        }

        if ($request->hasFile('ref_b')) {
            if (Storage::disk('public')->exists($p->ref_b)) {
                Storage::disk('public')->delete($p->ref_b);
            }
            $p->ref_b = $this->uploadOne($request->file('ref_b'), '/client', 'public');
        }

        if ($p->save()) {
            return $this->showOne($p);
        }

        return $this->err("Ocurrió un error al actualizar el cliente");
    }

    public function list(Request $request) {
        $limit = 20;
        // limite
        if ($request->query('limit')) { $limit = $request->query('limit');}

        $p = Person::where([
            ['user_id', $request->user()->id],
        ])
            ->select($this->selectFields())
            ->limit($limit)
            ->orderBy('created_at', 'desc')->get();

        return $this->showAll($p);
    }

    public function search(Request $request) {
        if ($request->has('data')) {
            $data = Str::upper($request->query('data'));

            $p = Person::where('name', 'like', "$data%")
                ->orWhere('phone_a', 'like', "$data%")
                ->orWhere('phone_b', 'like', "$data%")
                ->select($this->selectFields())
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get();

            return $this->showAll($p);
        }
        return $this->showAll(null);
    }

    public function history(Request $request)
    {
        $persons = Person::leftJoin('credits', 'credits.person_id', 'persons.id')
            ->where([
                ['persons.user_id', $request->user()->id],
            ])
            ->select('persons.id', 'persons.name', 'persons.rank', 'persons.city_a', 'persons.address_a',
                'persons.city_b', 'persons.address_b', 'persons.status', 'credits.cobro',
                'credits.id as credit', 'credits.f_inicio', 'credits.f_fin', 'credits.total')
            ->orderBy('credits.created_at', 'desc')
            ->limit(20)
            ->get();

        for ($i = 0; $i < count($persons); $i++) {
            if ($persons[$i]->credit !== null)
                $persons[$i]->paid = Payment::where('credit_id', $persons[$i]->credit)
                    ->where('status', Payment::STATUS_PAID)->sum('total');
            else
                $persons[$i]->paid = 0;
        }

        return $this->showAll($persons);
    }

    public function show($id)
    {
        $c = Person::findOrFail($id);
        if ($c->ref_a)
            $c->ref_a = url('/api/image/' . $c->ref_a);
        if ($c->ref_b)
            $c->ref_b = url('/api/image/' . $c->ref_b);
        return $this->showOne($c);
    }

    //* Functions

    public function selectFields()
    {
        return ['id', 'name', 'address_a', 'address_b',
            'status', 'rank', 'phone_a', 'phone_b', 'city_a', 'city_b'];
    }

    // Messages Error
    public function messages()
    {
        return [
            'name.required' => 'El nombre del cliente es requerido',
        ];
    }
}

