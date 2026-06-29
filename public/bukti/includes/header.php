<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti - Platform Kerja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-bg: linear-gradient(180deg, #1a1a1a 0%, #0d0d0d 100%);
            --sidebar-text: rgba(255,255,255,0.45);
            --sidebar-hover: rgba(255,255,255,0.85);
            --body-bg: #f5f3ef;
            --card-bg: #ffffff;
            --primary: #eab308;
            --primary-light: #facc15;
            --primary-glow: rgba(234, 179, 8, 0.15);
            --text-dark: #1a1a1a;
            --text-muted: #6b7280;
            --border-color: rgba(0,0,0,0.06);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.06);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.08);
            --radius: 16px;
            --radius-sm: 10px;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background-color: var(--body-bg);
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
            color: var(--text-dark);
        }
        
        /* ═══════════════════════════════════════════
           SIDEBAR - Premium Dark with Gold Accents
           ═══════════════════════════════════════════ */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            position: fixed;
            top: 16px;
            left: 16px;
            bottom: 16px;
            z-index: 1000;
            border-radius: 20px;
            padding-top: 28px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
            background-size: 200% 100%;
            animation: shimmerBar 3s ease-in-out infinite;
        }
        
        @keyframes shimmerBar {
            0%, 100% { background-position: 200% 0; }
            50% { background-position: -200% 0; }
        }
        
        .sidebar-brand {
            padding: 0 28px 28px;
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a1a1a;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(234, 179, 8, 0.3);
        }
        
        .nav-link {
            color: var(--sidebar-text);
            padding: 11px 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.25s ease;
            text-decoration: none;
            position: relative;
            margin: 2px 12px;
            border-radius: var(--radius-sm);
        }
        
        .nav-link i { font-size: 1.1rem; }
        
        .nav-link:hover {
            color: var(--sidebar-hover);
            background: rgba(255,255,255,0.06);
        }
        
        .nav-link.active {
            color: var(--primary-light);
            background: rgba(234, 179, 8, 0.1);
            font-weight: 600;
        }
        
        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 15%;
            height: 70%;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary), var(--primary-light));
            border-radius: 0 4px 4px 0;
        }
        
        .sidebar .section-label {
            padding: 0 28px;
            margin-top: 20px;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            color: rgba(255,255,255,0.2);
        }
        
        .sidebar .btn-center {
            margin: 12px;
            padding: 10px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .sidebar .btn-center:hover {
            border-color: var(--primary);
            color: var(--primary-light);
            background: rgba(234, 179, 8, 0.08);
        }
        
        /* ═══════════════════════════════════════════
           MAIN LAYOUT
           ═══════════════════════════════════════════ */
        .main-wrapper {
            margin-left: 292px;
            padding: 24px;
            display: flex;
            gap: 24px;
            min-height: 100vh;
        }
        
        .content-area { flex: 1; min-width: 0; }
        .widget-area { width: 310px; flex-shrink: 0; }
        
        /* ═══════════════════════════════════════════
           CARDS - Clean & Premium
           ═══════════════════════════════════════════ */
        .card-custom {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .card-custom:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        /* Status Badges */
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .badge-todo {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .badge-progress {
            background: #eff6ff;
            color: #3b82f6;
        }
        
        .badge-done {
            background: #f0fdf4;
            color: #16a34a;
        }
        
        /* Greeting Header */
        .greeting-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        
        .greeting-card h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .greeting-card h3 span {
            color: var(--primary);
        }
        
        .greeting-card p {
            color: var(--text-muted);
            margin: 4px 0 0;
            font-size: 0.9rem;
        }
        
        /* View Toggle Buttons */
        .view-toggle .btn {
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-muted);
            transition: all 0.25s ease;
        }
        
        .view-toggle .btn.active,
        .view-toggle .btn:hover {
            background: var(--text-dark);
            color: white;
            border-color: var(--text-dark);
        }
        
        /* Post Input */
        .post-input-area {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: box-shadow 0.3s ease;
        }
        
        .post-input-area:focus-within {
            box-shadow: 0 0 0 3px var(--primary-glow);
            border-color: var(--primary);
        }
        
        .post-input-area input,
        .post-input-area textarea {
            border: none;
            background: transparent;
            width: 100%;
            outline: none;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        
        .post-input-area input::placeholder,
        .post-input-area textarea::placeholder {
            color: #9ca3af;
        }
        
        /* Post Card Content */
        .post-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px 8px;
        }
        
        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a1a1a;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        
        .post-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            object-fit: cover;
        }
        
        .post-meta .post-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        
        .post-meta .post-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .post-body {
            padding: 8px 20px 16px;
        }
        
        .post-body h6 {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 6px;
        }
        
        .post-body p {
            font-size: 0.88rem;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 4px;
        }
        
        .post-actions {
            padding: 0 20px 14px;
            display: flex;
            gap: 16px;
        }
        
        .post-actions .btn {
            font-size: 0.8rem;
            color: var(--text-muted);
            padding: 5px 12px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            transition: all 0.2s ease;
        }
        
        .post-actions .btn:hover {
            background: var(--primary-glow);
            color: var(--primary);
            border-color: rgba(234, 179, 8, 0.2);
        }
        
        /* Widget Area */
        .widget-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
        }
        
        .widget-card h6 {
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .widget-card label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .widget-card .form-control,
        .widget-card .form-select {
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            font-size: 0.85rem;
            padding: 8px 12px;
            transition: all 0.25s ease;
        }
        
        .widget-card .form-control:focus,
        .widget-card .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        
        .widget-card .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            color: #1a1a1a;
            font-weight: 600;
            border-radius: var(--radius-sm);
            padding: 10px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .widget-card .btn-primary:hover {
            box-shadow: 0 4px 16px rgba(234, 179, 8, 0.35);
            transform: translateY(-1px);
        }
        
        /* Stats Widget */
        .stat-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .stat-item .stat-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .stat-item .stat-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .stat-item .stat-value {
            font-weight: 700;
            font-size: 0.95rem;
        }
        
        /* ═══════════════════════════════════════════
           MOBILE NAVBAR
           ═══════════════════════════════════════════ */
        .mobile-header {
            display: none;
            padding: 14px 20px;
            background: linear-gradient(135deg, #1a1a1a, #0d0d0d);
            color: white;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1001;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }
        
        .mobile-header .brand-icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1a1a1a;
            font-size: 0.85rem;
        }
        
        /* ═══════════════════════════════════════════
           MENTIONS & TAGGING
           ═══════════════════════════════════════════ */
        .mention-list {
            position: absolute;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            max-height: 200px;
            overflow-y: auto;
            width: 260px;
            z-index: 9999;
            box-shadow: var(--shadow-lg);
            display: none;
        }
        
        .mention-item {
            padding: 10px 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            border-bottom: 1px solid #f8f9fa;
            transition: background 0.15s ease;
        }
        
        .mention-item:hover {
            background: var(--primary-glow);
        }
        
        .mention-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        /* ═══════════════════════════════════════════
           TIMELINE IN MODAL
           ═══════════════════════════════════════════ */
        .timeline-box {
            border-left: 2px solid #e5e7eb;
            margin-left: 10px;
            padding-left: 20px;
            margin-bottom: 20px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 15px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 5px;
            width: 12px;
            height: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 2px rgba(234, 179, 8, 0.2);
        }
        
        /* ═══════════════════════════════════════════
           MODAL STYLING
           ═══════════════════════════════════════════ */
        .modal-content {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 16px 20px;
        }
        
        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1rem;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 12px 20px;
        }
        
        /* ═══════════════════════════════════════════
           ANIMATIONS
           ═══════════════════════════════════════════ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card-custom,
        .widget-card,
        .greeting-card,
        .post-input-area {
            animation: fadeInUp 0.4s ease forwards;
        }
        
        .card-custom:nth-child(2) { animation-delay: 0.05s; }
        .card-custom:nth-child(3) { animation-delay: 0.1s; }
        .card-custom:nth-child(4) { animation-delay: 0.15s; }
        
        /* ═══════════════════════════════════════════
           SCROLLBAR
           ═══════════════════════════════════════════ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        
        /* ═══════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════ */
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-110%); }
            .sidebar.show { transform: translateX(0); left: 0; top: 0; bottom: 0; border-radius: 0; width: 280px; }
            .main-wrapper { margin-left: 0; flex-direction: column; padding: 16px; }
            .widget-area { width: 100%; order: -1; }
            .mobile-header { display: flex; }
        }
    </style>
</head>
<body>
<div class="mobile-header d-lg-none">
    <div class="d-flex align-items-center gap-2 fw-bold"><span class="brand-icon"><i class="bi bi-check2-square"></i></span> Bukti</div>
    <button class="btn btn-dark p-0" onclick="document.querySelector('.sidebar').classList.toggle('show')"><i class="bi bi-list fs-3"></i></button>
</div>