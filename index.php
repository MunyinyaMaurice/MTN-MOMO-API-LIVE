<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MTN MoMo API - DEEPNEXIS Ltd</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status {
            background: #10b981;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: bold;
        }
        .info {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .label {
            color: #6b7280;
            font-weight: 600;
        }
        .value {
            color: #111827;
            font-family: 'Courier New', monospace;
        }
        .endpoints {
            background: #fef3c7;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #f59e0b;
        }
        .endpoint {
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #78350f;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .warning {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 MTN MoMo API</h1>
        <p class="subtitle">DEEPNEXIS Ltd - Production Environment</p>
        
        <div class="status">
            ✅ API Server is Running
        </div>
        
        <div class="info">
            <div class="info-item">
                <span class="label">Environment</span>
                <span class="value"><?php echo $_ENV['MOMO_ENVIRONMENT'] ?? 'production'; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Target Environment</span>
                <span class="value"><?php echo $_ENV['MOMO_TARGET_ENVIRONMENT'] ?? 'mtnrwanda'; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Currency</span>
                <span class="value">RWF</span>
            </div>
            <div class="info-item">
                <span class="label">Test Phone</span>
                <span class="value">250782752491</span>
            </div>
            <div class="info-item">
                <span class="label">Base URL</span>
                <span class="value"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]"; ?></span>
            </div>
        </div>
        
        <div class="endpoints">
            <h3 style="margin-bottom: 15px; color: #78350f;">📡 Quick Test Endpoints</h3>
            <div class="endpoint">GET /api.php?action=help</div>
            <div class="endpoint">GET /api.php?action=verify</div>
            <div class="endpoint">GET /api.php?action=collection/balance</div>
            <div class="endpoint">POST /api.php?action=collection/request-to-pay</div>
        </div>
        
        <div style="text-align: center;">
            <a href="api.php?action=verify" class="btn">🔍 Verify Credentials</a>
            <a href="api.php?action=help" class="btn" style="background: #10b981;">📖 API Documentation</a>
        </div>
        
        <div class="warning">
            <strong>⚠️ Production Environment</strong><br>
            This system uses REAL MONEY. Always test with small amounts (100 RWF) first.
        </div>
    </div>
</body>
</html>
