<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
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

        if ($request->is('api/*')) {
            // This will replace our 404 response with a JSON response.
            if ($exception instanceof ModelNotFoundException) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Resource item not found.'
                ], 404);
            }

            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Resource item not found.'
                ], 404);
            }

            if ($exception instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Resource item not found.'
                ], 405);
            }

            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Unauthenticated request.'
                ], 401);
            }

            if ($exception instanceof TokenPermissionsMismatchException) {
                return response()->json([
                    'error' => 1,
                    'message' => $exception->getMessage()
                ], 403);
            }
        }
        return parent::render($request, $exception);
    }
}
