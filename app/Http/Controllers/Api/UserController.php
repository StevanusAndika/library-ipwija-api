<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\ChangeUserStatusRequest;
use App\Http\Requests\BatchInsertUsersRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{

    public function check_uncomplete_data()
    {
        try {
            $user = Auth::user();

            $missingFields = [];
            if (!$user->nim) $missingFields[] = 'nim';
            if (!$user->phone) $missingFields[] = 'phone';
            if (!$user->alamat_asal && !$user->alamat_sekarang) $missingFields[] = 'alamat';
            if (!$user->tempat_lahir) $missingFields[] = 'tempat_lahir';
            if (!$user->tanggal_lahir) $missingFields[] = 'tanggal_lahir';
            if (!$user->agama) $missingFields[] = 'agama';

            if (count($missingFields) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lengkapi data-data berikut untuk menjadi anggota perpustakaan',
                    'data' => $missingFields
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Membership status set to complete',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check membership status',
                'error' => $e->getMessage()
            ], 500);
        }    
    }

    // Hanya boleh diakses oleh admin
    public function change_status_user(ChangeUserStatusRequest $request)
    {
        try {
            $user = Auth::user();
            $id_user = $request->input('user_id');

            if ($user->id == $id_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat mengubah status diri sendiri.'
                ], 403);
            }

            $targetUser = User::find($id_user);
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan.'
                ], 404);
            }

            $targetUser->status = $request->input('status');
            $targetUser->save();

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $targetUser
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = User::query();

        // Search by name, nim, phone, email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role (admin, user)
        if ($request->has('role') && $request->role !== null) {
            $query->where('role', $request->role);
        }

        // Filter by agama (religion)
        if ($request->has('agama') && $request->agama !== null) {
            $query->where('agama', $request->agama);
        }

        // Filter by status (PENDING, ACTIVE, SUSPENDED, INACTIVE)
        if ($request->has('status') && $request->status !== null) {
            $query->where('status', $request->status);
        }

        // Add statistics
        $query->withCount([
            'borrowings',
            'activeBorrowings as active_borrowings_count',
            'unpaidFines as unpaid_fines_count'
        ]);

        $perPage = $request->get('per_page', 20);
        $users = $query->orderBy('created_at', 'DESC')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    public function store(AddUserRequest $request) 
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $data = $request->except(['password']);
                
                $data['password'] = Hash::make($request->password);
                
                if (!isset($data['role'])) {
                    $data['role'] = 'user';
                }
                
                if (!isset($data['status'])) {
                    $data['status'] = 'PENDING';
                }
                
                return User::create($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $user = User::withCount([
            'borrowings',
            'activeBorrowings',
            'fines',
            'unpaidFines'
        ])->with([
            // 'activeBorrowings' => function($query) {
            //     $query->with(['book.category'])->limit(5);
            // },
            // 'unpaidFines' => function($query) {
            //     $query->with(['borrowing.book'])->limit(5);
            // }
        ])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

    public function update(UpdateUserRequest $request, $id = null)
    {
        if (!$id) {
            $id = Auth::user()->id;
        }
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        $data = $request->except(['foto', 'password']);

        // Update password if provided
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profile_picture')) {
            // Delete old photo if exists
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $profile_picture = $request->file('profile_picture');
            $profilePictureName = time() . '_' . $user->id . '.' . $profile_picture->getClientOriginalExtension();
            $profilePicturePath = $profile_picture->storeAs('user_photos', $profilePictureName, 'public');
            $data['profile_picture'] = $profilePicturePath;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user,
            'request_data' => $request
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        // Prevent admin from deleting themselves
        if ($user->id === Auth::user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        // Check if user has active borrowings
        if ($user->activeBorrowings()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user with active borrowings'
            ], 400);
        }

        // Check if user has unpaid fines
        if ($user->unpaidFines()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user with unpaid fines'
            ], 400);
        }

        // Delete user photo if exists
        if ($user->foto && Storage::disk('public')->exists($user->foto)) {
            Storage::disk('public')->delete($user->foto);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    public function batch_insert_users(BatchInsertUsersRequest $request)
    {
        try {
            $file = $request->file('csv_file');
            $path = $file->getRealPath();
            
            $handle = fopen($path, 'r');
            if (!$handle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuka file CSV'
                ], 400);
            }

            $header = fgetcsv($handle, 0, ';'); // Skip header row
            
            $chunkSize = 100;
            $chunk = [];
            $insertedCount = 0;
            $skippedCount = 0;
            $errors = [];
            $lineNumber = 2; // Start from line 2 (after header)

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    $lineNumber++;
                    continue;
                }

                // Parse CSV data
                $userData = [
                    'name' => isset($row[1]) ? trim($row[1]) : null,
                    'nim' => isset($row[2]) ? trim($row[2]) : null,
                    'phone' => isset($row[3]) ? trim($row[3]) : null,
                    'tempat_lahir' => isset($row[4]) ? trim($row[4]) : null,
                    'tanggal_lahir' => isset($row[5]) ? trim($row[5]) : null,
                    'agama' => isset($row[6]) ? trim($row[6]) : null,
                    'address' => isset($row[7]) ? trim($row[7]) : null,
                    'line_number' => $lineNumber,
                ];

                if (empty($userData['name'])) {
                    $skippedCount++;
                    $errors[] = "Baris {$lineNumber}: Nama tidak boleh kosong";
                    $lineNumber++;
                    continue;
                }

                if (!empty($userData['nim'])) {
                    $existingNim = User::where('nim', $userData['nim'])->exists();
                    if ($existingNim) {
                        $skippedCount++;
                        $errors[] = "Baris {$lineNumber}: NIM {$userData['nim']} sudah terdaftar, data tidak diinput";
                        $lineNumber++;
                        continue;
                    }
                }

                // Check for duplicate phone
                if (!empty($userData['phone'])) {
                    $existingPhone = User::where('phone', $userData['phone'])->exists();
                    if ($existingPhone) {
                        $skippedCount++;
                        $errors[] = "Baris {$lineNumber}: Nomor telepon {$userData['phone']} sudah terdaftar, data tidak diinput";
                        $lineNumber++;
                        continue;
                    }
                }

                $chunk[] = $userData;
                $lineNumber++;

                // Process chunk when it reaches chunkSize or end of file
                if (count($chunk) >= $chunkSize) {
                    $insertedCount += $this->processUserChunk($chunk, $errors);
                    $chunk = [];
                }
            }

            // Process remaining data
            if (!empty($chunk)) {
                $insertedCount += $this->processUserChunk($chunk, $errors);
            }

            fclose($handle);

            return response()->json([
                'success' => true,
                'message' => 'Batch insert users selesai',
                'data' => [
                    'inserted' => $insertedCount,
                    'skipped' => $skippedCount,
                    'total_processed' => $insertedCount + $skippedCount,
                ],
                'errors' => $errors
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan batch insert users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process a chunk of user data
     */
    private function processUserChunk(array $chunk, &$errors)
    {
        try {
            $insertedCount = 0;

            DB::transaction(function () use ($chunk, &$insertedCount, &$errors) {
                foreach ($chunk as $userData) {
                    try {
                        // Double check for duplicate (in case of concurrent requests)
                        if (!empty($userData['nim']) && User::where('nim', $userData['nim'])->exists()) {
                            $errors[] = "Baris {$userData['line_number']}: NIM {$userData['nim']} sudah terdaftar (double check)";
                            continue;
                        }

                        if (!empty($userData['phone']) && User::where('phone', $userData['phone'])->exists()) {
                            $errors[] = "Baris {$userData['line_number']}: Nomor telepon {$userData['phone']} sudah terdaftar (double check)";
                            continue;
                        }

                        // Create user
                        User::create([
                            'name' => $userData['name'],
                            'nim' => $userData['nim'] ?: null,
                            'phone' => $userData['phone'] ?: null,
                            'tempat_lahir' => $userData['tempat_lahir'] ?: null,
                            'tanggal_lahir' => $userData['tanggal_lahir'] ?: null,
                            'agama' => $userData['agama'] ?: null,
                            'address' => $userData['address'] ?: null,
                            'role' => 'user',
                            'status' => 'PENDING',
                            'password' => Hash::make($userData['nim']), // Default password
                        ]);

                        $insertedCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Baris {$userData['line_number']}: {$e->getMessage()}";
                    }
                }
            });

            return $insertedCount;
        } catch (\Exception $e) {
            $errors[] = "Error saat memproses chunk: {$e->getMessage()}";
            return 0;
        }
    }

    public function toggleStatus($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "User {$status} successfully",
            'data' => $user
        ]);
    }

    public function toggleMembership($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'success' => false,
                'message' => 'Only mahasiswa can have membership'
            ], 400);
        }

        $user->is_anggota = !$user->is_anggota;
        $user->save();

        $status = $user->is_anggota ? 'granted membership' : 'revoked membership';

        return response()->json([
            'success' => true,
            'message' => "User {$status} successfully",
            'data' => $user
        ]);
    }

    public function getUserStats($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User statistics retrieved successfully',
            'data' => [
                'user' => $user->toApiResponse(),
                'stats' => $user->getStats()
            ]
        ]);
    }
}
