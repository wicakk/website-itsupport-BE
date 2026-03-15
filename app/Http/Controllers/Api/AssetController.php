<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\PmSchedule;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Asset::with([
            'assignee:id,name,initials,color,department',
            'pmSchedules',
        ])->latest();

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('category')) $query->where('category', $request->category);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('name',           'like', "%$s%")
                  ->orWhere('asset_number',  'like', "%$s%")
                  ->orWhere('serial_number', 'like', "%$s%")
                  ->orWhere('brand',         'like', "%$s%")
            );
        }

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'category'        => ['required', Rule::in(['Laptop','Desktop','Printer','Network','Server','Phone','Monitor','Others'])],
            'brand'           => 'nullable|string|max:100',
            'model'           => 'nullable|string|max:100',
            'serial_number'   => 'nullable|string|unique:assets',
            'location'        => 'nullable|string|max:255',
            'purchase_date'   => 'nullable|date',
            'purchase_price'  => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date|after:today',
            'notes'           => 'nullable|string',
            'specs'           => 'nullable|array',
        ]);

        $asset = Asset::create($data);

        return response()->json(['message' => 'Aset berhasil ditambahkan.', 'asset' => $asset], 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        return response()->json($asset->load([
            'assignee:id,name,initials,color,department',
            'pmSchedules',
        ]));
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'status'          => ['sometimes', Rule::in(['Active','Maintenance','Inactive','Disposed'])],
            'location'        => 'sometimes|nullable|string|max:255',
            'warranty_expiry' => 'sometimes|nullable|date',
            'notes'           => 'sometimes|nullable|string',
            'specs'           => 'sometimes|nullable|array',
        ]);

        $asset->update($data);

        return response()->json(['message' => 'Aset diperbarui.', 'asset' => $asset->fresh()]);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $asset->delete();
        return response()->json(['message' => 'Aset dihapus.']);
    }

    public function assign(Request $request, Asset $asset): JsonResponse
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user = User::find($request->user_id);
        $asset->update(['assigned_to' => $request->user_id, 'status' => 'Active']);

        return response()->json([
            'message' => "Aset di-assign ke {$user->name}.",
            'asset'   => $asset->fresh()->load('assignee:id,name,initials,color'),
        ]);
    }

    public function unassign(Asset $asset): JsonResponse
    {
        $asset->update(['assigned_to' => null]);
        return response()->json(['message' => 'Assignment aset dihapus.', 'asset' => $asset->fresh()]);
    }

    public function setMaintenance(Asset $asset): JsonResponse
    {
        $asset->update(['status' => 'Maintenance', 'assigned_to' => null]);
        return response()->json(['message' => 'Aset masuk mode maintenance.', 'asset' => $asset->fresh()]);
    }

    public function storePM(Request $request, Asset $asset): JsonResponse
    {
        $request->validate([
            'title'     => 'required|string',
            'interval'  => 'required|string',
            'next_date' => 'required|date',
        ]);

        $pm = $asset->pmSchedules()->create([
            'title'     => $request->title,
            'interval'  => $request->interval,
            'next_date' => $request->next_date,
            'notes'     => $request->notes,
            'status'    => 'Pending', // fix: was 'Terjadwal' which is not a valid enum value
        ]);

        return response()->json(['data' => $pm]);
    }

    public function completePM(Request $request, Asset $asset, PmSchedule $pm): JsonResponse
    {
        $pm->update([
            'status'    => 'Selesai',
            'last_done' => now()->toDateString(),
        ]);

        return response()->json(['data' => $pm->fresh()]);
    }
}