<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request)
    {
        $bookmarks = session('bookmark', [
            'user_id' => 0,
            'career_id' => 0,
            'subject_id' => 0,
            'cycle_id' => date('Y'),
        ]);

        return response()->json($bookmarks);
    }

    // Actualizar un bookmark (marcar una entidad)
    public function update(Request $request)
    {
        $type = $request->input('type'); // 'user_id', 'career_id', 'subject_id', 'cycle_id'
        $id = $request->input('id');    // ID del elemento marcado

        if (!in_array($type, ['user_id', 'career_id', 'subject_id', 'cycle_id'])) {
            return response()->json(['error' => 'Tipo inválido'], 400);
        }

        // Obtener el bookmark actual de la sesión
        $bookmarks = session('bookmark', [
            'user_id' => 0,
            'career_id' => 0,
            'subject_id' => 0,
            'cycle_id' => date('Y'),
        ]);

        // Actualizar el bookmark para el tipo específico
        $bookmarks[$type] = $id;

        // Guardar el bookmark actualizado en la sesión
        session(['bookmark' => $bookmarks]);

        return response()->json($bookmarks);
    }

    // Limpiar todos los bookmarks
    public function clear()
    {
        session()->forget('bookmark');
        return response()->json(['message' => 'Bookmarks eliminados']);
    }
}