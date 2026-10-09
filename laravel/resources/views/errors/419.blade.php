<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session renouvelée - OVANIE</title>
    <style>
        *{box-sizing:border-box} body{margin:0;font-family:Inter,Arial,sans-serif;background:#f5f8fc;color:#0f2a4f;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}.card{width:min(560px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 24px 70px rgba(15,42,79,.12);overflow:hidden}.bar{height:6px;background:linear-gradient(90deg,#1e3a5f,#245fa6,#f47a20)}.content{padding:42px;text-align:center}.badge{width:58px;height:58px;margin:0 auto 20px;border-radius:18px;background:#eef5ff;display:grid;place-items:center;color:#245fa6;font-size:28px;font-weight:800}h1{margin:0 0 12px;font-size:27px;line-height:1.15}p{margin:0 auto 26px;max-width:430px;color:#64748b;line-height:1.65;font-size:15px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 24px;border-radius:12px;background:#245fa6;color:#fff;text-decoration:none;font-weight:800}.btn:hover{background:#1e4f8d}.hint{margin-top:18px;font-size:12px;color:#94a3b8}@media(max-width:600px){.content{padding:32px 22px}h1{font-size:24px}}
    </style>
</head>
<body>
    <main class="card">
        <div class="bar"></div>
        <div class="content">
            <div class="badge">✓</div>
            <h1>Session sécurisée renouvelée</h1>
            <p>Pour protéger votre compte, OVANIE a renouvelé votre session. Rechargez simplement votre accès pour continuer.</p>
            <a class="btn" href="{{ url()->previous() ?: route('home') }}">Continuer</a>
            <div class="hint">Aucune donnée de votre compte n’a été supprimée.</div>
        </div>
    </main>
</body>
</html>
