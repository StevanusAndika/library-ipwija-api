<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use TCPDF;

class UserController extends Controller
{
    public function generateMember(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->view('errors.404', ['message' => 'Token is required'], 400);
        }

        try {
            $auth = JWTAuth::parseToken()->authenticate();

            if (!$auth) {
                return response()->view('errors.404', ['message' => 'User not found'], 404);
            }

            return view('user.generate-member', ['user' => $auth]);

        } catch (TokenExpiredException $e) {
            return response()->view('errors.404', ['message' => 'Token has expired'], 401);

        } catch (TokenInvalidException $e) {
            return response()->view('errors.404', ['message' => 'Token is invalid'], 401);

        } catch (JWTException $e) {
            return response()->view('errors.404', ['message' => 'Token error: ' . $e->getMessage()], 401);

        } catch (\Exception $e) {
            return response()->view('errors.404', ['message' => 'Authentication failed'], 500);
        }
    }

    public function memberCardPdf(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            abort(400, 'Token is required');
        }

        try {
            $auth = JWTAuth::parseToken()->authenticate();

            if (!$auth) {
                abort(404, 'User not found');
            }

            // Initialize PDF dengan orientasi Landscape A7
            $pdf = new TCPDF('L', 'mm', 'A7', true, 'UTF-8', false);
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            
            // Metadata PDF
            $pdf->SetTitle('Kartu Anggota Perpustakaan IPWIJA - ' . $auth->name);
            $pdf->SetAuthor('Universitas IPWIJA');
            $pdf->SetSubject('Membership Card / Kartu Anggota');
            $pdf->SetKeywords('membership, card, perpustakaan, IPWIJA, rekayasa perangkat lunak');
            $pdf->SetCreator('Developed by Dhaffa Abdillah Hakim');
            
            $pdf->AddPage();

            $pageWidth = $pdf->GetPageWidth();
            $pageHeight = $pdf->GetPageHeight();

            // Background Image
            $bgPath = public_path('images/image.png');
            if (file_exists($bgPath)) {
                $pdf->Image($bgPath, 0, 0, $pageWidth, $pageHeight, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
            } else {
                // Fallback gradient-like blue background
                $pdf->SetFillColor(26, 58, 122);
                $pdf->Rect(0, 0, $pageWidth, $pageHeight, 'F');
            }

            // === HEADER SECTION ===
            // Logo IPWIJA (top right)
            $logoWidth = 25;
            $logoHeight = 6;
            $logoX = $pageWidth - $logoWidth - 3;
            $logoY = 4;
            
            $logoPath = public_path('images/logo-ipwija-web.png');
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, $logoX, $logoY, $logoWidth, $logoHeight, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
            }

            // Title Header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(4, 4);
            $pdf->Cell(60, 4, "KARTU ANGGOTA PERPUSTAKAAN", 0, 1, 'L', 0, '', 0);

            // Subtitle
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY(4, 8);
            $pdf->Cell(60, 3, "Universitas IPWIJA", 0, 1, 'L', 0, '', 0);

            // Decorative line
            $pdf->SetDrawColor(255, 193, 7);
            $pdf->SetLineWidth(0.5);
            $pdf->Line(4, 12, $pageWidth - 4, 12);

            $photoWidth = 20;
            $photoHeight = 26;
            $photoX = 4;
            $photoY = 25;

            // White background for photo
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($photoX, $photoY, $photoWidth, $photoHeight, 'FD');

            // Display profile picture or initial
            $profilePicturePath = $auth->profile_picture ? storage_path('app/public/' . $auth->profile_picture) : null;
            
            if ($profilePicturePath && file_exists($profilePicturePath)) {
                $pdf->Image(
                    $profilePicturePath,
                    $photoX, 
                    $photoY, 
                    $photoWidth, 
                    $photoHeight, 
                    '', 
                    '', 
                    '', 
                    false, 
                    300, 
                    '', 
                    false, 
                    false, 
                    0, 
                    false, 
                    false, 
                    false
                );
            } else {
                // Display first letter as placeholder
                $firstLetter = strtoupper(substr($auth->name, 0, 1));
                $pdf->SetFont('helvetica', 'B', 40);
                $pdf->SetTextColor(200, 200, 200);
                $pdf->SetXY($photoX, $photoY + 6);
                $pdf->Cell($photoWidth, 14, $firstLetter, 0, 0, 'C');
            }

            // Barcode below photo
            $barcodeY = $photoY + $photoHeight + 1;
            $barcodeData = $auth->nim ?? $auth->email ?? 'N/A';
            
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($photoX, $barcodeY, $photoWidth, 7, 'F');
            
            $pdf->SetTextColor(0, 0, 0);
            $pdf->write1DBarcode(
                $barcodeData, 
                'C128', 
                $photoX + 0.5, 
                $barcodeY + 0.5, 
                $photoWidth - 1, 
                5, 
                0.35, 
                [], 
                'N'
            );

            // === DATA SECTION (Right side) ===
            $dataX = $photoX + $photoWidth + 10;
            $dataWidth = $pageWidth - $dataX - 4;
            
            // Name
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($dataX, 22);
            $memberName = strlen($auth->name) > 30 ? substr(strtoupper($auth->name), 0, 27) . '...' : strtoupper($auth->name);
            $pdf->MultiCell($dataWidth, 4, $memberName, 0, 'L', 0, 1, '', '', true, 0, false, true, 8, 'M');

            // NIM
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($dataX, 29);
            if ($auth->nim) {
                $pdf->Cell($dataWidth, 3, "NIM: " . $auth->nim, 0, 1, 'L', 0, '', 0);
            } else {
                $pdf->SetTextColor(220, 220, 220);
                $pdf->Cell($dataWidth, 3, 'NIM: Tidak tersedia', 0, 1, 'L', 0, '', 0);
            }

            // Divider line
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($dataX, 26, $pageWidth - 4, 26);

            // Birth info
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(5, 5, 5);
            $pdf->SetXY($dataX, 32);
            $pdf->Cell($dataWidth, 3, 'Tempat, Tanggal Lahir:', 0, 1, 'L', 0, '', 0);

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetXY($dataX, 34);
            
            $tempatLahir = $auth->tempat_lahir ?? null;
            $tanggalLahir = $auth->tanggal_lahir ?? null;

            if ($tempatLahir && $tanggalLahir) {
                $birthDate = date('d M Y', strtotime($tanggalLahir));
                $birthInfo = $tempatLahir . ', ' . $birthDate;
                $pdf->MultiCell($dataWidth, 3, $birthInfo, 0, 'L', 0, 1, '', '', true, 0, false, true, 6, 'M');
            } else {
                $pdf->SetTextColor(220, 220, 220);
                $pdf->Cell($dataWidth, 3, 'Tidak tersedia', 0, 1, 'L', 0, '', 0);
            }

            // Join date section
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(245, 245, 245);
            $pdf->SetXY($dataX + 20, 58.5);
            $pdf->Cell($dataWidth, 3, 'Bergabung pada:', 0, 1, 'L', 0, '', 0);

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(255, 193, 7);
            $pdf->SetXY($dataX + 20, 61);
            $joinDate = $auth->created_at->format('d/m/Y');
            $pdf->Cell($dataWidth, 3, $joinDate, 0, 1, 'L', 0, '', 0);

            // Output PDF
            $filename = 'membership-card-' . ($auth->nim ?? 'user') . '.pdf';
            $pdf->Output($filename, 'I');

        } catch (TokenExpiredException $e) {
            abort(401, 'Token has expired');

        } catch (TokenInvalidException $e) {
            abort(401, 'Token is invalid');

        } catch (JWTException $e) {
            abort(401, 'Token error: ' . $e->getMessage());

        } catch (\Exception $e) {
            abort(500, 'Failed to generate PDF: ' . $e->getMessage());
        }
    }
}
