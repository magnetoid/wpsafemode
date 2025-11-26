<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WP Safe Mode - Login</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">

    <!-- Premium Safe Mode CSS -->
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/components.css">

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f1115 0%, #1a1f2e 100%);
            position: relative;
            overflow: hidden;
        }

        /* Animated background particles */
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(0, 229, 255, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(124, 77, 255, 0.05) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(-50px, -50px);
            }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: var(--space-lg);
        }

        .login-card {
            background: rgba(22, 27, 34, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }

        .login-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-md);
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 30px rgba(0, 229, 255, 0.3);
        }

        .login-logo .material-symbols-outlined {
            font-size: 3rem;
            color: #000;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 var(--space-xs) 0;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-subtitle {
            color: var(--color-text-muted);
            font-size: var(--font-size-sm);
        }

        .form-group {
            margin-bottom: var(--space-lg);
        }

        .form-label {
            display: block;
            margin-bottom: var(--space-sm);
            font-weight: 500;
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: var(--space-md);
            padding-left: 48px;
            background-color: var(--color-bg-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text-main);
            font-size: var(--font-size-base);
            transition: all var(--transition-fast);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 229, 255, 0.1);
        }

        .form-icon {
            position: absolute;
            left: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-muted);
            pointer-events: none;
        }

        .btn-login {
            width: 100%;
            padding: var(--space-md);
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            color: #000;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: var(--font-size-base);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 229, 255, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: var(--space-lg);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--color-border);
            color: var(--color-text-muted);
            font-size: var(--font-size-sm);
        }
    </style>
</head>

<body>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <span class="material-symbols-outlined">shield</span>
                </div>
                <h1 class="login-title">WP Safe Mode</h1>
                <p class="login-subtitle">Secure WordPress Management</p>
            </div>

            <form id="login-form" method="post">
                <?php if (isset($data['message'])): ?>
                    <div class="card"
                        style="background-color: var(--color-danger); border-color: var(--color-danger); margin-bottom: var(--space-lg); padding: var(--space-md);">
                        <div style="display: flex; align-items: center; gap: var(--space-sm);">
                            <span class="material-symbols-outlined">error</span>
                            <span><?php echo htmlspecialchars($data['message']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="username">Username or Email</label>
                    <div class="form-input-wrapper">
                        <span class="material-symbols-outlined form-icon">person</span>
                        <input type="text" id="username" name="username" class="form-input"
                            placeholder="Enter your username or email" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="form-input-wrapper">
                        <span class="material-symbols-outlined form-icon">lock</span>
                        <input type="password" id="password" name="password" class="form-input"
                            placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" name="submit_login" class="btn-login">
                    <span class="material-symbols-outlined">login</span>
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <p>Protected by WP Safe Mode v1.0.1</p>
            </div>
        </div>
    </div>

</body>

</html>