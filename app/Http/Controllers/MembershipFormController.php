<?php

namespace App\Http\Controllers;

use App\Models\MembershipForm;
use App\Models\MembershipFormBeneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MembershipFormController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $search  = $request->query('search', '');

        $query = MembershipForm::with(['city:id,name'])
            ->select('membership_forms.*')
            ->where('state', 0);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',     'LIKE', "%{$search}%")
                  ->orWhere('lastname', 'LIKE', "%{$search}%")
                  ->orWhere('id_card',  'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('id', 'desc');

        $paginated = $query->paginate($perPage);

        return response()->json([
            'message' => 'Solicitudes obtenidas correctamente',
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    public function show($id)
    {
        $form = MembershipForm::with([
            'city:id,name,department_id',
            'membershipFormBeneficiaries',
        ])->find($id);

        if (!$form) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        return response()->json([
            'message' => 'Solicitud obtenida correctamente',
            'data'    => $form,
        ], 200);
    }

    public function destroy($id)
    {
        $form = MembershipForm::find($id);

        if (!$form) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $form->delete();

        return response()->json(['message' => 'Solicitud eliminada correctamente'], 200);
    }

    public function markConverted($id)
    {
        $form = MembershipForm::find($id);

        if (!$form) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $form->state = 1;
        $form->save();

        return response()->json([
            'message' => 'Solicitud marcada como convertida',
            'data'    => $form,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'lastname'        => 'required|string|max:255',
            'document'        => 'required|string|max:255',
            'movil'           => 'required|digits:10',
            'email'           => 'required|email|max:255',
            'birth_date'      => 'nullable|date',
            'address'         => 'required|string|max:255',
            'city_id'         => 'required|exists:cities,id',
            'advisor_name'    => 'required|string|max:255',
            'beneficiaries'   => 'nullable|array',
            'beneficiaries.*' => 'array',
            'beneficiaries.*.full_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $form = MembershipForm::create([
            'name'     => $request->name,
            'lastname' => $request->lastname,
            'id_card'  => $request->document,
            'phone'    => $request->movil,
            'email'    => $request->email,
            'bithdate' => $request->birth_date,
            'address'  => $request->address,
            'city_id'  => $request->city_id,
            'date'     => now()->toDateString(),
            'seller'   => $request->advisor_name,
            'state'    => 0,
        ]);

        if ($request->filled('beneficiaries')) {
            foreach ($request->beneficiaries as $b) {
                if (!empty($b['full_name'])) {
                    MembershipFormBeneficiary::create([
                        'membership_form_id' => $form->id,
                        'name'               => $b['full_name'],
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Solicitud de afiliación recibida correctamente',
            'data'    => $form,
        ], 201);
    }
}
