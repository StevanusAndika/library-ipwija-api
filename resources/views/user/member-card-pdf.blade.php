<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Membership Card - {{ $user->nim }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            width: 210mm;
            height: 130mm;
            margin: 0;
            padding: 0;
        }

        .membership-card {
            background: linear-gradient(135deg, #1a3a7a 0%, #2c5aa0 50%, #1a3a7a 100%);
            width: 100%;
            height: 100%;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
        }

        .membership-card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            z-index: 0;
        }

        .card-left {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .card-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .university-logo {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #1a3a7a;
        }

        .university-name {
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .card-center {
            flex: 1.2;
            position: relative;
            z-index: 1;
        }

        .member-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .member-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 18px;
            margin-bottom: 10px;
        }

        .detail-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .detail-label {
            font-size: 8px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
        }

        .detail-value {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .member-status {
            display: inline-block;
            background: rgba(255, 255, 0, 0.2);
            border: 2px solid rgba(255, 255, 0, 0.4);
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .card-right {
            flex: 0.7;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        .member-photo {
            width: 70px;
            height: 90px;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .barcode-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .barcode-container {
            background: white;
            padding: 6px;
            border-radius: 3px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .barcode-img {
            max-height: 100%;
            max-width: 100%;
            width: auto;
        }

        .barcode-text {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-align: center;
        }

        /* Print optimization */
        @page {
            size: A5 landscape;
            margin: 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .membership-card {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="membership-card">
        <!-- Left Side: Logo & Info -->
        <div class="card-left">
            <div class="card-logo">
                <div class="university-logo">🎓</div>
                <div class="university-name">
                    Universitas<br>IPWIJA
                </div>
            </div>

            <!-- Center: Member Information -->
            <div class="card-center">
                <div class="member-name">{{ $user->name }}</div>

                <div class="member-details">
                    <div class="detail-row">
                        <span class="detail-label">NIM</span>
                        <span class="detail-value">{{ $user->nim ?? 'N/A' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Joined</span>
                        <span class="detail-value">{{ $user->created_at->format('m/d/y') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">{{ strtoupper($user->status) }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Role</span>
                        <span class="detail-value">{{ strtoupper($user->role ?? 'MEMBER') }}</span>
                    </div>
                </div>

                <div class="member-status">📚 MEMBER</div>
            </div>
        </div>

        <!-- Right Side: Photo & Barcode -->
        <div class="card-right">
            <div class="member-photo">👤</div>

            <div class="barcode-section">
                <div class="barcode-container">
                    <img src="data:image/svg+xml;utf8,
                    <svg xmlns='http://www.w3.org/2000/svg' width='140' height='45' viewBox='0 0 140 45'>
                        <rect width='140' height='45' fill='white'/>
                        <rect x='8' y='8' width='3' height='30' fill='black'/>
                        <rect x='12' y='8' width='2' height='30' fill='black'/>
                        <rect x='15' y='8' width='2' height='30' fill='black'/>
                        <rect x='18' y='8' width='3' height='30' fill='black'/>
                        <rect x='22' y='8' width='2' height='30' fill='black'/>
                        <rect x='25' y='8' width='2' height='30' fill='black'/>
                        <rect x='28' y='8' width='3' height='30' fill='black'/>
                        <rect x='32' y='8' width='2' height='30' fill='black'/>
                        <rect x='35' y='8' width='2' height='30' fill='black'/>
                        <rect x='38' y='8' width='3' height='30' fill='black'/>
                        <rect x='42' y='8' width='2' height='30' fill='black'/>
                        <rect x='45' y='8' width='2' height='30' fill='black'/>
                        <rect x='48' y='8' width='3' height='30' fill='black'/>
                        <rect x='52' y='8' width='2' height='30' fill='black'/>
                        <rect x='55' y='8' width='2' height='30' fill='black'/>
                        <rect x='58' y='8' width='3' height='30' fill='black'/>
                        <rect x='62' y='8' width='2' height='30' fill='black'/>
                        <rect x='65' y='8' width='2' height='30' fill='black'/>
                        <rect x='68' y='8' width='3' height='30' fill='black'/>
                        <rect x='72' y='8' width='2' height='30' fill='black'/>
                        <rect x='75' y='8' width='2' height='30' fill='black'/>
                        <rect x='78' y='8' width='3' height='30' fill='black'/>
                        <rect x='82' y='8' width='2' height='30' fill='black'/>
                        <rect x='85' y='8' width='2' height='30' fill='black'/>
                        <rect x='88' y='8' width='3' height='30' fill='black'/>
                        <rect x='92' y='8' width='2' height='30' fill='black'/>
                        <rect x='95' y='8' width='2' height='30' fill='black'/>
                        <rect x='98' y='8' width='3' height='30' fill='black'/>
                        <rect x='102' y='8' width='2' height='30' fill='black'/>
                        <rect x='105' y='8' width='2' height='30' fill='black'/>
                        <rect x='108' y='8' width='3' height='30' fill='black'/>
                        <rect x='112' y='8' width='2' height='30' fill='black'/>
                        <rect x='115' y='8' width='2' height='30' fill='black'/>
                        <rect x='118' y='8' width='3' height='30' fill='black'/>
                        <rect x='122' y='8' width='2' height='30' fill='black'/>
                        <rect x='125' y='8' width='2' height='30' fill='black'/>
                        <rect x='128' y='8' width='5' height='30' fill='black'/>
                    </svg>" alt="Barcode" class="barcode-img">
                </div>
                <div class="barcode-text">{{ $user->nim ?? 'N/A' }}</div>
            </div>
        </div>
    </div>
</body>
</html>
