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
        :root { --sidebar-bg: #000000; --sidebar-text: #888; --body-bg: #f4f6f8; --card-bg: #ffffff; --primary: #0d6efd; }
        body { background-color: var(--body-bg); font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }
        
        /* Sidebar Desktop */
        .sidebar { width: 260px; background: var(--sidebar-bg); position: fixed; top: 20px; left: 20px; bottom: 20px; z-index: 1000; border-radius: 20px; padding-top: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; flex-direction: column; transition: transform 0.3s ease; }
        .sidebar-brand { padding: 0 30px 30px; color: white; font-weight: 700; font-size: 1.4rem; display: flex; align-items: center; gap: 12px; }
        .nav-link { color: var(--sidebar-text); padding: 12px 30px; display: flex; align-items: center; gap: 15px; font-weight: 500; transition: 0.2s; text-decoration: none; position: relative; }
        .nav-link:hover, .nav-link.active { color: #fff; }
        .nav-link.active::before { content: ''; position: absolute; left: 0; top: 10%; height: 80%; width: 4px; background: #fff; border-radius: 0 5px 5px 0; }
        
        .main-wrapper { margin-left: 300px; padding: 30px; display: flex; gap: 30px; }
        .content-area { flex: 1; min-width: 0; }
        .widget-area { width: 320px; flex-shrink: 0; }
        
        .card-custom { background: var(--card-bg); border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 20px; overflow: hidden; position: relative; }
        
        /* Mobile Navbar */
        .mobile-header { display: none; padding: 15px 20px; background: #000; color: white; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1001; }
        
        /* Tagging & Autocomplete */
        .mention-list { position: absolute; background: white; border: 1px solid #e0e0e0; border-radius: 12px; max-height: 200px; overflow-y: auto; width: 250px; z-index: 9999; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: none; }
        .mention-item { padding: 10px 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; border-bottom: 1px solid #f8f9fa; }
        .mention-item:hover { background: #f0f5ff; }
        
        /* Timeline in Modal */
        .timeline-box { border-left: 2px solid #e9ecef; margin-left: 10px; padding-left: 20px; margin-bottom: 20px; }
        .timeline-item { position: relative; margin-bottom: 15px; }
        .timeline-item::before { content: ''; position: absolute; left: -25px; top: 5px; width: 12px; height: 12px; background: var(--primary); border-radius: 50%; border: 2px solid white; box-shadow: 0 0 0 1px #e9ecef; }
        
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-110%); }
            .sidebar.show { transform: translateX(0); left: 0; top: 0; bottom: 0; border-radius: 0; width: 280px; }
            .main-wrapper { margin-left: 0; flex-direction: column; padding: 15px; }
            .widget-area { width: 100%; order: -1; }
            .mobile-header { display: flex; }
        }
    </style>
</head>
<body>
<div class="mobile-header d-lg-none">
    <div class="d-flex align-items-center gap-2 fw-bold"><i class="bi bi-check2-square"></i> Bukti</div>
    <button class="btn btn-dark p-0" onclick="document.querySelector('.sidebar').classList.toggle('show')"><i class="bi bi-list fs-3"></i></button>
</div>