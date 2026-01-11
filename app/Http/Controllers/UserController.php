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

            $pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->AddPage();

            $pageWidth = $pdf->GetPageWidth();
            $pageHeight = $pdf->GetPageHeight();

            // Background gradient (blue)
            $pdf->SetFillColor(26, 58, 122);
            $pdf->Rect(0, 0, $pageWidth, $pageHeight, 'F');
            
            // Lighter blue gradient effect
            $pdf->SetFillColor(44, 90, 160);
            $pdf->Rect($pageWidth * 0.6, 0, $pageWidth * 0.4, $pageHeight, 'F');

            // LEFT SECTION - Logo & Text
            $xStart = 10;
            $yStart = 12;

            // Logo Circle
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle($xStart + 12, $yStart + 12, 10, 0, 360, 'F');
            
            // Logo text inside circle
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(26, 58, 122);
            $pdf->SetXY($xStart + 5, $yStart + 6);
            $pdf->Cell(14, 12, '🎓', 0, 0, 'C');

            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($xStart, $yStart + 23);
            $pdf->Cell(24, 3, 'UNIVERSITAS', 0, 1, 'C');
            $pdf->SetXY($xStart, $yStart + 26);
            $pdf->Cell(24, 3, 'IPWIJA', 0, 1, 'C');

            // Member name
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($xStart + 27, $yStart + 5);
            $nameLines = $pdf->getStringWidth($auth->name) > 35 ? 2 : 1;
            $pdf->MultiCell(35, 5, strtoupper($auth->name), 0, 'L', false);

            // Info details
            $infoY = $yStart + 15;
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(200, 200, 200);

            // NIM
            $pdf->SetXY($xStart + 27, $infoY);
            $pdf->Cell(8, 3, 'NIM', 0, 0);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($xStart + 36, $infoY);
            $pdf->Cell(15, 3, $auth->nim ?? 'N/A', 0, 1);

            // Joined Date
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->SetXY($xStart + 27, $infoY + 4);
            $pdf->Cell(8, 3, 'JOINED', 0, 0);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($xStart + 36, $infoY + 4);
            $pdf->Cell(15, 3, $auth->created_at->format('m/d/y'), 0, 1);

            // Status
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->SetXY($xStart + 27, $infoY + 8);
            $pdf->Cell(8, 3, 'STATUS', 0, 0);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($xStart + 36, $infoY + 8);
            $pdf->Cell(15, 3, strtoupper($auth->status), 0, 1);

            // Member badge
            $pdf->SetFillColor(255, 193, 7);
            $pdf->SetDrawColor(255, 193, 7);
            $pdf->RoundedRect($xStart + 27, $infoY + 13, 20, 4, 2, '1111', 'F');
            
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetTextColor(51, 51, 51);
            $pdf->SetXY($xStart + 27, $infoY + 13.5);
            $pdf->Cell(20, 3, '📚 MEMBER', 0, 0, 'C');

            // RIGHT SECTION - Photo & Barcode
            $rightX = $pageWidth - 42;
            $photoY = $yStart + 2;

            // Photo container
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Rect($rightX, $photoY, 35, 45, 'F');

            // Add photo if exists
            if ($auth->profile_picture && file_exists(storage_path('app/public/' . $auth->profile_picture))) {
                $pdf->Image(
                    storage_path('app/public/' . $auth->profile_picture),
                    $rightX, $photoY, 35, 45, 'jpg', '', 'T', false, 300, '', false, false, 1, false, false, false
                );
            } else {
                // Placeholder
                $pdf->SetFont('helvetica', '', 24);
                $pdf->SetTextColor(150, 150, 150);
                $pdf->SetXY($rightX, $photoY + 12);
                $pdf->Cell(35, 20, '👤', 0, 0, 'C');
            }

            // Barcode section
            $barcodeY = $photoY + 48;
            
            // Barcode container
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($rightX + 2, $barcodeY, 31, 12, 'F');

            // Simple barcode pattern
            $pdf->SetDrawColor(0, 0, 0);
            $barWidth = 1.5;
            $barX = $rightX + 4;
            $pattern = [1, 0, 1, 1, 0, 1, 0, 1, 1, 0, 1, 0, 1, 1, 0, 1, 0, 1];
            
            foreach ($pattern as $i => $bar) {
                if ($bar) {
                    $pdf->Rect($barX + ($i * $barWidth), $barcodeY + 1, $barWidth - 0.1, 10, 'F');
                }
            }

            // NIM below barcode
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($rightX, $barcodeY + 13);
            $pdf->Cell(35, 3, $auth->nim ?? 'N/A', 0, 0, 'C');

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
