<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too Many Requests - LiveTV BD</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --font-family: 'Outfit', 'Inter', sans-serif;
            --bg-main: #131110;
            --bg-card: #2D2422;
            --accent-color: #FE4C24;
            --accent-gradient: linear-gradient(135deg, #FE4C24 0%, #FF7849 100%);
            --border-glass: rgba(255, 255, 255, 0.05);
            --text-primary: #FFFFFF;
            --text-secondary: #A39692;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 24px;
        }

        .limit-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 48px 32px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6), 0 0 40px rgba(254, 76, 36, 0.1);
            position: relative;
            overflow: hidden;
            animation: cardEntrance 0.6s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .limit-card::before {
            content: '';
            position: absolute;
            top: -10%;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 100px;
            background: radial-gradient(circle, rgba(254, 76, 36, 0.15) 0%, rgba(254, 76, 36, 0) 70%);
            filter: blur(20px);
            pointer-events: none;
        }

        .icon-timer {
            font-size: 64px;
            color: var(--accent-color);
            margin-bottom: 24px;
            display: inline-block;
            filter: drop-shadow(0 4px 12px rgba(254, 76, 36, 0.4));
            animation: spinTimer 4s infinite linear;
        }

        @keyframes spinTimer {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .countdown-wrapper {
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 32px;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            min-width: 180px;
        }

        .countdown-time {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-primary);
            font-family: monospace;
            line-height: 1;
        }

        .countdown-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 8px;
        }

        .btn-retry {
            background: var(--accent-gradient);
            border: none;
            color: white;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 4px 15px rgba(254, 76, 36, 0.2);
            cursor: not-allowed;
            opacity: 0.5;
            pointer-events: none;
        }

        .btn-retry.active {
            box-shadow: 0 6px 20px rgba(254, 76, 36, 0.4);
            transform: translateY(-2px);
            cursor: pointer;
            opacity: 1;
            pointer-events: auto;
        }

        .btn-retry:hover {
            box-shadow: 0 6px 20px rgba(254, 76, 36, 0.4);
            transform: translateY(-2px);
            color: white;
        }

        .security-badge {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            color: var(--text-secondary);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

    <div class="limit-card">
        <div class="security-badge">
            <i class="bi bi-clock-history"></i> Rate Limiter
        </div>
        
        <div>
            <i class="bi bi-hourglass-split icon-timer"></i>
        </div>
        
        <h1>Too Many Requests (429)</h1>
        
        <p>
            You are browsing a bit too fast. To ensure a smooth experience for everyone, requests are rate limited.
        </p>
        
        <div class="countdown-wrapper">
            <div id="countdown" class="countdown-time">--s</div>
            <div class="countdown-label">Please wait</div>
        </div>
        
        <div>
            <a href="/" id="retry-btn" class="btn-retry">
                <i class="bi bi-arrow-clockwise"></i> Try Again
            </a>
        </div>
    </div>

    <script>
        let secondsLeft = parseInt("{{ $retryAfter ?? 60 }}");
        if (isNaN(secondsLeft) || secondsLeft <= 0) {
            secondsLeft = 60;
        }

        const countdownEl = document.getElementById('countdown');
        const retryBtnEl = document.getElementById('retry-btn');

        function updateTimer() {
            countdownEl.textContent = secondsLeft + 's';
            
            if (secondsLeft <= 0) {
                countdownEl.textContent = 'Ready';
                countdownEl.style.color = '#28a745';
                retryBtnEl.classList.add('active');
                retryBtnEl.href = window.location.href; // Refresh page on click
                clearInterval(interval);
            } else {
                secondsLeft--;
            }
        }

        updateTimer();
        const interval = setInterval(updateTimer, 1000);
    </script>
</body>
</html>
