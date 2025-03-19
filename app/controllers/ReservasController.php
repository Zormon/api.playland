<?php

namespace App\Controllers;

use App\Models\Reserva;
use App\Models\Evento;
use Lib\Err;
use Lib\Cache;

use App\Interfaces\ItemController;
use App\traits\ValidateRequestData;
use Illuminate\Database\QueryException;

class ReservasController extends Controller implements ItemController {
    use ValidateRequestData;

    public array $fields = [
        'equipo_id' => 'numeric',
        'evento_id' => 'numeric',
        'dia' => 'date',
        'entrada_id' => 'numeric',
        'pagado' => 'in:[no,taquilla,online]',
    ];

    private const array CACHE_KEYS = [
        'evento' => 'reservas:@/json',
        'adulto' => 'reservas:@_#/json',
    ];

    public function all() {
        if (!$evid = request()->get('event')) {
            response()->exit(Err::get('MISSING_EVENT_PARAM'), 400);
        }

        $cacheKey = auth()->user()->is('adulto') ?
            $this->adCacheKey($evid, auth()->user()->id) :
            $this->evCacheKey($evid);

        // Si se solicita sin caché, borrar la caché.
        if (request()->get('nocache')) {
            Cache::delete($cacheKey);
        }

        if (!$json = Cache::get($cacheKey)) {
            $reservas = auth()->user()->is('adulto') ?
                Reserva::where('evento_id', $evid)->whereHas('equipo', function ($query) {
                    $query->where('adulto_id', auth()->user()->id);
                })->get() :
                Reserva::where('evento_id', $evid)->get();

            $json = json_encode($reservas);
            Cache::set($cacheKey, $json, 3600 * 24 * 30); // 30 días
        }

        response()->withHeader('Content-Type', 'application/json');
        response()->custom($json, 200);
    }

    public function get(int $id) {
        if (!$reserva = Reserva::with(['equipo', 'evento', 'entrada'])->find($id)) {
            response()->exit(null, 404);
        }

        // Si el usuario solo puede ver sus propias reservas, verificar que sea el dueño
        if (auth()->user()->is('adulto')) {
            $this->mustOwnReserva($reserva);
        }

        response()->json($reserva);
    }

    public function create() {
        // @TODO:  La taquilla solo puede crear reservas del evento actual
        $reservaData = $this->getItemData(request());
        $reserva = new Reserva($reservaData);

        // Si el usuario solo puede gestionar sus propias reservas, verificar que sea el dueño del equipo
        if (auth()->user()->is('adulto')) {
            $this->mustOwnEquipo($reservaData['equipo_id']);
        }

        try {
            $reserva->save();
        } catch (QueryException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 201);
    }

    public function put(int $id) {
        $reservaData = $this->getItemData(request(), true);

        if (!$reserva = Reserva::find($id)) {
            response()->exit(null, 404);
        }

        $evento = Evento::find($reserva->evento_id);
        if (!$evento ) {
            response()->exit(Err::get('EVENT_NOT_FOUND'), 404);
        }

        // Si el usuario solo puede gestionar sus propias reservas, verificar que sea el dueño
        // <De momento solo acceden aqui los admins>
        if (auth()->user()->is('adulto')) {
            $this->mustOwnReserva($reserva);

            // Si está cambiando el equipo, verificar que también sea dueño del nuevo equipo
            if (isset($reservaData['equipo_id']) && $reservaData['equipo_id'] != $reserva->equipo_id) {
                $this->mustOwnEquipo($reservaData['equipo_id']);
            }
        }

        // La taquilla solo puede editar reservas del evento actual
        if (auth()->user()->is('taquilla') && !$evento->isCurrent()) {
            response()->exit(Err::get('EVENT_NOT_CURRENT'), 403);
        }

        // Make sure we're working with a Reserva model instance
        if (!$reserva instanceof Reserva) {
            $reserva = Reserva::find($id);
            if (!$reserva) {
                response()->exit(null, 404);
            }
        }

        try {
            $reserva->update($reservaData);
        } catch (QueryException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    public function delete(int $id) {
        // <De momento solo acceden aqui los admins>
        if (!$reserva = Reserva::find($id)) {
            response()->exit(null, 404);
        }

        // Si el usuario solo puede gestionar sus propias reservas, verificar que sea el dueño
        if (auth()->user()->is('adulto')) {
            $this->mustOwnReserva($reserva);
        }

        try {
            $reserva->delete();
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        }

        response()->plain(null, 204);
    }

    /**
     * Comprueba que el usuario es dueño de la reserva a través del equipo, si no lo es, devuelve un error 403.
     *
     * @param Reserva $reserva La reserva a comprobar
     * @return void
     */
    private function mustOwnReserva(Reserva|\stdClass $reserva) {
        $userId = auth()->user()->id;
        $equipo = $reserva->equipo;

        if (!$equipo || $equipo->adulto_id !== $userId) {
            response()->exit(null, 403);
        }
    }

    /**
     * Comprueba que el usuario es dueño del equipo, si no lo es, devuelve un error 403.
     *
     * @param int $equipoId El ID del equipo a comprobar
     * @return void
     */
    private function mustOwnEquipo(int $equipoId) {
        $userId = auth()->user()->id;
        $equipo = \App\Models\Equipo::find($equipoId);

        if (!$equipo || $equipo->adulto_id !== $userId) {
            response()->exit(null, 403);
        }
    }

    private function evCacheKey($id) {
        return str_replace('@', $id, self::CACHE_KEYS['evento']);
    }

    private function adCacheKey($evid, $uid) {
        return str_replace(['@', '#'], [$evid, $uid], self::CACHE_KEYS['adulto']);
    }
}
