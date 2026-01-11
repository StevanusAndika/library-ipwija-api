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

            $pdf = new TCPDF('L', 'mm', 'A7', true, 'UTF-8', false);
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->AddPage();

            $pageWidth = $pdf->GetPageWidth();
            $pageHeight = $pdf->GetPageHeight();

            $bgPath = public_path('images/bg.jpg');
            if (file_exists($bgPath)) {
                $pdf->Image($bgPath, 0, 0, $pageWidth, $pageHeight, 'jpg', '', '', false, 300, '', false, false, 1, false, false, false);
            } else {
                // Fallback to blue if image not found
                $pdf->SetFillColor(26, 58, 122);
                $pdf->Rect(0, 0, $pageWidth, $pageHeight, 'F');
            }

            $pdf->SetFillColor(0, 0, 0);
            $pdf->SetAlpha(0.5);
            $pdf->Rect(0, 0, $pageWidth, $pageHeight, 'F');
            $pdf->SetAlpha(1.0);

            $logoWidth = $pageWidth * 0.29;
            $logoHeight = $pageHeight * 0.095;
            $logoPadding = $pageWidth * 0.03;
            $logoPath = public_path('images/logo-ipwija-web.png');
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, $logoPadding, $logoPadding, $logoWidth, $logoHeight, 'png', '', '', false, 300, '', false, false, 0, false, false, false);
            } else {
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY(10, 20);
                $pdf->Cell(30, 10, 'Universitas IPWIJA', 0, 0, 'C', 0, '', 0);
            }

            $gapSize = $pageHeight * 0.05;
            $separatorY = $logoPadding + $logoHeight + $gapSize;
            $separatorLineWidth = $pageWidth - (2 * $logoPadding);
            
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineWidth(0.3);
            $pdf->Line($logoPadding, $separatorY, $separatorLineWidth, $separatorY);

            // Section Data Diri
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(0, ($pageHeight * 0.22));
            $pdf->Cell(0, 5,  "KARTU ANGGOTA PERPUSTAKAAN", 0, 1, 'C', 0, '', 0);

            $pdf->SetFont('helvetica', '', 12);
            $pdf->SetXY($logoPadding, ($pageHeight * 0.35));
            $memberName = strlen($auth->name) > 35 ? substr(strtoupper($auth->name), 0, 35) . '...' : strtoupper($auth->name);
            $pdf->Cell(0, 5, $memberName, 0, 1, 'L', 0, '', 0);

            $pdf->SetXY($logoPadding, ($pageHeight * 0.45));
            if ($auth->nim) {
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Cell(0, 5, $auth->nim, 0, 1, 'L', 0, '', 0);
            } else {
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, 'NIM tidak tersedia', 0, 1, 'L', 0, '', 0);
            }

            // Tempat Lahir dan Tanggal Lahir
            $pdf->SetXY($logoPadding, ($pageHeight * 0.55));
            $birthInfo = '';
            $tempatLahir = $auth->tempat_lahir ?? null;
            $tanggalLahir = $auth->tanggal_lahir ?? null;

            if ($tempatLahir && $tanggalLahir) {
                $birthDate = date('d F Y', strtotime($tanggalLahir));
                $birthInfo = $tempatLahir . ', ' . $birthDate;
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Cell(0, 5, $birthInfo, 0, 1, 'L', 0, '', 0);
            } else {
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, 'Tempat dan tanggal lahir tidak tersedia', 0, 1, 'L', 0, '', 0);
            }

            $photoWidth = $pageWidth * 0.20;
            $photoHeight = $pageHeight * 0.40;
            $photoX = $pageWidth * 0.73;
            $photoY = $pageHeight * 0.35;

            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($photoX, $photoY, $photoWidth, $photoHeight, 'FD');

            $profilePicturePath = $auth->profile_picture ? storage_path('app/public/' . $auth->profile_picture) : null;
            
            if ($profilePicturePath && file_exists($profilePicturePath)) {
                $pdf->Image(
                    $profilePicturePath,
                    $photoX, $photoY, $photoWidth, $photoHeight, 'jpg', '', '', false, 300, '', false, false, 1, false, false, false
                );
            } else {
                // Display first letter of name
                $firstLetter = strtoupper(substr($auth->name, 0, 1));
                $pdf->SetFont('helvetica', 'B', 60);
                $pdf->SetTextColor(150, 150, 150);
                $pdf->SetXY($photoX, $photoY + ($photoHeight / 3));
                $pdf->Cell($photoWidth, $photoHeight / 3, $firstLetter, 0, 0, 'C');
            }

            $bottomY = $pageHeight * 0.80;

            // Left side - Bergabung pada
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($logoPadding, $bottomY);
            $pdf->Cell(0, 4, 'Bergabung pada:', 0, 1, 'L', 0, '', 0);

            $joinDate = $auth->created_at->format('d/m/Y');
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetXY($logoPadding, $bottomY + 4);
            $pdf->Cell(0, 4, $joinDate, 0, 1, 'L', 0, '', 0);

            $barcodeData = $auth->nim ?? $auth->email ?? 'N/A';
            
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($photoX, $bottomY, $photoWidth, 8, 'FD');
            
            $pdf->SetTextColor(0, 0, 0);
            $pdf->write1DBarcode($barcodeData, 'C128', $photoX + 1, $bottomY + 1, $photoWidth - 2, 6, 0.4, [], 'N');
            
            // Output PDF
            $filename = 'membership-card-' . $auth->nim . '.pdf';
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
