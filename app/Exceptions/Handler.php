<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponse;
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        if ($exception instanceof \League\OAuth2\Server\Exception\OAuthServerException) {
            $transPayload = trans('oauth.' . $exception->getErrorType());

            if (is_array($transPayload)) { // can be remove if you translate all error types!
                $exception->setPayload($transPayload);
            }
        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if (!$request->expectsJson())
            return parent::render($request, $exception);


        if($exception instanceof ValidationException){
            return $this->invalidJson($request, $exception);
        }
        if($exception instanceof ModelNotFoundException) {
            return $this->err("No se encontró resultados", 404);
        }
        if($exception instanceof NotFoundHttpException) {
            return $this->err("No se encontró la ruta especificada", 404);
        }
        if($exception instanceof MethodNotAllowedHttpException) {
            return $this->err("El método especificado no es válida", 405);
        }
        if($exception instanceof  AuthenticationException) {
            return $this->err('No autenticado, Inicie sesión para continuar!', 401);
        }
        if($exception instanceof  AuthorizationException) {
            return $this->err('No posee permisos para ejecutar esta acción!', 403);
        }
        if($exception instanceof HttpException) {
            return $this->err("El método especificado no es válido", 405);
        }

        if(config('app.debug')){
            return parent::render($request, $exception);
        }

        return $this->err('Falla inesperada, intente más tarde', 500);
    }

    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos, verifique!',
            'errors' => (array_values($exception->errors())),
        ], $exception->status);
    }
}
