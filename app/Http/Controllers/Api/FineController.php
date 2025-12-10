<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FineController extends Controller
{
    // Admin: Get all fines
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

        if ($request->has('year')) {
            $query->whereYear('fine_date', $request->year);
        }

        $perPage = $request->get('per_page', 15);
        $fines = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Fines retrieved successfully',
            'data' => $fines
        ]);
    }

    // Mahasiswa: Get my fines
    public function myFines(Request $request)
    {
        $user = $request->user();

        $query = $user->fines()->with(['borrowing.book']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $fines = $query->orderBy('created_at', 'desc')->paginate(10);

        $totalUnpaid = $user->getTotalUnpaidFines();
        $totalPaid = $user->paidFines()->sum('amount');

        return response()->json([
            'success' => true,
            'message' => 'Your fines retrieved successfully',
            'data' => [
                'fines' => $fines,
                'total_unpaid' => $totalUnpaid,
                'total_paid' => $totalPaid
            ]
        ]);
    }

    // Admin/Mahasiswa: Mark fine as paid
    public function markAsPaid($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string|max:50',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $fine = Fine::with('user')->find($id);

        if (!$fine) {
            return response()->json([
                'success' => false,
                'message' => 'Fine not found'
            ], 404);
        }

        if ($fine->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Fine is already paid'
            ], 400);
        }

        // Update fine
        $fine->status = 'paid';
        $fine->paid_date = $request->payment_date;
        $fine->description .= ' | Paid via ' . $request->payment_method . ' - ' . $request->notes;
        $fine->save();

        // Update borrowing fine_paid status
        if ($fine->borrowing) {
            $fine->borrowing->fine_paid = true;
            $fine->borrowing->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Fine marked as paid successfully',
            'data' => $fine
        ]);
    }

    // Get fine statistics
    public function statistics(Request $request)
    {
        $query = Fine::query();

        if ($request->has('year')) {
            $query->whereYear('fine_date', $request->year);
        }

        $totalFines = $query->count();
        $totalAmount = $query->sum('amount');
        $unpaidAmount = $query->where('status', 'unpaid')->sum('amount');
        $paidAmount = $query->where('status', 'paid')->sum('amount');

        // Monthly statistics
        $monthlyData = Fine::selectRaw('YEAR(fine_date) as year, MONTH(fine_date) as month,
            SUM(amount) as total_amount, COUNT(*) as total_fines')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Fine statistics retrieved successfully',
            'data' => [
                'total_fines' => $totalFines,
                'total_amount' => $totalAmount,
                'unpaid_amount' => $unpaidAmount,
                'paid_amount' => $paidAmount,
                'monthly_data' => $monthlyData
            ]
        ]);
    }
}
