<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRMFinity AI - Intelligent Underwriting Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        :root {
            --primary: #0A2E65;
            --secondary: #0066FF;
            --accent: #00D4FF;
            --success: #00C48C;
            --dark: #0F172A;
            --light: #F8FAFC;
            --text: #334155;
            --border: #E2E8F0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
            font-weight: 400;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Space Grotesk', 'Inter', sans-serif;
            font-weight: 700;
            color: var(--dark);
            letter-spacing: -0.02em;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 30px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Space Grotesk', sans-serif;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon-inner {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0066FF 0%, #00D4FF 100%);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 102, 255, 0.25);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .logo:hover .logo-icon-inner {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(0, 102, 255, 0.4);
        }

        .logo-icon-inner::before {
            content: 'C';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            font-weight: 900;
            color: white;
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: -1px;
        }

        .logo-icon-inner::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .logo-brand {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -1px;
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .logo-ai {
            background: linear-gradient(135deg, #0066FF, #00D4FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            font-size: 28px;
            position: relative;
            display: inline-block;
        }

        .logo-ai::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(135deg, #0066FF, #00D4FF);
            border-radius: 2px;
        }

        .logo-tagline {
            font-size: 9px;
            font-weight: 700;
            color: rgba(0, 102, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 1px;
        }

        .nav-menu {
            display: flex;
            gap: 40px;
            align-items: center;
        }

        .nav-link {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: var(--secondary);
        }

        .btn {
            padding: 16px 36px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            font-size: 16px;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            color: white;
            box-shadow: 0 4px 15px rgba(0, 102, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 102, 255, 0.4);
        }

        .btn-outline {
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #1e40af 100%);
            padding: 80px 0 100px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='2' fill='white' fill-opacity='0.1'/%3E%3C/svg%3E");
            z-index: 1;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.12) 0%, transparent 50%);
            z-index: 1;
        }

        /* Floating orbs */
        .hero .container::before {
            content: '';
            position: absolute;
            top: 10%;
            left: 10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(40px);
            z-index: 1;
        }

        .hero .container::after {
            content: '';
            position: absolute;
            bottom: 10%;
            right: 10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(50px);
            z-index: 1;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-text h1 {
            font-size: 60px;
            line-height: 1.05;
            color: white;
            margin-bottom: 24px;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .hero-text p {
            font-size: 20px;
            color: rgba(255,255,255,0.9);
            margin-bottom: 40px;
            line-height: 1.6;
            font-weight: 400;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
        }

        .hero-image {
            position: relative;
            perspective: 1000px;
        }

        .hero-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
        }

        .hero-image:hover img {
            transform: translateY(-10px) rotateY(2deg);
        }

        /* Stats Section */
        .stats {
            background: white;
            padding: 50px 0;
            margin-top: -60px;
            position: relative;
            z-index: 10;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
        }

        .stat-card {
            text-align: center;
            padding: 40px 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 102, 255, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: rgba(0, 102, 255, 0.2);
        }

        .stat-number {
            font-size: 52px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 15px;
            color: var(--text);
            font-weight: 500;
        }

        /* Features Section */
        .features {
            padding: 80px 0;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            position: relative;
            overflow: hidden;
        }

        .features::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='50' height='50' viewBox='0 0 50 50' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='rgba(59,130,246,0.5)' stroke-width='1'%3E%3Cline x1='25' y1='0' x2='25' y2='50'/%3E%3Cline x1='0' y1='25' x2='50' y2='25'/%3E%3C/g%3E%3Ccircle cx='25' cy='25' r='1.5' fill='%2310b981'/%3E%3C/svg%3E");
            opacity: 0.1;
            z-index: 1;
        }

        .features::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            z-index: 1;
        }

        .features .container {
            position: relative;
            z-index: 2;
        }

        .features h2,
        .features h3 {
            color: white;
        }

        .features .section-header p {
            color: rgba(255, 255, 255, 0.8);
        }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 50px;
        }

        .section-header h2 {
            font-size: 48px;
            margin-bottom: 16px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .section-header p {
            font-size: 18px;
            color: var(--text);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .feature-card h3 {
            font-size: 22px;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: white !important;
        }

        .feature-card p {
            color: rgba(255, 255, 255, 0.85) !important;
            line-height: 1.7;
            font-size: 16px;
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 8px 20px rgba(0, 102, 255, 0.3);
        }

        .features .container::before {
            content: '';
            position: absolute;
            bottom: 10%;
            left: 5%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            z-index: 1;
        }

        .feature-icon svg {
            width: 32px;
            height: 32px;
            color: white;
        }

        /* How It Works */
        .how-it-works {
            padding: 80px 0;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .how-it-works::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='400' height='400' viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%231e40af' stroke-width='2' opacity='0.03'%3E%3Cpath d='M 0 200 Q 100 150 200 200 T 400 200' /%3E%3Cpath d='M 0 250 Q 100 200 200 250 T 400 250' /%3E%3Ccircle cx='200' cy='200' r='80' /%3E%3Ccircle cx='200' cy='200' r='120' /%3E%3Ccircle cx='200' cy='200' r='160' stroke='%2310b981' /%3E%3C/g%3E%3C/svg%3E");
            opacity: 1;
            z-index: 1;
        }

        .how-it-works .container {
            position: relative;
            z-index: 2;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            margin-top: 0;
        }

        .step-card {
            position: relative;
            text-align: center;
            padding: 30px;
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .step-card:hover {
            background: rgba(0, 102, 255, 0.03);
            transform: translateY(-5px);
        }

        .step-number {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 36px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 102, 255, 0.3);
            letter-spacing: -0.02em;
        }

        .step-card h3 {
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .step-card p {
            color: var(--text);
            line-height: 1.7;
        }

        /* Benefits Section */
        .benefits {
            padding: 80px 0;
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            color: var(--text);
            position: relative;
            overflow: hidden;
        }

        .benefits::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='600' height='600' viewBox='0 0 600 600' xmlns='http://www.w3.org/2000/svg'%3E%3Cdefs%3E%3ClinearGradient id='grad' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%231e40af;stop-opacity:0.04' /%3E%3Cstop offset='100%25' style='stop-color:%2310b981;stop-opacity:0.04' /%3E%3C/linearGradient%3E%3C/defs%3E%3Cg stroke='url(%23grad)' fill='none' stroke-width='2'%3E%3Cline x1='0' y1='100' x2='600' y2='500' /%3E%3Cline x1='0' y1='200' x2='600' y2='400' /%3E%3Cline x1='100' y1='0' x2='500' y2='600' /%3E%3Ccircle cx='150' cy='150' r='40' /%3E%3Ccircle cx='450' cy='450' r='60' /%3E%3Ccircle cx='300' cy='300' r='30' /%3E%3Ccircle cx='500' cy='200' r='50' /%3E%3C/g%3E%3C/svg%3E");
            opacity: 1;
            z-index: 1;
        }

        .benefits .container {
            position: relative;
            z-index: 2;
        }

        .benefits h2,
        .benefits h3 {
            color: var(--dark);
        }

        .benefits p {
            color: var(--text);
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .benefit-card {
            background: white;
            padding: 45px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
            border: 1px solid rgba(0, 102, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        .benefit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .benefit-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            border-color: rgba(0, 102, 255, 0.15);
        }

        .benefit-card:hover::before {
            transform: scaleX(1);
        }

        .benefit-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 8px 20px rgba(0, 102, 255, 0.3);
        }

        /* CTA Section */
        .cta {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--accent) 100%);
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='2' fill='white' fill-opacity='0.15'/%3E%3C/svg%3E");
            opacity: 1;
        }

        .cta .container {
            position: relative;
            z-index: 2;
        }

        .cta h2 {
            font-size: 52px;
            color: white;
            margin-bottom: 24px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .cta p {
            font-size: 22px;
            margin-bottom: 48px;
            opacity: 0.95;
        }

        .cta .btn {
            background: white;
            color: var(--secondary);
            font-size: 18px;
            padding: 20px 52px;
            font-weight: 700;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .cta .btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 16px 50px rgba(0,0,0,0.3);
        }

        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 60px 0 30px;
        }

        .footer .logo-brand {
            color: white;
        }

        .footer .logo-tagline {
            color: rgba(255, 255, 255, 0.6);
        }

        .footer .logo-ai::after {
            background: linear-gradient(135deg, #0066FF, #00D4FF);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }

        .footer-col h4 {
            color: white;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 30px;
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        @media (max-width: 1024px) {
            .hero-content,
            .features-grid,
            .steps-grid,
            .benefits-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero-text h1 {
                font-size: 40px;
            }

            .nav-menu .nav-link {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .stats-grid,
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">
                        <div class="logo-icon-inner"></div>
                    </div>
                    <div class="logo-text">
                        <div class="logo-brand">
                            CRMFinity<span class="logo-ai">AI</span>
                        </div>
                        <div class="logo-tagline">Smart Underwriting</div>
                    </div>
                </div>
                <nav class="nav-menu">
                    <a href="#features" class="nav-link">Features</a>
                    <a href="#how-it-works" class="nav-link">How It Works</a>
                    <a href="#benefits" class="nav-link">Benefits</a>
                    <a href="{{ route('login') }}" class="btn btn-primary">Get Started</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Turn Bank Statements Into Funded Deals — In Seconds</h1>
                    <p>The AI-powered platform that helps MCA brokers close 10x more deals. Instantly analyze cash flow, spot hidden revenue, flag existing MCAs, and create funder-ready reports that get approved faster.</p>
                    <div class="hero-buttons">
                        <a href="{{ route('login') }}" class="btn btn-primary">Start Free Trial</a>
                        <a href="#how-it-works" class="btn btn-outline">Watch Demo</a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="/images/bank-statement-display.jpg" alt="Bank Statement Analysis"
                         onerror="this.style.display='none';">
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Active Brokers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">3 Sec</div>
                    <div class="stat-label">Average Analysis Time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Revenue Accuracy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">10x</div>
                    <div class="stat-label">Faster Submissions</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <h2>Everything You Need to Win More Deals</h2>
                <p>Powerful AI tools that save you hours and help you close funding faster than ever</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3>Instant Statement Analysis</h3>
                    <p>Upload any bank statement and watch our AI work its magic. Automatically detects revenue, categorizes every transaction, and flags hidden MCAs in seconds—no manual work required.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3>Funder-Ready Reports in 30 Seconds</h3>
                    <p>Generate beautiful, comprehensive underwriting reports that make funders say "yes." Detailed cash flow breakdowns, risk scores, and insights that close deals faster.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3>Live Bank Data (No More Chasing)</h3>
                    <p>Send your merchant a secure link and get their bank data in minutes—not days. Real-time transactions, zero paperwork, and no more "I'll send it tomorrow."</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3>Spot Hidden Revenue Streams</h3>
                    <p>Our AI finds money others miss. Accurately calculates true monthly revenue, detects seasonal patterns, and uncovers revenue streams that boost your deal value.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3>Your Entire Pipeline in One Place</h3>
                    <p>Never lose track of a deal again. Organize merchants, store documents, track funder submissions, and manage every deal from first call to funding.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                        </svg>
                    </div>
                    <h3>Reports That Funders Actually Love</h3>
                    <p>Professional PDFs with everything funders need: cash flow breakdowns, revenue charts, MCA stacking analysis, and risk scores. Submit with confidence.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>How It Works</h2>
                <p>Submit your merchant deals to funders faster than ever before</p>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">01</div>
                    <h3>Upload Statements</h3>
                    <p>Upload your merchant's bank statements or send them a secure Plaid link to connect their bank directly.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">02</div>
                    <h3>AI Analysis</h3>
                    <p>Our AI analyzes every transaction, detects daily revenue, identifies existing MCAs, and calculates cash flow metrics.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">03</div>
                    <h3>Present to Funders</h3>
                    <p>Get a professional underwriting report in seconds. Export to PDF and submit to funders with confidence.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section id="benefits" class="benefits">
        <div class="container">
            <div class="section-header">
                <h2>Why Brokers Choose CRMFinity AI</h2>
                <p>Close more deals and impress funders with professional underwriting</p>
            </div>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 28px; height: 28px; color: white;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3>Submit Deals 10x Faster</h3>
                    <p>Stop spending hours manually reviewing statements. Analyze deals in seconds and submit to funders the same day.</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 28px; height: 28px; color: white;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3>Impress Funders</h3>
                    <p>Professional reports with accurate revenue detection, MCA identification, and detailed cash flow analysis that funders trust.</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 28px; height: 28px; color: white;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <h3>Manage Your Pipeline</h3>
                    <p>Track all merchant deals in one platform. Store documents, analysis, and funder communications in an organized workspace.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Close More MCA Deals?</h2>
            <p>Join hundreds of brokers using AI to analyze deals faster and impress funders</p>
            <a href="{{ route('login') }}" class="btn">Get Started Today</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="logo" style="margin-bottom: 20px;">
                        <div class="logo-icon">
                            <div class="logo-icon-inner"></div>
                        </div>
                        <div class="logo-text">
                            <div class="logo-brand">
                                CRMFinity<span class="logo-ai">AI</span>
                            </div>
                            <div class="logo-tagline">Smart Underwriting</div>
                        </div>
                    </div>
                    <p style="color: rgba(255,255,255,0.7);">AI-powered underwriting platform built for MCA brokers</p>
                </div>
                <div class="footer-col">
                    <h4>Platform</h4>
                    <ul class="footer-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#benefits">Benefits</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Company</h4>
                    <ul class="footer-links">
                        <li><a href="#">About</a></li>
                        <li><a href="#">Contact</a></li>
                        <li><a href="{{ route('login') }}">Sign In</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Legal</h4>
                    <ul class="footer-links">
                        <li><a href="#">Privacy</a></li>
                        <li><a href="#">Terms</a></li>
                        <li><a href="#">Security</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; {{ date('Y') }} CRMFinity AI. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
