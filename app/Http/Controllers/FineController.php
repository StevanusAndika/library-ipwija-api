<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FineController extends Controller
{
    public function index(Request $request)
    {
        $query = Fine::with(['user', 'borrowing.book']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('fine_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $perPage = $request->get('per_page', 15);
        $fines = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Fines retrieved successfully',
            'data' => $fines
        ]);
    }

    public function myFines(Request $request)
    {
        $user = $request->user();

        $fines = $user->fines()
            ->with(['borrowing.book'])
            ->latest()
            ->paginate(10);

        $totalUnpaid = $user->getTotalUnpaidFines();

        return response()->json([
            'success' => true,
            'message' => 'Your fines retrieved successfully',
            'data' => [
                'fines' => $fines,
                'total_unpaid' => $totalUnpaid
            ]
        ]);
    }

    public function markAsPaid($id)
    {
        $fine = Fine::find($id);

        if (!$fine) {
            return response()->json([
                'success' => false,
                'message' => 'Fine not found',
                'data' => null
            ], 404);
        }

        if ($fine->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Fine is already paid',
                'data' => null
            ], 400);
        }

        $fine->markAsPaid();

        return response()->json([
            'success' => true,
            'message' => 'Fine marked as paid successfully',
            'data' => $fine
        ]);
    }
}
