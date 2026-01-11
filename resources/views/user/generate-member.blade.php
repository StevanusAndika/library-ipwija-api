<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Membership Card - IPWIJA</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1a3a7a 0%, #2c5aa0 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 900px;
        }

        .card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background: linear-gradient(135deg, #1a3a7a 0%, #2c5aa0 100%);
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .header-logo {
            flex-shrink: 0;
            z-index: 1;
        }

        .logo-circle {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            color: #1a3a7a;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .header-text {
            flex: 1;
            z-index: 1;
        }

        .header-text h2 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .header-text p {
            font-size: 13px;
            opacity: 0.9;
            letter-spacing: 0.3px;
        }

        .card-body {
            display: flex;
            padding: 40px;
            gap: 40px;
        }

        .card-info {
            flex: 1;
        }

        .member-name {
            font-size: 28px;
            font-weight: bold;
            color: #1a3a7a;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .info-group {
            margin-bottom: 20px;
        }

        .info-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            color: #333;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .card-photo {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .photo-container {
            width: 140px;
            height: 180px;
            background: #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
        }

        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-status-badge {
            background: #ffc107;
            color: #333;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .card-footer {
            background: #f9f9f9;
            padding: 20px 40px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .footer-text {
            font-size: 12px;
            color: #666;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view-pdf {
            background: #1a3a7a;
            color: white;
        }

        .btn-view-pdf:hover {
            background: #142d5f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 58, 122, 0.3);
        }

        .btn-back {
            background: #e0e0e0;
            color: #333;
        }

        .btn-back:hover {
            background: #d0d0d0;
        }

        @media (max-width: 768px) {
            .card-body {
                flex-direction: column;
                padding: 20px;
                gap: 20px;
            }

            .card-header {
                padding: 20px;
            }

            .header-text h2 {
                font-size: 18px;
            }

            .member-name {
                font-size: 22px;
            }

            .photo-container {
                width: 120px;
                height: 160px;
            }

            .card-footer {
                flex-direction: column;
                padding: 15px 20px;
            }

            .action-buttons {
                width: 100%;
            }

            button {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <!-- Header dengan Logo -->
            <div class="card-header">
                <div class="header-logo">
                    <div class="logo-circle">🎓</div>
                </div>
                <div class="header-text">
                    <h2>UNIVERSITAS IPWIJA</h2>
                    <p>Pilihanf Generasi Cerdas</p>
                </div>
            </div>

            <!-- Body dengan Info dan Photo -->
            <div class="card-body">
                <div class="card-info">
                    <div class="member-name">{{ $user->name }}</div>

                    <div class="info-group">
                        <div class="info-label">NIM</div>
                        <div class="info-value">{{ $user->nim ?? 'N/A' }}</div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Tanggal Bergabung</div>
                        <div class="info-value">{{ $user->created_at->format('d M Y') }}</div>
                    </div>

                    @if($user->status)
                    <div class="info-group">
                        <div class="info-label">Status</div>
                        <div class="info-value">{{ $user->status }}</div>
                    </div>
                    @endif

                    @if($user->role)
                    <div class="info-group">
                        <div class="info-label">Role</div>
                        <div class="info-value">{{ ucfirst($user->role) }}</div>
                    </div>
                    @endif
                </div>

                <div class="card-photo">
                    <div class="photo-container">
                        @if($user->profile_picture && file_exists(storage_path('app/public/' . $user->profile_picture)))
                            <img src="{{ asset('storage/' . $user->profile_picture) }}" alt="Profile">
                        @else
                            👤
                        @endif
                    </div>
                    <div class="member-status-badge">
                        📚 Member
                    </div>
                </div>
            </div>

            <!-- Footer dengan Tombol -->
            <div class="card-footer">
                <div class="footer-text">
                    <strong>Kartu Anggota Perpustakaan IPWIJA</strong><br>
                    Berlaku selama status anggota aktif
                </div>
                <div class="action-buttons">
                    <button class="btn-view-pdf" onclick="viewPDF()">👁️ View PDF</button>
                    <button class="btn-back" onclick="window.history.back()">← Back</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewPDF() {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            window.location.href = `/member-card-pdf?token=${token}`;
        }
    </script>
</body>
</html>
