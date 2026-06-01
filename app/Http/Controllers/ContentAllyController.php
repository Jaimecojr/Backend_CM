<?php

namespace App\Http\Controllers;

use App\Models\ContentAlly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContentAllyController extends Controller
{
    public function index()
    {
        $allies = ContentAlly::orderBy('position')->get();

        return response()->json([
            'message' => 'Aliados obtenidos correctamente',
            'data'    => $allies,
        ]);
    }

    public function store(Request $request)
    {
        if (ContentAlly::count() >= 6) {
            return response()->json([
                'message' => 'No se pueden agregar más de 6 aliados estratégicos',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'image'    => 'required|image|mimes:jpeg,png,webp|max:2048',
            'url'      => 'required|string|url|max:255',
            'position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');
        $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('content_allies', $filename, 'public');

        $ally = ContentAlly::create([
            'image'          => $path,
            'image_filename' => $filename,
            'url'            => $request->input('url'),
            'position'       => $request->input('position'),
        ]);

        return response()->json([
            'message' => 'Aliado creado correctamente',
            'data'    => $ally,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $ally = ContentAlly::find($id);

        if (!$ally) {
            return response()->json(['message' => 'Aliado no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'image'    => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'url'      => 'required|string|url|max:255',
            'position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($ally->image);
            $file = $request->file('image');
            $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('content_allies', $filename, 'public');
            $ally->image = $path;
            $ally->image_filename = $filename;
        }

        $ally->url      = $request->input('url');
        $ally->position = $request->input('position');
        $ally->save();

        return response()->json([
            'message' => 'Aliado actualizado correctamente',
            'data'    => $ally,
        ]);
    }

    public function destroy($id)
    {
        $ally = ContentAlly::find($id);

        if (!$ally) {
            return response()->json(['message' => 'Aliado no encontrado'], 404);
        }

        Storage::disk('public')->delete($ally->image);
        $ally->delete();

        return response()->json(['message' => 'Aliado eliminado correctamente']);
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items'            => 'required|array',
            'items.*.id'       => 'required|integer|exists:content_allies,id',
            'items.*.position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->items as $item) {
            ContentAlly::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }

    public function publicIndex()
    {
        $allies = ContentAlly::orderBy('position')
            ->get(['id', 'image', 'url', 'position']);

        $data = $allies->map(fn ($a) => [
            'id'       => $a->id,
            'image'    => $a->image,
            'url'      => $a->url,
            'position' => $a->position,
        ]);

        return response()->json([
            'message' => 'Aliados obtenidos correctamente',
            'data'    => $data,
        ]);
    }
}
