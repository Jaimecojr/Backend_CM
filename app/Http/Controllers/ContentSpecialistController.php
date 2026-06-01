<?php

namespace App\Http\Controllers;

use App\Models\ContentSpecialist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContentSpecialistController extends Controller
{
    public function index()
    {
        $specialists = ContentSpecialist::orderBy('position')->get();

        return response()->json([
            'message' => 'Especialistas obtenidos correctamente',
            'data'    => $specialists,
        ]);
    }

    public function store(Request $request)
    {
        if (ContentSpecialist::count() >= 4) {
            return response()->json([
                'message' => 'No se pueden agregar más de 4 especialistas',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'photo'     => 'required|image|mimes:jpeg,png,webp|max:2048',
            'position'  => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('photo');
        $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('content_specialists', $filename, 'public');

        $specialist = ContentSpecialist::create([
            'name'           => $request->name,
            'specialty'      => $request->input('specialty'),
            'photo'          => $path,
            'photo_filename' => $filename,
            'position'       => $request->position,
        ]);

        return response()->json([
            'message' => 'Especialista creado correctamente',
            'data'    => $specialist,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $specialist = ContentSpecialist::find($id);

        if (!$specialist) {
            return response()->json(['message' => 'Especialista no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'photo'     => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'position'  => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('photo')) {
            Storage::disk('public')->delete($specialist->photo);
            $file = $request->file('photo');
            $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('content_specialists', $filename, 'public');
            $specialist->photo = $path;
            $specialist->photo_filename = $filename;
        }

        $specialist->name      = $request->name;
        $specialist->specialty = $request->input('specialty');
        $specialist->position  = $request->position;
        $specialist->save();

        return response()->json([
            'message' => 'Especialista actualizado correctamente',
            'data'    => $specialist,
        ]);
    }

    public function destroy($id)
    {
        $specialist = ContentSpecialist::find($id);

        if (!$specialist) {
            return response()->json(['message' => 'Especialista no encontrado'], 404);
        }

        Storage::disk('public')->delete($specialist->photo);
        $specialist->delete();

        return response()->json(['message' => 'Especialista eliminado correctamente']);
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items'            => 'required|array',
            'items.*.id'       => 'required|integer|exists:content_specialists,id',
            'items.*.position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->items as $item) {
            ContentSpecialist::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }

    public function publicIndex()
    {
        $specialists = ContentSpecialist::orderBy('position')
            ->get(['id', 'name', 'specialty', 'photo', 'position']);

        $data = $specialists->map(fn ($s) => [
            'id'        => $s->id,
            'name'      => $s->name,
            'specialty' => $s->specialty,
            'photo'     => $s->photo,
            'position'  => $s->position,
        ]);

        return response()->json([
            'message' => 'Especialistas obtenidos correctamente',
            'data'    => $data,
        ]);
    }
}
