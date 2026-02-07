<?php
$configFile = 'settings/config.json';
$storeName = 'Loja';
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    $storeName = $config['store']['name'] ?? 'Loja';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Página não encontrada -
        <?php echo htmlspecialchars($storeName); ?>
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/globals.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f5f5f5;
            text-align: center;
            padding: 24px;
            font-family: 'Inter', sans-serif;
        }

        .container {
            background: white;
            padding: 40px 32px;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            max-width: 400px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .error-icon {
            width: 64px;
            height: 64px;
            color: #fe2c55;
            margin-bottom: 24px;
            background: #fff0f0;
            padding: 16px;
            border-radius: 50%;
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            color: #121212;
            margin-bottom: 12px;
        }

        p {
            color: #757575;
            margin-bottom: 32px;
            font-size: 14px;
            line-height: 1.5;
        }

        .btn-back {
            background: #fe2c55;
            color: white;
            padding: 14px 32px;
            border-radius: 99px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s;
            width: 100%;
        }

        .btn-back:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <div class="container">
        <svg class="error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <h1>Página não encontrada</h1>
        <p>A página que você está tentando acessar não está disponível ou foi removida.</p>
        <button onclick="history.back()" class="btn-back">
            Voltar
        </button>
    </div>
</body>

</html>