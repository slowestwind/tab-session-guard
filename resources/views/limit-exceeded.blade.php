<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tab Limit Exceeded</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2.5rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #f59e0b;
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.75rem;
            font-weight: 600;
        }
        
        .message {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        
        .details {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 500;
            color: #374151;
        }
        
        .detail-value {
            color: #6b7280;
            font-weight: 600;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }
        
        .limit-exceeded {
            color: #dc2626;
            font-weight: 700;
        }
        
        .auto-redirect {
            margin-top: 1.5rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚠️</div>
        
        <h1>Tab Limit Exceeded</h1>
        
        <div class="message">
            {{ $message ?? 'You have reached the maximum number of allowed tabs for this section.' }}
        </div>
        
        <div class="details">
            @if(isset($validation['current']) && isset($validation['max']))
            <div class="detail-item">
                <span class="detail-label">Current Tabs:</span>
                <span class="detail-value limit-exceeded">{{ $validation['current'] }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Maximum Allowed:</span>
                <span class="detail-value">{{ $validation['max'] }}</span>
            </div>
            @endif
            
            @if(isset($validation['type']))
            <div class="detail-item">
                <span class="detail-label">Limit Type:</span>
                <span class="detail-value">{{ ucfirst($validation['type']) }}</span>
            </div>
            @endif
            
            @if(isset($validation['role']))
            <div class="detail-item">
                <span class="detail-label">Role:</span>
                <span class="detail-value">{{ ucfirst($validation['role']) }}</span>
            </div>
            @endif
            
            @if(isset($validation['module']))
            <div class="detail-item">
                <span class="detail-label">Module:</span>
                <span class="detail-value">{{ ucfirst($validation['module']) }}</span>
            </div>
            @endif
        </div>
        
        <div class="actions">
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                Go to Dashboard
            </a>
            
            <button onclick="window.close()" class="btn btn-secondary">
                Close This Tab
            </button>
        </div>
        
        @if($config['ui']['auto_close_alert'] ?? false)
        <div class="auto-redirect">
            This page will redirect automatically in <span id="countdown">10</span> seconds.
        </div>
        @endif
    </div>

    <script>
        // Auto-redirect functionality
        @if($config['ui']['auto_close_alert'] ?? false)
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '{{ route("dashboard") }}';
            }
        }, 1000);
        @endif
        
        // Close tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Try to close tab if opened programmatically
            if (window.opener) {
                setTimeout(() => {
                    window.close();
                }, 3000);
            }
        });
    </script>
</body>
</html>
