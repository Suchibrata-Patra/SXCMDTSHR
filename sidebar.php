<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));

require_once 'db_config.php';
$sidebarLabels = getLabelCounts($userEmail);
$unlabeledCount = getUnlabeledEmailCount($userEmail);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 340px;
            --nature-primary: #c41e3a;
            --nature-primary-light: #d63651;
            --nature-dark: #0a0e13;
            --nature-charcoal: #151a21;
            --nature-slate: #1e2530;
            --nature-gray: #4a5568;
            --nature-muted: #6b7280;
            --nature-border: #2d3748;
            --nature-border-light: #374151;
            --nature-bg: #0f141a;
            --nature-surface: #161b22;
            --nature-hover: #1f2937;
            --nature-active: rgba(196, 30, 58, 0.08);
            --nature-gold: #d4af37;
            --nature-platinum: #e5e7eb;
            --nature-glass: rgba(22, 27, 34, 0.7);
            --glow-primary: rgba(196, 30, 58, 0.3);
            --glow-gold: rgba(212, 175, 55, 0.2);
            --shadow-premium: 0 20px 60px rgba(0, 0, 0, 0.4);
            --shadow-glow: 0 0 40px var(--glow-primary);
            --shadow-subtle: 0 4px 16px rgba(0, 0, 0, 0.2);
            --transition-smooth: cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0a0e13 0%, #151a21 100%);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        /* Animated Background Particles */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0) translateX(0);
                opacity: 0.3;
            }

            50% {
                transform: translateY(-20px) translateX(10px);
                opacity: 0.6;
            }
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: var(--nature-gold);
            border-radius: 50%;
            opacity: 0.3;
            animation: float 6s infinite;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 24px;
            left: 24px;
            z-index: 10002;
            background: var(--nature-surface);
            border: 1px solid var(--nature-border);
            color: var(--nature-platinum);
            padding: 14px;
            border-radius: 16px;
            cursor: pointer;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-premium);
            transition: all 0.4s var(--transition-smooth);
        }

        .mobile-toggle:hover {
            background: var(--nature-primary);
            border-color: var(--nature-primary);
            box-shadow: var(--shadow-glow);
            transform: scale(1.05);
        }

        /* Sidebar Container */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--nature-bg);
            background: linear-gradient(180deg, var(--nature-charcoal) 0%, var(--nature-bg) 100%);
            border-right: 1px solid var(--nature-border);
            display: flex;
            flex-direction: column;
            height: 100vh;
            color: var(--nature-platinum);
            transition: transform 0.5s var(--transition-smooth);
            position: sticky;
            top: 0;
            box-shadow: var(--shadow-premium);
            z-index: 10000;
            overflow: hidden;
        }

        /* Elegant Gradient Overlay */
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 300px;
            background: radial-gradient(ellipse at top, rgba(196, 30, 58, 0.1) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Header Section */
        .sidebar-header {
            padding: 36px 28px;
            border-bottom: 1px solid var(--nature-border);
            background: var(--nature-glass);
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 1;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 18px;
            text-decoration: none;
            transition: all 0.5s var(--transition-smooth);
            position: relative;
        }

        .logo:hover {
            transform: translateX(4px);
        }

        .logo-image-wrapper {
            position: relative;
            width: 64px;
            height: 64px;
        }

        .logo-image-wrapper::before {
            content: '';
            position: absolute;
            inset: -4px;
            background: linear-gradient(135deg, var(--nature-primary), var(--nature-gold));
            border-radius: 18px;
            opacity: 0;
            transition: opacity 0.4s var(--transition-smooth);
            z-index: -1;
            filter: blur(12px);
        }

        .logo:hover .logo-image-wrapper::before {
            opacity: 0.6;
        }

        .logo-image {
            width: 64px;
            height: 64px;
            object-fit: contain;
            border-radius: 16px;
            border: 2px solid var(--nature-border-light);
            box-shadow: var(--shadow-subtle);
            transition: all 0.4s var(--transition-smooth);
        }

        .logo:hover .logo-image {
            border-color: var(--nature-primary);
            box-shadow: 0 8px 24px rgba(196, 30, 58, 0.3);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .logo-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 600;
            color: var(--nature-platinum);
            line-height: 1;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 0%, #e5e7eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-subtitle {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 10px;
            font-weight: 600;
            color: var(--nature-muted);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Navigation Section */
        .nav-section {
            flex: 1;
            padding: 20px 20px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--nature-border) transparent;
            position: relative;
            z-index: 1;
        }

        .nav-section::-webkit-scrollbar {
            width: 6px;
        }

        .nav-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-section::-webkit-scrollbar-thumb {
            background: var(--nature-border);
            border-radius: 3px;
            transition: background 0.3s;
        }

        .nav-section::-webkit-scrollbar-thumb:hover {
            background: var(--nature-primary);
        }

        /* Navigation Items */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            text-decoration: none;
            color: var(--nature-muted);
            font-size: 15px;
            font-weight: 600;
            border-radius: 14px;
            margin-bottom: 6px;
            transition: all 0.3s var(--transition-smooth);
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: linear-gradient(180deg, var(--nature-primary), var(--nature-gold));
            transform: scaleY(0);
            transition: transform 0.3s var(--transition-smooth);
        }

        .nav-item::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(196, 30, 58, 0.05), rgba(212, 175, 55, 0.05));
            opacity: 0;
            transition: opacity 0.3s var(--transition-smooth);
            z-index: -1;
        }

        .nav-item .material-icons-round {
            font-size: 22px;
            transition: all 0.4s var(--transition-bounce);
            position: relative;
            z-index: 1;
        }

        .nav-item:hover {
            background: var(--nature-hover);
            color: var(--nature-platinum);
            border-color: var(--nature-border-light);
            transform: translateX(4px);
        }

        .nav-item:hover::after {
            opacity: 1;
        }

        .nav-item:hover .material-icons-round {
            transform: scale(1.15) rotate(5deg);
            color: var(--nature-primary);
        }

        .nav-item.active {
            background: var(--nature-active);
            color: var(--nature-primary);
            font-weight: 700;
            border-color: rgba(196, 30, 58, 0.3);
            box-shadow: 0 4px 16px rgba(196, 30, 58, 0.15);
        }

        .nav-item.active::before {
            transform: scaleY(1);
        }

        .nav-item.active::after {
            opacity: 1;
        }

        .nav-item.active .material-icons-round {
            color: var(--nature-primary);
            transform: scale(1.1);
            filter: drop-shadow(0 0 8px var(--glow-primary));
        }

        /* Section Title */
        .nav-section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--nature-gold);
            padding: 28px 18px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            letter-spacing: 2px;
            position: relative;
        }

        .nav-section-title::after {
            content: '';
            position: absolute;
            bottom: 6px;
            left: 18px;
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, var(--nature-gold), transparent);
        }

        .manage-labels-btn {
            color: var(--nature-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 6px;
            border-radius: 10px;
            transition: all 0.3s var(--transition-smooth);
            border: 1px solid transparent;
        }

        .manage-labels-btn:hover {
            background: var(--nature-hover);
            color: var(--nature-gold);
            border-color: var(--nature-border-light);
            transform: rotate(90deg);
        }

        /* Label Items */
        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            text-decoration: none;
            color: var(--nature-muted);
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            margin-bottom: 4px;
            transition: all 0.3s var(--transition-smooth);
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .label-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at left, rgba(255, 255, 255, 0.03), transparent);
            opacity: 0;
            transition: opacity 0.3s var(--transition-smooth);
        }

        .label-item:hover {
            background: var(--nature-hover);
            color: var(--nature-platinum);
            border-color: var(--nature-border-light);
            transform: translateX(4px);
        }

        .label-item:hover::before {
            opacity: 1;
        }

        .label-content {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .label-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            box-shadow: 0 0 12px currentColor;
            transition: all 0.3s var(--transition-bounce);
            position: relative;
        }

        .label-dot::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 4px;
            border: 1px solid currentColor;
            opacity: 0;
            transition: opacity 0.3s var(--transition-smooth);
        }

        .label-item:hover .label-dot {
            transform: scale(1.3) rotate(45deg);
        }

        .label-item:hover .label-dot::after {
            opacity: 0.5;
        }

        .label-count {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: var(--nature-muted);
            background: var(--nature-slate);
            padding: 4px 10px;
            border-radius: 20px;
            min-width: 28px;
            text-align: center;
            border: 1px solid var(--nature-border);
            transition: all 0.3s var(--transition-smooth);
        }

        .label-item:hover .label-count {
            background: var(--nature-primary);
            color: white;
            border-color: var(--nature-primary);
            transform: scale(1.05);
        }

        /* User Footer */
        .user-footer {
            padding: 24px;
            border-top: 1px solid var(--nature-border);
            background: var(--nature-glass);
            backdrop-filter: blur(20px);
            position: relative;
            z-index: 1;
        }

        .user-card {
            background: var(--nature-surface);
            border: 1px solid var(--nature-border-light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-subtle);
            transition: all 0.4s var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--nature-primary), var(--nature-gold), transparent);
            opacity: 0;
            transition: opacity 0.3s var(--transition-smooth);
        }

        .user-card:hover {
            border-color: var(--nature-primary);
            box-shadow: 0 8px 32px rgba(196, 30, 58, 0.2);
            transform: translateY(-2px);
        }

        .user-card:hover::before {
            opacity: 1;
        }

        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 9px;
            font-weight: 700;
            color: #10b981;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            background: rgba(16, 185, 129, 0.1);
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .auth-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 8px #10b981;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.6;
                transform: scale(0.8);
            }
        }

        .user-email {
            font-size: 13px;
            font-weight: 600;
            color: var(--nature-platinum);
            word-break: break-all;
            display: block;
            font-family: 'Space Grotesk', sans-serif;
        }

        /* Footer Actions */
        .footer-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            flex: 1;
            text-decoration: none;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            padding: 12px 14px;
            border-radius: 12px;
            transition: all 0.4s var(--transition-smooth);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
            border: 1px solid;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s var(--transition-smooth);
        }

        .action-btn:hover::before {
            opacity: 1;
        }

        .config-btn {
            color: var(--nature-platinum);
            background: var(--nature-slate);
            border-color: var(--nature-border-light);
        }

        .config-btn:hover {
            background: var(--nature-charcoal);
            border-color: var(--nature-gold);
            color: var(--nature-gold);
            box-shadow: 0 4px 20px var(--glow-gold);
            transform: translateY(-2px);
        }

        .logout-btn {
            color: white;
            background: linear-gradient(135deg, var(--nature-primary), var(--nature-primary-light));
            border-color: var(--nature-primary);
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, var(--nature-primary-light), var(--nature-primary));
            box-shadow: var(--shadow-glow);
            transform: translateY(-2px);
        }

        .action-btn .material-icons-round {
            font-size: 16px;
            transition: transform 0.3s var(--transition-bounce);
        }

        .action-btn:hover .material-icons-round {
            transform: scale(1.2) rotate(5deg);
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(10, 14, 19, 0.8);
            backdrop-filter: blur(8px);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.4s var(--transition-smooth);
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: flex;
            }

            .sidebar {
                position: fixed;
                left: 0;
                transform: translateX(-100%);
                z-index: 10000;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            :root {
                --sidebar-width: 300px;
            }
        }

        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Focus States */
        .nav-item:focus,
        .label-item:focus,
        .action-btn:focus,
        .manage-labels-btn:focus {
            outline: 2px solid var(--nature-primary);
            outline-offset: 3px;
        }

        /* Micro Interactions */
        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        .logo-title:hover {
            background: linear-gradient(90deg, #ffffff 25%, var(--nature-gold) 50%, #ffffff 75%);
            background-size: 200% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 3s infinite;
        }
    </style>
</head>

<body>

    <!-- Animated Particles Background -->
    <div class="particles" id="particles"></div>

    <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">
        <span class="material-icons-round">menu</span>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="mainSidebar">
        <!-- Header -->
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <div class="logo-image-wrapper">
                    <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                        alt="Institutional Logo" class="logo-image">
                </div>
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">Official Portal</span>
                </div>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round">edit_note</span>
                <span>Compose</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round">history</span>
                <span>All Mail</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons-round">analytics</span>
                <span>Analytics</span>
            </a>

            <!-- Labels Section -->
            <div class="nav-section-title">
                Labels
                <a href="manage_labels.php" class="manage-labels-btn" title="Manage Labels">
                    <span class="material-icons-round" style="font-size: 18px;">settings</span>
                </a>
            </div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-content">
                    <div class="label-dot"
                        style="background-color: <?= htmlspecialchars($label['label_color']) ?>; color: <?= htmlspecialchars($label['label_color']) ?>;">
                    </div>
                    <span>
                        <?= htmlspecialchars($label['label_name']) ?>
                    </span>
                </div>
                <?php if (isset($label['count']) && $label['count'] > 0): ?>
                <span class="label-count">
                    <?= $label['count'] ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- User Footer -->
        <div class="user-footer">
            <div class="user-card">
                <span class="auth-badge">Authenticated</span>
                <span class="user-email">
                    <?= htmlspecialchars($userEmail) ?>
                </span>
            </div>

            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">
                    <span class="material-icons-round">tune</span>
                    <span>Config</span>
                </a>
                <a href="logout.php" class="action-btn logout-btn">
                    <span class="material-icons-round">logout</span>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Generate floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        createParticles();

        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.querySelector('.sidebar-overlay');

            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }

        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('mainSidebar').classList.contains('open')) {
                toggleSidebar();
            }
        });

        // Smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Add parallax effect to sidebar on scroll
        const navSection = document.querySelector('.nav-section');
        navSection.addEventListener('scroll', () => {
            const scrolled = navSection.scrollTop;
            document.querySelector('.sidebar::before')?.style.setProperty('transform', `translateY(${scrolled * 0.3}px)`);
        });
    </script>
</body>

</html>