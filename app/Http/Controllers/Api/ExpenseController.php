<?php

namespace App\Http\Controllers\Api;

//use App\Employ;
use App\Expense;
use App\Http\Controllers\ApiController;
use App\Traits\UploadTrait;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseController extends ApiController
{
    use UploadTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('admin')->only(['info']);
    }

    public function index(Request $request)
    {
        $page = 1;
        $limit = 30;
        $wheres = [];

        // page
        if ($request->query('page'))
            $page = $request->query('page');

        // date
        if($request->query('from') && !$request->query('to'))
            array_push($wheres, ['expenses.date', '=', $request->query('from')]);

        // from to
        if($request->query('from') && $request->query('to')){
            array_push($wheres, ['expenses.date', '>=', $request->query('from')]);
            array_push($wheres, ['expenses.date', '<=', $request->query('to')]);
        }

        // limit
        if($request->query('limit')) {
            $limit = $request->query('limit');
        }

        // employ
        if($request->query('employ_id')) {
            //$employ = Employ::findOrFail($request->employ_id);
            //array_push($wheres, ['expenses.user_id', $employ->user->id]);
        }

        $offset = ($page-1) * $limit;

        // si no es administrador
        if(!$request->user()->isAdmin()) {
            array_push($wheres,['expenses.user_id', $request->user()->id]);
            array_push($wheres,['expenses.status', Expense::STATUS_ACTIVO ]);

            $expenses = Expense::join('users', 'users.id', 'expenses.user_id')
            ->join('persons', 'persons.id', 'users.person_id')
            ->select('expenses.id', 'expenses.monto', 'expenses.category', 'expenses.date','expenses.status',
                'expenses.image', 'expenses.description', 'persons.name')
            ->where($wheres)
            ->offset($offset)->limit($limit)
            ->orderBy('expenses.date', 'desc')->get();

            return $this->showAll($expenses);
        }

        // para el administrador
        else {
            $expenses = Expense::join('users', 'users.id', 'expenses.user_id')
            ->join('persons', 'persons.id', 'users.person_id')
            ->select('expenses.id', 'expenses.monto', 'expenses.category', 'expenses.date','expenses.status',
                'expenses.image', 'expenses.description',
                'persons.name'
               )
            ->where($wheres)
            ->offset($offset)->limit($limit)
            ->orderBy('expenses.date', 'desc')->get();


            $count = Expense::select('id')->where($wheres)->count();

            return $this->custom([
                'data'=> $expenses,
                'ok'=>true,
                'total'=> $count]);
        }
    }

    public function info(Request $request)
    {
        $wheres = [
            ['status', Expense::STATUS_ACTIVO]
        ];

        /*if ($request->query('employ_id')) {

            //$employ = Employ::findOrFail($request->employ_id);
            array_push($wheres, ['user_id', $employ->user->id]);
        }*/

        $now = Carbon::now();
        $weekStartDate = $now->startOfWeek()->format('Y-m-d');
        $weekEndDate = $now->endOfWeek(Carbon::SATURDAY)->format('Y-m-d');
        $mothStartDate = $now->firstOfMonth()->format('Y-m-d');
        $mothEndDate = $now->endOfMonth()->format('Y-m-d');

        $g_now = Expense::select('monto')->where($wheres)
            ->where('date', Carbon::now()->format('Y-m-d'))->sum('monto');

        $g_week = Expense::select('monto')->where($wheres)
            ->whereBetween('date', [$weekStartDate, $weekEndDate])->sum('monto');

        $g_month = Expense::select('monto')->where($wheres)
            ->whereBetween('date', [$mothStartDate, $mothEndDate])->sum('monto');

        return $this->ok([
            'today' => $g_now,
            'week'=> $g_week,
            'month' => $g_month
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'monto' => 'required|numeric',
            'category' => 'required|string|max:50',
            'date' => 'nullable|date_format:Y-m-d',
            'description' => 'nullable|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg',
            'employ_id' => 'nullable'
        ]);

        $expensive = new Expense();
        $expensive->monto = $request->get('monto');
        $expensive->category = Str::upper($request->get('category'));
        $expensive->description = $request->get('description');
        if ($request->employ_id && $request->user()->isAdmin())
        {
            //$employ = Employ::findOrFail($request->employ_id);
            //$expensive->user_id = $employ->user->id;
        }
        else
        {
           $expensive->user_id = $request->user()->id;
        }

        if($request->get('date')){
            $expensive->date = $request->get('date');
        } else {
            $expensive->date = Carbon::now()->format('Y-m-d');
        }

        if($request->hasFile('image')){
            $expensive->image = $this->uploadOne($request->file('image'), '/expense', 'public');
        }

        if($expensive->save())
            return $this->showOne($expensive);
        else
            return $this->err('No se ha podido procesar el gasto, contacte con soporte');
    }

    public function show($id)
    {
        $expense = Expense::join('users', 'users.id', 'expenses.user_id')
            ->join('persons', 'persons.id', 'users.person_id')
            ->select('expenses.*', 'persons.name')
            ->where('expenses.id', $id)->first();
        if (!$expense)
            return $this->err('No se encontró el gasto');
        else
            return $this->ok($expense);
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id, Request $request){
        $request->validate([
            'description' => 'required|string|max:100'
        ], ['description.required' => 'Ingrese un motivo para anular el gasto']);

        $e = Expense::findOrFail($id);

        if($request->user()->id !== $e->user_id && !$request->user()->hasRole(User::ADMIN_ROLE)) {
            return $this->err('No tienes permisos para realizar esta acción!');
        }

        $e->status = Expense::STATUS_CANCEL;
        $e->description = $request->description;

        if($e->save()) {
            return $this->success('Gasto anulado con éxito');
        }

        return $this->err('No ha podido anular el gasto');
    }
}
