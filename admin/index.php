<?php
// Handle Image Upload
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_GET['action'] === 'upload') {
        header('Content-Type: application/json');
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Erro no upload ou arquivo não enviado.']);
            exit;
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            echo json_encode(['success' => false, 'error' => 'Extensão não permitida.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../loja/database/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('upload_') . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            // Return relative path for frontend usage
            echo json_encode(['success' => true, 'path' => 'database/uploads/' . $filename]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Falha ao salvar arquivo no servidor.']);
        }
        exit;
    }

    // Handle File Delete
    if ($_GET['action'] === 'delete_file') {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $path = $data['path'] ?? '';

        // Security check: must be inside database/uploads/
        if (strpos($path, 'database/uploads/') !== 0 || strpos($path, '..') !== false) {
            echo json_encode(['success' => false, 'error' => 'Caminho inválido.']);
            exit;
        }

        $fullPath = __DIR__ . '/' . $path;
        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao deletar arquivo.']);
            }
        } else {
            // File doesn't exist, technically success
            echo json_encode(['success' => true]);
        }
        exit;
    }
}
$configFile = __DIR__ . '/../loja/database/config.json';

// Handle Save
$message = '';
if (isset($_GET['saved'])) {
    $message = '<div class="alert success">Configurações salvas com sucesso!</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = $_POST['config_json'];
    // Validate JSON
    $decoded = json_decode($rawData, true);
    if ($decoded === null) {
        $message = '<div class="alert error">Erro: JSON inválido.</div>';
    } else {
        // Save
        file_put_contents($configFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        header('Location: /admin?saved=1');
        exit;
    }
}

// Load Config
if (!file_exists($configFile)) {
    die("Config file not found.");
}
$configContent = file_get_contents($configFile);

// Remove BOM if present (common issue with Windows edited files)
$configContent = preg_replace('/^\xEF\xBB\xBF/', '', $configContent);

$configData = json_decode($configContent, true);
$jsonError = json_last_error();
$jsonErrorMsg = json_last_error_msg();

if ($jsonError !== JSON_ERROR_NONE) {
    echo "<div style='color:red; background:white; padding:20px; text-align:center; font-weight:bold; border-bottom:2px solid red;'>";
    echo "ERRO CRÍTICO NO ARQUIVO CONFIG.JSON: " . $jsonErrorMsg;
    echo "<br><small>Verifique se não há vírgulas sobrando ou erros de sintaxe no arquivo.</small>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Loja</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <base href="/">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/globals.css">
    <style>
        body {
            background: #f5f5f5;
            padding-bottom: 80px;
        }

        .admin-header {
            background: white;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 480px;
            margin: 0 auto;
        }

        .admin-title {
            font-size: 18px;
            font-weight: 700;
        }

        .container {
            max-width: 480px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .tab-btn {
            background: #e0e0e0;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: #555;
        }

        .tab-btn.active {
            background: #fe2c55;
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
            cursor: pointer;
        }

        .json-editor {
            width: 100%;
            height: 600px;
            font-family: monospace;
            font-size: 14px;
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 16px;
            border-radius: 8px;
            border: none;
        }

        .save-bar {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            background: white;
            padding: 16px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-save {
            background: #fe2c55;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 100px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-save:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 500;
        }

        .alert.success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .editor-mode-toggle {
            margin-bottom: 20px;
        }

        /* Media Uploader Styles */
        .media-uploader {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .upload-box {
            width: 120px;
            height: 120px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.2s;
        }

        .upload-box:hover {
            border-color: #fe2c55;
            color: #fe2c55;
            background: #fff0f2;
        }

        .upload-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .upload-box.has-image div:not(.remove-img),
        .upload-box.has-image span {
            display: none;
        }

        .remove-img {
            position: absolute;
            top: 4px;
            right: 4px;
            background: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 22px;
            font-weight: bold;
            color: red;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }

        .remove-img:hover {
            background: #fee2e2;
        }

        .remove-img:hover {
            background: #fee2e2;
        }

        /* Review Item Styles */
        .review-item {
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            background: #fafafa;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .review-user {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 13px;
        }

        .review-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #ddd;
            object-fit: cover;
        }

        .review-text {
            font-size: 13px;
            color: #555;
            margin-bottom: 8px;
        }

        .review-meta {
            font-size: 11px;
            color: #999;
        }

        .review-actions {
            display: flex;
            gap: 8px;
        }

        .btn-xs {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #ddd;
            background: white;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
</head>

<body>

    <div class="admin-header">
        <div class="admin-title">Painel Administrativo</div>
        <a href="/" class="btn btn-secondary" target="_blank"
            style="text-decoration: none; font-size: 13px; padding: 8px 16px; display:flex; align-items:center; gap:6px;">
            Ver Loja
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
        </a>
    </div>

    <div class="container">
        <?php echo $message; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="openTab('tab-store')">Loja</button>
            <button class="tab-btn" onclick="openTab('tab-products')">Produtos</button>
            <button class="tab-btn" onclick="openTab('tab-chat')">Chat</button>
        </div>

        <form method="POST" id="configForm">
            <!-- Hidden JSON for storage -->
            <textarea name="config_json" id="hidden_json_input"
                style="display:none;"><?php echo htmlspecialchars($configContent); ?></textarea>

            <!-- Store Settings Tab -->
            <div id="tab-store" class="tab-content active">

                <!-- Store Settings -->
                <h3 style="margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Informações da Loja
                </h3>
                <div style="display: flex; gap: 24px; align-items: flex-start; margin-bottom: 16px;">
                    <!-- Left Column: Logo -->
                    <div style="flex-shrink: 0;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Foto de Perfil</label>
                            <div class="media-uploader">
                                <label class="upload-box" id="preview_store_logo_box" style="position: relative;">
                                    <div
                                        style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                        <div style="font-size:24px; margin-bottom:4px;">+</div>
                                        <span>Adicionar</span>
                                    </div>
                                    <img id="preview_store_logo_img" style="display:none;">
                                    <div class="remove-img" id="remove_store_logo_btn" style="display:none;"
                                        onclick="removeStoreLogo(event)">&times;</div>
                                    <input type="file" style="display:none;" accept="image/*"
                                        onchange="handleImageUpload(this, 'store_logo')">
                                </label>
                            </div>
                            <input type="hidden" id="store_logo" oninput="updateJsonFromVisual()">
                        </div>
                    </div>

                    <!-- Right Column: Settings -->
                    <div style="flex-grow: 1;">
                        <div class="form-group">
                            <label>Nome da Loja</label>
                            <input type="text" class="form-control" id="store_name" oninput="updateJsonFromVisual()">
                        </div>

                        <div style="display:flex; gap:12px;">
                            <div class="form-group" style="flex:0.5;">
                                <label>Moeda</label>
                                <input type="text" class="form-control" id="store_currency"
                                    oninput="updateJsonFromVisual()">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Idioma</label>
                                <select class="form-control" id="store_locale" onchange="updateJsonFromVisual()">
                                    <option value="pt-BR">Português</option>
                                    <option value="en-US">Inglês</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:12px; align-items: end;">
                    <div class="form-group" style="flex:1;">
                        <label>Vendas Totais</label>
                        <input type="text" class="form-control" id="store_sales" oninput="maskStoreSales(this)">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Taxa de Resposta</label>
                        <input type="text" class="form-control" id="store_response" oninput="maskStorePercent(this)">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Entrega no Prazo</label>
                        <input type="text" class="form-control" id="store_delivery" oninput="maskStorePercent(this)">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label>Código Personalizado (Head)</label>
                    <textarea class="form-control" id="store_custom_head" oninput="updateJsonFromVisual()"
                        style="height: 120px; font-family: monospace; font-size: 13px;"
                        placeholder="<!-- Cole aqui seus scripts como Pixel do TikTok, Utmify, etc -->"></textarea>
                    <div style="font-size:12px; color:#666; margin-top:4px;">Este código será inserido dentro da tag
                        &lt;head&gt; de todas as páginas.</div>
                </div>



            </div>


            <!-- Products Tab -->
            <div id="tab-products" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h3 style="margin:0;">Gerenciar Produtos</h3>
                    <button type="button" class="btn-save" style="padding: 8px 16px; font-size: 14px;"
                        onclick="addProduct()">+
                        Novo Produto</button>
                </div>
                <div id="productList"></div>
            </div>

            <!-- Chat / FAQ Tab -->
            <div id="tab-chat" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h3 style="margin:0;">Gerenciar Chat</h3>
                    <button type="button" class="btn-save" style="padding: 8px 16px; font-size: 14px;"
                        onclick="addFaq()">+ Nova Pergunta</button>
                </div>

                <div class="form-group" style="margin-bottom:24px;">
                    <label>Mensagem Automática de Resposta</label>
                    <input type="text" class="form-control" id="store_chat_reply"
                        placeholder="Em breve um atendente entrará em contato." oninput="updateJsonFromVisual()">
                    <div style="font-size:12px; color:#666; margin-top:4px;">Resposta automática enviada apenas quando o
                        usuário digitar uma mensagem personalizada (fora das perguntas rápidas).</div>
                </div>

                <div style="text-align:start; font-size:12px; color:#999; margin-bottom: 16px; font-style:italic;">
                    <i class="fas fa-arrows-alt-v"></i> Arraste os cards para reordenar
                </div>

                <div id="faqList"></div>
            </div>

            <!-- Product Modal -->
            <div id="productModal"
                style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center; padding: 20px; box-sizing: border-box;">
                <div
                    style="background:white; border-radius:12px; width:100%; max-width:600px; max-height:90vh; display:flex; flex-direction:column; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                    <!-- Header -->
                    <div style="padding: 24px 24px 0 24px;">
                        <h3 style="margin-bottom:16px; margin-top:0;">Editar Produto</h3>
                        <div class="tabs" style="margin-bottom:0;">
                            <button type="button" class="tab-btn active" id="mtab-btn-details"
                                onclick="switchModalTab('details')">Produto</button>
                            <button type="button" class="tab-btn" id="mtab-btn-variations"
                                onclick="switchModalTab('variations')">Variações</button>
                            <button type="button" class="tab-btn" id="mtab-btn-reviews"
                                onclick="switchModalTab('reviews')">Avaliações</button>
                        </div>
                    </div>

                    <!-- Scrollable Body -->
                    <div style="overflow-y:auto; padding: 24px; flex:1;">
                        <input type="hidden" id="edit_index">

                        <!-- DETAILS TAB -->
                        <div id="modal-tab-details">
                            <div class="form-group">
                                <label>Imagens (Arraste para ordenar)</label>
                                <div class="media-uploader" id="product_images_container" style="margin-bottom:8px;">
                                    <!-- Populated by JS -->
                                    <label class="upload-box">
                                        <div
                                            style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                            <div style="font-size:24px; margin-bottom:4px;">+</div>
                                            <span>Adicionar</span>
                                        </div>
                                        <input type="file" style="display:none;" accept="image/*"
                                            onchange="handleImageUpload(this, 'edit_images_list', true)">
                                    </label>
                                </div>

                            </div>

                            <div class="form-group">
                                <label>Título</label>
                                <input type="text" class="form-control" id="edit_title">
                            </div>

                            <div style="display:flex; gap:12px;">
                                <div class="form-group" style="flex:1;">
                                    <label>Preço Original</label>
                                    <input type="text" class="form-control" id="edit_price" oninput="maskMoney(this)">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label>Preço de Comparação</label>
                                    <input type="text" class="form-control" id="edit_original_price"
                                        oninput="maskMoney(this)">
                                </div>
                            </div>

                            <div style="display:flex; gap:12px;">
                                <div class="form-group" style="flex:1;">
                                    <label>Frete Original</label>
                                    <input type="text" class="form-control" id="edit_shipping_fee"
                                        oninput="maskMoney(this)">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label>Timer</label>
                                    <div style="display:flex; gap:4px; align-items: center;">
                                        <input type="text" class="form-control" id="edit_timer_h" placeholder="00"
                                            style="text-align:center;" oninput="maskInt(this)">
                                        <span>:</span>
                                        <input type="text" class="form-control" id="edit_timer_m" placeholder="00"
                                            style="text-align:center;" oninput="maskInt(this)">
                                        <span>:</span>
                                        <input type="text" class="form-control" id="edit_timer_s" placeholder="00"
                                            style="text-align:center;" oninput="maskInt(this)">
                                    </div>
                                </div>
                            </div>



                            <div class="form-group">
                                <label>Link de Checkout</label>
                                <input type="text" class="form-control" id="edit_checkout_url">
                            </div>



                            <div style="display:flex; gap:12px;">
                                <div class="form-group" style="flex:1;">
                                    <label>Nota (0-5)</label>
                                    <input type="text" class="form-control" id="edit_rating_value"
                                        oninput="maskRating(this)" onblur="formatRating(this)">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label>Qtd Avaliações</label>
                                    <input type="text" class="form-control" id="edit_rating_count"
                                        oninput="maskInt(this)">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label>Qtd Vendidos</label>
                                    <input type="text" class="form-control" id="edit_sold_count"
                                        oninput="maskInt(this)">
                                </div>
                            </div>



                            <div class="form-group">
                                <label>Descrição Completa (HTML permitida)</label>
                                <textarea class="form-control" id="edit_body"></textarea>
                            </div>
                        </div> <!-- End modal-tab-details -->

                        <!-- VARIATIONS TAB -->
                        <div id="modal-tab-variations" style="display:none;">
                            <div class="form-group"
                                style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                                <input type="checkbox" id="edit_has_variations" style="width:20px; height:20px;">
                                <label for="edit_has_variations" style="margin:0; font-weight:600;">Produto possui
                                    variações?</label>
                            </div>

                            <div class="form-group">
                                <label>Nome do Tipo de Variação (ex: Cor, Tamanho)</label>
                                <input type="text" class="form-control" id="edit_variation_type" placeholder="Ex: Cor">
                            </div>

                            <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">

                            <label style="font-weight:600; margin-bottom:12px; display:block;">Lista de
                                Variações</label>

                            <div
                                style="text-align:start; font-size:12px; color:#999; margin-bottom: 16px; font-style:italic;">
                                <i class="fas fa-arrows-alt-v"></i> Arraste os itens para reordenar
                            </div>

                            <div id="variations_container">
                                <!-- Populated by JS -->
                            </div>

                            <button type="button" class="btn btn-secondary" onclick="addVariation()"
                                style="width:100%; height: 36px; margin-top:12px; border:1px dashed #ccc;">
                                + Adicionar Variação
                            </button>
                        </div>

                        <!-- REVIEWS TAB -->
                        <div id="modal-tab-reviews" style="display:none;">

                            <!-- List View -->
                            <div id="reviews-list-view">
                                <button type="button" class="btn-save" style="width:100%; margin-bottom:16px;"
                                    onclick="openReviewForm(-1)">+ Nova Avaliação</button>
                                <div id="reviews-container"></div>
                                <div
                                    style="text-align:center; font-size:12px; color:#999; margin-top:12px; font-style:italic;">
                                    <i class="fas fa-arrows-alt-v"></i> Arraste os itens para reordenar
                                </div>
                            </div>

                            <!-- Edit/Create Form View -->
                            <div id="reviews-form-view" style="display:none;">
                                <h4 style="margin-top:0; margin-bottom:12px;">Editar Avaliação</h4>

                                <div class="form-group">
                                    <label>Avatar do Usuário</label>
                                    <div class="media-uploader">
                                        <label class="upload-box" id="rev_avatar_box" style="position: relative;">
                                            <div
                                                style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                                <div style="font-size:24px; margin-bottom:4px;">+</div>
                                                <span>Adicionar</span>
                                            </div>
                                            <img id="rev_avatar_img" style="display:none;">
                                            <div class="remove-img" id="rev_avatar_remove" style="display:none;"
                                                onclick="removeReviewAvatar(event)">&times;</div>
                                            <input type="file" style="display:none;" accept="image/*"
                                                onchange="handleImageUpload(this, 'rev_avatar', false, false, true)">
                                        </label>
                                        <input type="hidden" id="rev_avatar">
                                    </div>
                                </div>

                                <div style="display:flex; gap:12px;">
                                    <div class="form-group" style="flex:1;">
                                        <label>Nome do Usuário</label>
                                        <input type="text" class="form-control" id="rev_name">
                                    </div>
                                    <div class="form-group" style="flex:1;">
                                        <label>Data</label>
                                        <input type="text" class="form-control" id="rev_date"
                                            placeholder="01/01/2025 10:00" oninput="maskDateTime(this)" maxlength="16">
                                    </div>
                                </div>

                                <div style="display:flex; gap:12px;">
                                    <div class="form-group" style="flex:1;">
                                        <label>Nota (1-5)</label>
                                        <input type="number" class="form-control" id="rev_rating" min="1" max="5"
                                            step="0.1">
                                    </div>
                                    <div class="form-group" style="flex:1;">
                                        <label>Variação</label>
                                        <input type="text" class="form-control" id="rev_variation">
                                    </div>
                                </div>

                                <!-- Avatar input removed from here -->

                                <div class="form-group">
                                    <label>Texto</label>
                                    <textarea class="form-control" id="rev_text" style="height:80px;"></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Imagens da Avaliação</label>
                                    <div class="media-uploader" id="review_images_container">
                                        <!-- Populated via JS -->
                                        <label class="upload-box">
                                            <div
                                                style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                                <div style="font-size:24px; margin-bottom:4px;">+</div>
                                                <span>Adicionar</span>
                                            </div>
                                            <input type="file" style="display:none;" accept="image/*"
                                                onchange="handleImageUpload(this, 'review_images_uploader', false, true)">
                                        </label>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div> <!-- End Scrollable Body -->

                    <!-- Footer -->
                    <div id="mainModalFooter"
                        style="display:flex; justify-content:flex-end; gap:12px; padding:16px; border-top:1px solid #eee; background:white; border-radius: 0 0 12px 12px;">
                        <button type="button" class="btn btn-secondary"
                            style="text-decoration: none; font-size: 13px; padding: 8px 16px;"
                            onclick="closeModal()">Cancelar</button>
                        <button type="button" class="btn-save" onclick="saveProduct()">Confirmar</button>
                    </div>

                    <!-- Review Footer (Initially Hidden) -->
                    <div id="reviewButtonsFooter"
                        style="display:none; justify-content:space-between; gap:12px; padding:16px; border-top:1px solid #eee; background:white; border-radius: 0 0 12px 12px;">
                        <button type="button" class="btn btn-secondary" style="flex:1;"
                            onclick="cancelReviewForm()">Cancelar</button>
                        <button type="button" class="btn-save" style="flex:1;" onclick="saveReview()">Salvar
                            Avaliação</button>
                    </div>
                </div>
            </div>

            <div class="save-bar">
                <button type="button" class="btn btn-secondary"
                    style="text-decoration: none; font-size: 13px; padding: 8px 16px;" onclick="resetForm()">Recarregar
                    original</button>
                <button type="submit" class="btn-save">Salvar Alterações</button>
            </div>
        </form>
    </div>

    <script>
        let configData = <?php echo json_encode($configData) ?: '{}'; ?>;
        if (!configData || !configData.store) {
            console.error("Config Data is invalid or empty", configData);
            // Fallback to prevent crash
            configData = {
                store: { name: '', currency_symbol: 'R$', locale: 'pt-BR' },
                products: [],
                faq: []
            };
            alert("Erro: O arquivo config.json parece estar inválido ou vazio. Verifique o arquivo.");
        }
        let productImages = []; // Global state for product images optimization
        let currentProductReviews = []; // Global state for reviews
        let editingReviewIndex = -1; // -1 for new
        let reviewImages = []; // Temp state for review images being edited

        // Init Visual Editor
        document.getElementById('store_name').value = configData.store.name;
        document.getElementById('store_currency').value = configData.store.currency_symbol;
        document.getElementById('store_locale').value = configData.store.locale || 'pt-BR';
        document.getElementById('store_logo').value = configData.store.logo || '';
        if (configData.store.logo) {
            document.getElementById('preview_store_logo_img').src = configData.store.logo;
            document.getElementById('preview_store_logo_img').style.display = 'block';
            document.getElementById('remove_store_logo_btn').style.display = 'block';
        }

        document.getElementById('store_sales').value = parseInt(configData.store.sales_count || 0).toLocaleString('pt-BR');
        document.getElementById('store_response').value = (configData.store.response_rate || 0) + '%';
        document.getElementById('store_response').value = (configData.store.response_rate || 0) + '%';
        document.getElementById('store_delivery').value = (configData.store.on_time_delivery_rate || 0) + '%';
        document.getElementById('store_chat_reply').value = configData.store.chat_auto_reply || 'Em breve um atendente entrará em contato.';

        document.getElementById('store_delivery').value = (configData.store.on_time_delivery_rate || 0) + '%';
        document.getElementById('store_custom_head').value = configData.store.custom_head_code || '';

        renderProducts();
        renderFaq();

        function renderFaq() {
            const list = document.getElementById('faqList');
            list.innerHTML = '';

            if (!configData.faq || configData.faq.length === 0) {
                list.innerHTML = '<p style="color:#666;">Nenhuma pergunta cadastrada.</p>';
                return;
            }

            configData.faq.forEach((f, idx) => {
                const item = document.createElement('div');
                item.className = 'faq-item'; // Class for Sortable
                item.setAttribute('data-id', f.id); // For tracking
                item.style.cssText = 'background: white; border: 1px solid #eee; padding: 16px; margin-bottom: 12px; border-radius: 8px; position:relative; cursor: grab;';

                item.innerHTML = `
                    <div style="margin-bottom:12px; text-align:center;">
                         <i class="fas fa-grip-lines" style="color:#ddd;"></i>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-weight:600; font-size:12px; color:#555; margin-bottom:4px;">Pergunta</label>
                        <input type="text" class="form-control" value="${f.question || ''}" placeholder="Pergunta visível" oninput="updateFaq(${idx}, 'question', this.value)">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-weight:600; font-size:12px; color:#555; margin-bottom:4px;">Resposta</label>
                        <textarea class="form-control" style="height:80px;" placeholder="Resposta" oninput="updateFaq(${idx}, 'answer', this.value)">${f.answer || ''}</textarea>
                    </div>
                     <div style="display:flex; justify-content:flex-end; align-items:center;">
                         <button type="button" class="btn-xs" style="color:red; border:1px solid red; background:transparent; font-size:12px;" onclick="deleteFaq(${idx})">Excluir Pergunta</button>
                    </div>
                `;
                list.appendChild(item);
            });
        }

        function addFaq() {
            if (!configData.faq) configData.faq = [];
            configData.faq.push({
                id: 'new_faq_' + Date.now(),
                question: '',
                answer: ''
            });
            renderFaq();
            updateJsonFromVisual();
        }

        function updateFaq(idx, field, val) {
            configData.faq[idx][field] = val;
            updateJsonFromVisual();
        }

        function deleteFaq(idx) {
            if (confirm('Excluir esta pergunta?')) {
                configData.faq.splice(idx, 1);
                renderFaq();
                updateJsonFromVisual();
            }
        }

        function renderProducts() {
            const list = document.getElementById('productList');
            list.innerHTML = '';

            if (!configData.products || configData.products.length === 0) {
                list.innerHTML = '<p style="color:#666;">Nenhum produto cadastrado.</p>';
                return;
            }

            configData.products.forEach((p, index) => {
                const item = document.createElement('div');
                item.style.cssText = 'background: white; border: 1px solid #eee; padding: 12px; margin-bottom: 8px; border-radius: 8px; display: flex; align-items: center; gap: 12px;';

                const imgUrl = (p.images && p.images.length > 0) ? p.images[0] : '';

                // Safety check for price
                const currency = configData.store.currency_symbol || 'R$';
                const priceVal = (p.current_price / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                const priceFormatted = `${currency} ${priceVal}`;

                item.innerHTML = `
                    <div style="width:50px; height:50px; background:#f5f5f5; border-radius:4px; overflow:hidden; flex-shrink:0;">
                        ${imgUrl ? `<img src="${imgUrl}" style="width:100%; height:100%; object-fit:cover;">` : ''}
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:14px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">${p.title || 'Sem Título'}</div>
                        <div style="font-size:12px; color:#666;">${priceFormatted}</div>
                    </div>
                    <button type="button" style="background:#fff; border:1px solid #ddd; padding:6px 16px; border-radius:20px; cursor:pointer; font-size:13px; color:#333; font-weight:500;" onclick="openProductModal(${index})">Editar</button>
                    <button type="button" style="background:none; border:none; color:#dc2626; padding:8px; cursor:pointer;" onclick="deleteProduct(${index})">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                             <polyline points="3 6 5 6 21 6"></polyline>
                             <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                `;
                list.appendChild(item);
            });
        }

        function openProductModal(index) {
            let p;
            if (index === -1) {
                p = {
                    title: '',
                    current_price: 0,
                    original_price: 0,
                    checkout_url: '',
                    rating_value: 0,
                    rating_count: 0,
                    sold_count: 0,
                    description_body: '',
                    shipping: { shipping_fee_original: 0 },
                    offer: { timer_initial_seconds: 0 },
                    images: [],
                    reviews: [],
                    has_variations: false,
                    variation_type: '',
                    variations: []
                };
            } else {
                p = configData.products[index];
            }

            document.getElementById('edit_index').value = index;
            document.getElementById('edit_title').value = p.title || '';
            document.getElementById('edit_price').value = formatMoney(p.current_price || 0);
            document.getElementById('edit_original_price').value = formatMoney(p.original_price || 0);
            document.getElementById('edit_checkout_url').value = p.checkout_url || '';
            document.getElementById('edit_rating_value').value = (p.rating_value || 0).toFixed(1);
            document.getElementById('edit_rating_count').value = formatInt(p.rating_count || 0);
            document.getElementById('edit_sold_count').value = formatInt(p.sold_count || 0);
            document.getElementById('edit_body').value = p.description_body || '';

            document.getElementById('edit_shipping_fee').value = formatMoney(p.shipping?.shipping_fee_original || 0);

            // Timer
            const totalSec = p.offer?.timer_initial_seconds || 0;
            const h = Math.floor(totalSec / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            document.getElementById('edit_timer_h').value = h > 0 ? h : '';
            document.getElementById('edit_timer_m').value = m > 0 ? m : '';
            document.getElementById('edit_timer_s').value = s > 0 ? s : '';

            // Images
            productImages = (p.images && p.images.length > 0) ? [...p.images] : [];
            renderProductImages();

            // Reviews
            currentProductReviews = (p.reviews && p.reviews.length > 0) ? JSON.parse(JSON.stringify(p.reviews)) : [];
            editingReviewIndex = -1;

            // Variation Fields
            document.getElementById('edit_has_variations').checked = !!p.has_variations;
            document.getElementById('edit_variation_type').value = p.variation_type || '';
            currentProductVariations = (p.variations && p.variations.length > 0) ? JSON.parse(JSON.stringify(p.variations)) : [];
            renderVariationsListV2();

            // Default to Details Tab
            switchModalTab('details');

            document.getElementById('productModal').style.display = 'flex';
        }

        function switchModalTab(tab) {
            document.getElementById('modal-tab-details').style.display = (tab === 'details') ? 'block' : 'none';
            document.getElementById('modal-tab-reviews').style.display = (tab === 'reviews') ? 'block' : 'none';
            document.getElementById('modal-tab-variations').style.display = (tab === 'variations') ? 'block' : 'none';

            document.getElementById('mtab-btn-details').classList.toggle('active', tab === 'details');
            document.getElementById('mtab-btn-reviews').classList.toggle('active', tab === 'reviews');
            document.getElementById('mtab-btn-variations').classList.toggle('active', tab === 'variations');

            if (tab === 'reviews') {
                cancelReviewForm(); // Reset to list view
                renderReviewList();
            }
        }

        function renderReviewList() {
            const container = document.getElementById('reviews-container');
            container.innerHTML = '';

            if (currentProductReviews.length === 0) {
                container.innerHTML = '<div style="color:#999; text-align:center; padding:20px;">Nenhuma avaliação cadastrada.</div>';
                return;
            }

            currentProductReviews.forEach((rev, idx) => {
                // Ensure temp ID for sorting
                if (!rev._tempId) rev._tempId = 'rev_' + Date.now() + '_' + idx;

                const el = document.createElement('div');
                el.className = 'review-item';
                el.setAttribute('data-temp-id', rev._tempId);
                el.style.cursor = 'move'; // Indicate draggable
                el.innerHTML = `
                    <div class="review-header">
                        <div class="review-user">
                            ${rev.user_avatar ? `<img src="${rev.user_avatar}" class="review-avatar">` : '<div class="review-avatar"></div>'}
                            <span>${rev.user_name || 'Anônimo'}</span>
                            <span style="color:#fe2c55; margin-left:8px;">★ ${rev.rating}</span>
                        </div>
                        <div class="review-actions">
                            <button type="button" class="btn-xs" onclick="openReviewForm(${idx})">Editar</button>
                            <button type="button" class="btn-xs" style="color:red;" onclick="deleteReview(${idx})">Excluir</button>
                        </div>
                    </div>
                    <div class="review-text">${rev.text || ''}</div>
                    <div class="review-meta">${rev.date} • ${rev.variation || 'Padrão'}</div>
                `;
                container.appendChild(el);
            });
        }

        function openReviewForm(index) {
            editingReviewIndex = index;
            document.getElementById('reviews-list-view').style.display = 'none';
            document.getElementById('reviews-form-view').style.display = 'block';

            // Toggle Footers
            // Toggle Footers
            document.getElementById('mainModalFooter').style.display = 'none';
            document.getElementById('reviewButtonsFooter').style.display = 'flex';
            // Review footer is inside reviews-form-view, so it shows up.

            if (index === -1) {
                // New
                document.getElementById('rev_name').value = '';
                document.getElementById('rev_avatar').value = '';
                document.getElementById('rev_avatar_img').src = '';
                document.getElementById('rev_avatar_img').style.display = 'none';
                document.getElementById('rev_avatar_box').classList.remove('has-image');
                document.getElementById('rev_avatar_remove').style.display = 'none';

                document.getElementById('rev_rating').value = '5';
                document.getElementById('rev_rating').value = '5';

                const now = new Date();
                const d = String(now.getDate()).padStart(2, '0');
                const m = String(now.getMonth() + 1).padStart(2, '0');
                const y = now.getFullYear();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');

                document.getElementById('rev_date').value = `${d}/${m}/${y} ${hh}:${mm}`;
                document.getElementById('rev_variation').value = 'Padrão';
                document.getElementById('rev_text').value = '';
                reviewImages = [];
            } else {
                const r = currentProductReviews[index];
                document.getElementById('rev_name').value = r.user_name || '';
                document.getElementById('rev_avatar').value = r.user_avatar || '';
                if (r.user_avatar) {
                    document.getElementById('rev_avatar_img').src = r.user_avatar;
                    document.getElementById('rev_avatar_img').style.display = 'block';
                    document.getElementById('rev_avatar_box').classList.add('has-image');
                    document.getElementById('rev_avatar_remove').style.display = 'block';
                } else {
                    document.getElementById('rev_avatar_img').style.display = 'none';
                    document.getElementById('rev_avatar_box').classList.remove('has-image');
                    document.getElementById('rev_avatar_remove').style.display = 'none';
                }

                document.getElementById('rev_rating').value = r.rating || 5;
                document.getElementById('rev_date').value = r.date || '';
                document.getElementById('rev_variation').value = r.variation || '';
                document.getElementById('rev_text').value = r.text || '';
                reviewImages = r.images ? [...r.images] : [];
            }
            renderReviewImages();
        }

        function cancelReviewForm() {
            document.getElementById('reviews-list-view').style.display = 'block';
            document.getElementById('reviews-form-view').style.display = 'none';
            document.getElementById('mainModalFooter').style.display = 'flex';
            document.getElementById('reviewButtonsFooter').style.display = 'none';
            editingReviewIndex = -1;
        }

        function saveReview() {
            const r = {
                user_name: document.getElementById('rev_name').value,
                user_avatar: document.getElementById('rev_avatar').value,
                rating: parseFloat(document.getElementById('rev_rating').value) || 5,
                date: document.getElementById('rev_date').value,
                variation: document.getElementById('rev_variation').value,
                text: document.getElementById('rev_text').value,
                images: [...reviewImages]
            };

            if (editingReviewIndex === -1) {
                currentProductReviews.push(r);
            } else {
                currentProductReviews[editingReviewIndex] = r;
            }
            cancelReviewForm();
            renderReviewList();
        }

        function deleteReview(index) {
            if (confirm('Excluir avaliação?')) {
                currentProductReviews.splice(index, 1);
                renderReviewList();
            }
        }

        function renderReviewImages() {
            const container = document.getElementById('review_images_container');
            const boxes = container.querySelectorAll('.upload-box.has-image');
            boxes.forEach(b => b.remove());

            const uploadBtn = container.querySelector('label.upload-box');

            reviewImages.forEach((url, idx) => {
                const box = document.createElement('div');
                box.className = 'upload-box has-image';
                box.innerHTML = `
                    <img src="${url}">
                    <div class="remove-img" onclick="removeReviewImage(${idx}, event)">&times;</div>
                `;
                container.insertBefore(box, uploadBtn);
            });
        }

        function removeReviewAvatar(event) {
            event.preventDefault();
            event.stopPropagation();

            const imgPath = document.getElementById('rev_avatar').value;
            if (imgPath) deleteFile(imgPath);

            document.getElementById('rev_avatar').value = '';
            document.getElementById('rev_avatar_img').src = '';
            document.getElementById('rev_avatar_img').style.display = 'none';
            document.getElementById('rev_avatar_box').classList.remove('has-image');
            document.getElementById('rev_avatar_remove').style.display = 'none';
        }

        function removeReviewImage(index, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (reviewImages[index]) {
                deleteFile(reviewImages[index]);
            }
            reviewImages.splice(index, 1);
            renderReviewImages();
        }

        function renderVariationsListV2() {
            const container = document.getElementById('variations_container');
            container.innerHTML = '';

            if (currentProductVariations.length === 0) {
                container.innerHTML = '<div style="color:#999; text-align:center; padding:20px;">Nenhuma variação cadastrada.</div>';
                return;
            }

            currentProductVariations.forEach((v, idx) => {
                const el = document.createElement('div');
                el.className = 'variation-row';
                el.dataset.id = v.id || `new-${idx}`; // Use existing ID or generate a temp one
                el.dataset.image = v.image || ''; // Store image URL on the row

                const currency = configData.store.currency_symbol || 'R$';

                el.innerHTML = `
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px; padding:10px; border:1px solid #eee; border-radius:8px;">
                        <div style="width:60px; height:60px; background:#f5f5f5; border-radius:6px; overflow:hidden; flex-shrink:0; position:relative;">
                            ${v.image ? `<img src="${v.image}" style="width:100%; height:100%; object-fit:cover;">` : ''}
                            
                            <label style="position:absolute; top:0; left:0; width:100%; height:100%; cursor:pointer; display:flex; align-items:center; justify-content:center; background:${v.image ? 'rgba(0,0,0,0)' : '#f5f5f5'}; opacity:${v.image ? '0' : '1'}; transition:opacity 0.2s; z-index:1;" onmouseover="this.style.opacity=1; this.style.background='rgba(0,0,0,0.3)'" onmouseout="this.style.opacity=${v.image ? '0' : '1'}; this.style.background='${v.image ? 'rgba(0,0,0,0)' : '#f5f5f5'}'">
                                <input type="file" style="display:none;" accept="image/*" onchange="handleVariationImageUpload(this, '${el.dataset.id}')">
                                <i class="fas fa-camera" style="color:${v.image ? 'white' : '#999'}; font-size:18px;"></i>
                            </label>

                            ${v.image ? `<button type="button" style="position:absolute; top:2px; right:2px; background:rgba(255,0,0,0.8); color:white; border:none; width:18px; height:18px; border-radius:50%; text-align:center; line-height:16px; cursor:pointer; font-size:14px; z-index:10; font-weight:bold; padding:0;" onclick="removeVariationImage('${el.dataset.id}')">&times;</button>` : ''}
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex; gap:8px; margin-bottom:6px;">
                                <input type="text" class="form-control var-name" placeholder="Nome" value="${v.name || ''}" style="flex:1; padding: 6px; font-size: 13px;">
                                <input type="text" class="form-control var-price" placeholder="Preço" value="${formatMoney(v.price || 0)}" oninput="maskMoney(this)" style="width: 80px; padding: 6px; font-size: 13px;">
                            </div>
                            <input type="text" class="form-control var-url" placeholder="URL Checkout (Link individual)" value="${v.checkout_url || ''}" style="border: 1px solid #ccc; padding: 6px; font-size: 13px;">
                            <input type="hidden" class="var-img-input" value="${v.image || ''}">
                        </div>
                        <button type="button" class="btn-xs" style="color:red;" onclick="deleteVariation(${idx})">Excluir</button>
                    </div>
                `;
                container.appendChild(el);
            });
        }

        function addVariation() {
            const newId = Date.now(); // Unique ID for new variation
            const defaultUrl = document.getElementById('edit_checkout_url').value || '';
            currentProductVariations.push({ id: newId, name: '', price: 0, image: '', checkout_url: defaultUrl });
            renderVariationsListV2();
        }

        function deleteVariation(index) {
            if (confirm('Excluir esta variação?')) {
                // If the variation had an image, delete it from server
                const variation = currentProductVariations[index];
                if (variation.image) {
                    deleteFile(variation.image);
                }
                currentProductVariations.splice(index, 1);
                renderVariationsListV2();
            }
        }

        function handleVariationImageUpload(input, variationId) {
            if (!input.files || !input.files[0]) return;

            const formData = new FormData();
            formData.append('file', input.files[0]);

            fetch('/admin?action=upload', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const variationRow = document.querySelector(`.variation-row[data-id="${variationId}"]`);
                        if (variationRow) {
                            const imgInput = variationRow.querySelector('.var-img-input');
                            const oldImage = imgInput.value;
                            if (oldImage && oldImage !== data.path) {
                                deleteFile(oldImage); // Delete old image if replaced
                            }
                            imgInput.value = data.path;
                            variationRow.dataset.image = data.path; // Update dataset for rendering

                            // Update currentProductVariations state
                            const variationIndex = currentProductVariations.findIndex(v => v.id == variationId);
                            if (variationIndex !== -1) {
                                currentProductVariations[variationIndex].image = data.path;
                            }
                            renderVariationsListV2(); // Re-render to show new image
                        }
                    } else {
                        showToast('Erro: ' + data.error, 'error');
                    }
                    input.value = '';
                })
                .catch(e => {
                    showToast('Erro no envio', 'error');
                    console.error(e);
                    input.value = '';
                });
        }

        function removeVariationImage(variationId) {
            const variationRow = document.querySelector(`.variation-row[data-id="${variationId}"]`);
            if (variationRow) {
                const imgInput = variationRow.querySelector('.var-img-input');
                const imagePath = imgInput.value;
                if (imagePath) {
                    deleteFile(imagePath);
                }
                imgInput.value = '';
                variationRow.dataset.image = '';

                // Update currentProductVariations state
                const variationIndex = currentProductVariations.findIndex(v => v.id == variationId);
                if (variationIndex !== -1) {
                    currentProductVariations[variationIndex].image = '';
                }
                renderVariationsListV2(); // Re-render to remove image
            }
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        function saveProduct() {
            const index = parseInt(document.getElementById('edit_index').value);

            let p;
            if (index === -1) {
                // New Product
                p = { id: Date.now() };
            } else {
                // Existing Product - modification in place (by reference to object in array? No, need to be careful)
                // actually we can just modify configData.products[index] directly or clone and replace.
                // Let's modify the reference to be safe with reactivity if any, or just updates.
                p = configData.products[index];
            }

            // Update Root Fields
            p.title = document.getElementById('edit_title').value;
            p.current_price = parseMoney(document.getElementById('edit_price').value);
            p.original_price = parseMoney(document.getElementById('edit_original_price').value);
            p.checkout_url = document.getElementById('edit_checkout_url').value;
            p.rating_value = parseFloat(document.getElementById('edit_rating_value').value) || 0;
            p.rating_count = parseIntFmt(document.getElementById('edit_rating_count').value);
            p.sold_count = parseIntFmt(document.getElementById('edit_sold_count').value);
            p.description_body = document.getElementById('edit_body').value;

            // Variations
            p.has_variations = document.getElementById('edit_has_variations').checked;
            p.variation_type = document.getElementById('edit_variation_type').value;

            // Gather variations from DOM inputs to ensure latest values
            const varRows = document.querySelectorAll('.variation-row');
            const newVariations = [];

            varRows.forEach(row => {
                const id = row.getAttribute('data-id');
                // find in current or new
                let existing = currentProductVariations.find(v => v.id == id);
                if (!existing) existing = { id: id }; // should exist though

                const nameInput = row.querySelector('.var-name');
                const priceInput = row.querySelector('.var-price');
                const urlInput = row.querySelector('.var-url');
                const imgInput = row.querySelector('.var-img-input'); // hidden input storing result

                if (nameInput && priceInput) {
                    existing.name = nameInput.value;
                    existing.price = parseMoney(priceInput.value);
                    existing.checkout_url = urlInput ? urlInput.value : '';
                    if (imgInput) existing.image = imgInput.value; // Use the hidden input value
                    newVariations.push(existing);
                }
            });
            p.variations = newVariations;

            // Merge Offer
            p.offer = {
                ...(p.offer || {}),
                // is_flash_sale: false, // Don't enforce false if not editing it? Or is it hardcoded logic? 
                // The form doesn't seem to have flash sale inputs, forcing false might overwrite true?
                // Previous code forced: is_flash_sale: false. 
                // If user wants to preserve it, we should not overwrite unless we know what we are doing.
                // But the previous code explicitly set it. I will keep explicit set BUT merge so other fields stay.
                is_flash_sale: false,
                timer_initial_seconds: (parseInt(document.getElementById('edit_timer_h').value) || 0) * 3600 + (parseInt(document.getElementById('edit_timer_m').value) || 0) * 60 + (parseInt(document.getElementById('edit_timer_s').value) || 0)
            };

            // Merge Shipping
            p.shipping = {
                ...(p.shipping || {}),
                free_shipping: false, // Same logic as offer
                shipping_fee_original: parseMoney(document.getElementById('edit_shipping_fee').value)
            };

            p.reviews = [...currentProductReviews];
            p.images = [...productImages];

            if (index === -1) {
                configData.products.push(p);
            }
            // If index !== -1, p is already the reference to configData.products[index], so mutations above updated it.
            // But to be explicit and safe if p was a copy:
            // Actually configData.products[index] = p is redundant if p is reference, but fine.
            // Wait, typical JS: var p = obj; p.x = 1 updates obj. 
            // So if p = configData.products[index], it is updated.

            // However, if we wanted to be immutable logic style we would clone. 
            // The previous code was replacing the entry.
            // Let's just ensure we didn't break anything.
            if (index !== -1) {
                configData.products[index] = p;
            }

            updateJsonFromVisual();
            renderProducts();
            closeModal();
        }

        function addProduct() {
            openProductModal(-1);
        }

        function deleteProduct(index) {
            if (confirm("Tem certeza que deseja excluir este produto?")) {
                configData.products.splice(index, 1);
                updateJsonFromVisual();
                renderProducts();
            }
        }

        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function updateJsonFromVisual() {
            try {
                // Update Store Settings
                configData.store.name = document.getElementById('store_name').value;
                configData.store.currency_symbol = document.getElementById('store_currency').value;
                configData.store.locale = document.getElementById('store_locale').value;
                configData.store.logo = document.getElementById('store_logo').value;
                configData.store.sales_count = parseInt(document.getElementById('store_sales').value.replace(/\D/g, '')) || 0;
                configData.store.response_rate = parseInt(document.getElementById('store_response').value.replace(/\D/g, '')) || 0;
                configData.store.on_time_delivery_rate = parseInt(document.getElementById('store_delivery').value.replace(/\D/g, '')) || 0;
                configData.store.chat_auto_reply = document.getElementById('store_chat_reply').value;
                configData.store.custom_head_code = document.getElementById('store_custom_head').value;

                configData.store.on_time_delivery_rate = parseInt(document.getElementById('store_delivery').value.replace(/\D/g, '')) || 0;

                // Update Hidden Input (using ID instead of class)
                document.getElementById('hidden_json_input').value = JSON.stringify(configData, null, 4);
            } catch (e) {
                console.error(e);
            }
        }

        function resetForm() {
            if (confirm('Tem certeza? Todas as alterações não salvas serão perdidas.')) {
                window.location.reload();
            }
        }

        function handleImageUpload(input, targetId, isProduct = false, isReview = false, isReviewAvatar = false) {
            if (!input.files || !input.files[0]) return;

            const formData = new FormData();
            formData.append('file', input.files[0]);

            fetch('/admin?action=upload', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (isReviewAvatar) {
                            const target = document.getElementById(targetId);
                            target.value = data.path;
                            document.getElementById('rev_avatar_img').src = data.path;
                            document.getElementById('rev_avatar_img').style.display = 'block';
                            document.getElementById('rev_avatar_box').classList.add('has-image');
                            document.getElementById('rev_avatar_remove').style.display = 'block';
                        } else if (isProduct) {
                            // Product Images: Append to array
                            productImages.push(data.path);
                            renderProductImages();
                        } else if (isReview) {
                            // Review Images
                            reviewImages.push(data.path);
                            renderReviewImages();
                        } else {
                            // Single Image (Logo): Replace value
                            const currentVal = target.value;

                            // If replacing an existing logo, delete the old file
                            if (targetId === 'store_logo' && currentVal && currentVal !== data.path) {
                                deleteFile(currentVal);
                            }

                            target.value = data.path;

                            // Update visual preview
                            if (targetId === 'store_logo') {
                                document.getElementById('preview_store_logo_img').src = data.path;
                                document.getElementById('preview_store_logo_img').style.display = 'block';
                                document.getElementById('remove_store_logo_btn').style.display = 'block';
                            }

                            // Sync
                            if (targetId.startsWith('store_')) updateJsonFromVisual();
                        }
                    } else {
                        showToast('Erro: ' + data.error, 'error');
                    }
                    input.value = '';
                })
                .catch(e => {
                    showToast('Erro no envio', 'error');
                    console.error(e);
                    input.value = '';
                });
        }

        function deleteFile(path) {
            if (!path) return;
            fetch('/admin?action=delete_file', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ path: path })
            }).then(r => r.json()).then(d => {
                if (!d.success) console.error('Erro ao deletar arquivo:', d.error);
            }).catch(console.error);
        }

        function removeStoreLogo(event) {
            event.preventDefault(); // Prevent label click (file dialog)
            event.stopPropagation();

            const currentPath = document.getElementById('store_logo').value;
            deleteFile(currentPath);

            document.getElementById('store_logo').value = '';
            document.getElementById('preview_store_logo_img').src = '';
            document.getElementById('preview_store_logo_img').style.display = 'none';
            document.getElementById('remove_store_logo_btn').style.display = 'none';

            updateJsonFromVisual();
        }

        function renderProductImages() {
            const container = document.getElementById('product_images_container');
            const urls = productImages;

            // Keep the upload button (last element)
            const uploadBtn = container.querySelector('label.upload-box') || container.lastElementChild;

            // Clear current images (but not the upload button)
            // It's safer to clear everything except the upload button
            const boxes = container.querySelectorAll('.upload-box.has-image');
            boxes.forEach(box => box.remove());

            // If upload button is missing (edge case), recreate simple one (unlikely if logic holds)
            // But actually, we just need to insertBefore the uploadBtn.

            // Insert images before upload btn
            urls.forEach((url, index) => {
                const box = document.createElement('div');
                box.className = 'upload-box has-image';
                box.dataset.url = url; // Store URL for sorting
                box.innerHTML = `
                    <img src="${url}">
                    <div class="remove-img" onclick="removeProductImage(${index})">&times;</div>
                `;
                container.insertBefore(box, uploadBtn);
            });
        }

        // Initialize Sortable
        document.addEventListener('DOMContentLoaded', () => {
            // Sortable for Images
            new Sortable(document.getElementById('product_images_container'), {
                animation: 150,
                handle: '.upload-box',
                filter: 'label.upload-box',
                onEnd: function () {
                    const boxes = document.querySelectorAll('#product_images_container .upload-box.has-image img');
                    productImages = Array.from(boxes).map(img => img.getAttribute('src'));
                }
            });

            // Sortable for Review Images
            new Sortable(document.getElementById('review_images_container'), {
                animation: 150,
                handle: '.upload-box',
                filter: 'label.upload-box', // Don't drag the add button
                onEnd: function () {
                    const boxes = document.querySelectorAll('#review_images_container .upload-box.has-image img');
                    reviewImages = Array.from(boxes).map(img => img.getAttribute('src'));

                    // Re-render to ensure indices in onclick handlers are correct
                    renderReviewImages();
                }
            });

            // Sortable for Variations
            new Sortable(document.getElementById('variations_container'), {
                animation: 150,
                handle: '.variation-row',
                onEnd: function () {
                    saveVariationsStateFromDOM();
                    const reordered = [];
                    document.querySelectorAll('#variations_container .variation-row').forEach(row => {
                        const id = row.getAttribute('data-id');
                        const v = currentProductVariations.find(it => it.id == id);
                        if (v) reordered.push(v);
                    });
                    currentProductVariations = reordered;
                }
            });

            // Sortable for Reviews
            new Sortable(document.getElementById('reviews-container'), {
                animation: 150,
                handle: '.review-item',
                onEnd: function () {
                    const rows = document.querySelectorAll('#reviews-container .review-item');
                    const newOrder = [];
                    rows.forEach(row => {
                        const tempId = row.getAttribute('data-temp-id');
                        const item = currentProductReviews.find(r => r._tempId == tempId);
                        if (item) newOrder.push(item);
                    });
                    currentProductReviews = newOrder;
                }
            });
            // Sortable for FAQ
            new Sortable(document.getElementById('faqList'), {
                animation: 150,
                handle: '.faq-item', // Drag whole item
                onEnd: function () {
                    // Reorder data based on DOM
                    const newOrder = [];
                    document.querySelectorAll('#faqList .faq-item').forEach(row => {
                        const id = row.getAttribute('data-id');
                        // Find usage of this ID in current data. 
                        // Note: Input values are live-updated to configData, BUT 
                        // if we reorder, indices change. So we rely on ID finding.
                        const item = configData.faq.find(f => f.id == id);
                        if (item) newOrder.push(item);
                    });
                    configData.faq = newOrder;
                    renderFaq(); // Re-render to fix indices in onclick events
                    updateJsonFromVisual();
                }
            });
        });

        function removeProductImage(index) {
            // Delete file from server
            if (productImages[index]) {
                deleteFile(productImages[index]);
            }

            productImages.splice(index, 1);
            renderProductImages();
        }

        function maskStoreSales(input) {
            let val = input.value.replace(/\D/g, '');
            if (!val) val = '0';
            input.value = parseInt(val).toLocaleString('pt-BR');
            updateJsonFromVisual();
        }

        function maskStorePercent(input) {
            let val = input.value.replace(/\D/g, '');
            if (val === '') val = '0';

            if (parseInt(val) > 100) val = '100';

            input.value = parseInt(val) + '%';

            updateJsonFromVisual();
        }

        // --- Mask Handlers ---

        function getSeparators() {
            const loc = (configData.store && configData.store.locale) ? configData.store.locale : 'pt-BR';
            if (loc === 'pt-BR') return { dec: ',', thou: '.', loc: 'pt-BR' };
            return { dec: '.', thou: ',', loc: 'en-US' };
        }

        function formatMoney(cents) {
            const { dec, thou } = getSeparators();
            let val = (cents / 100).toFixed(2);
            let parts = val.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thou);
            return parts.join(dec);
        }

        function parseMoney(str) {
            const { dec, thou } = getSeparators();
            // Escape special chars for regex if needed (dot is special)
            let thouRegex = new RegExp(thou === '.' ? '\\.' : thou, 'g');
            let clean = str.replace(thouRegex, '').replace(dec, '.');
            return Math.round(parseFloat(clean) * 100) || 0;
        }

        function formatInt(val) {
            const { loc } = getSeparators();
            return parseInt(val || 0).toLocaleString(loc);
        }

        function parseIntFmt(str) {
            return parseInt(str.replace(/\D/g, '')) || 0;
        }

        function maskMoney(input) {
            const { dec, thou } = getSeparators();
            let v = input.value.replace(/\D/g, '');
            if (!v) { input.value = ''; return; }

            let val = (parseInt(v) / 100).toFixed(2);
            let parts = val.split('.');
            // Add thousands
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thou);

            input.value = parts.join(dec);
        }

        function maskInt(input) {
            const { loc } = getSeparators();
            let v = input.value.replace(/\D/g, '');
            if (!v) { input.value = ''; return; }
            input.value = parseInt(v).toLocaleString(loc);
        }

        function maskRating(input) {
            let v = input.value.replace(/\D/g, '');
            if (!v) { input.value = ''; return; }

            // Format as 1 decimal place (X.Y)
            // Example: 1 -> 0.1, 12 -> 1.2, 50 -> 5.0

            let val = parseInt(v);
            // Cap at 50 (5.0)
            if (val > 50) val = 50;

            let formatted = (val / 10).toFixed(1);
            input.value = formatted;
        }

        function maskDateTime(input) {
            let v = input.value.replace(/\D/g, '').substring(0, 12);
            let f = '';

            if (v.length > 0) f += v.substring(0, 2);
            if (v.length >= 3) f += '/' + v.substring(2, 4);
            if (v.length >= 5) f += '/' + v.substring(4, 8);
            if (v.length >= 9) f += ' ' + v.substring(8, 10);
            if (v.length >= 11) f += ':' + v.substring(10, 12);

            input.value = f;
        }

        function formatRating(input) {
            const { dec } = getSeparators();
            let vStr = input.value.replace(dec, '.');
            let v = parseFloat(vStr);
            if (isNaN(v)) v = 0;
            if (v > 5.0) v = 5.0;
            if (v < 0) v = 0;
            input.value = v.toFixed(1).replace('.', dec);
        }

        function showToast(message, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            // Icons
            let iconSvg = '';
            if (type === 'success') iconSvg = '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            else if (type === 'error') iconSvg = '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
            else iconSvg = '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

            toast.innerHTML = `${iconSvg}<span>${message}</span>`;

            container.appendChild(toast);

            // Auto remove
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        function saveVariationsStateFromDOM() {
            const rows = document.querySelectorAll('#variations_container .variation-row');
            rows.forEach(row => {
                const id = row.getAttribute('data-id');
                const v = currentProductVariations.find(item => item.id == id);
                if (v) {
                    const nameIn = row.querySelector('.var-name');
                    const priceIn = row.querySelector('.var-price');
                    if (nameIn) v.name = nameIn.value;
                    if (priceIn) v.price = parseMoney(priceIn.value);
                }
            });
        }

        function renderVariationsList() {
            const container = document.getElementById('variations_container');
            // Don't wipe if we are re-ordering? No, render always wipes.
            // We must rely on currentProductVariations being up to date.
            container.innerHTML = '';

            if (currentProductVariations.length === 0) {
                container.innerHTML = '<div style="color:#999; text-align:center; padding:10px;">Nenhuma variação adicionada.</div>';
                return;
            }

            currentProductVariations.forEach((v, idx) => {
                const row = document.createElement('div');
                row.className = 'variation-row';
                row.setAttribute('data-id', v.id);
                row.dataset.image = v.image || '';

                row.style.cssText = 'border:1px solid #eee; padding:12px; margin-bottom:8px; border-radius:6px; background:#fff; display:flex; gap:12px; align-items:center; cursor: move;';

                const imgContent = v.image
                    ? `<img src="${v.image}" style="width:100%; height:100%; object-fit:cover;">
                       <div class="remove-img" style="top:-8px; right:-8px; width:20px; height:20px; line-height:18px; font-size:14px;" onclick="removeVariationImage('${v.id}')">&times;</div>`
                    : `<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; color:#ccc;">
                            <div style="font-size:20px; line-height:1;">+</div>
                       </div>`;

                row.innerHTML = `
                    <div style="width:50px; height:50px; background:#f9fafb; border:2px dashed #d1d5db; border-radius:4px; position:relative; cursor:pointer; flex-shrink:0;" onclick="this.nextElementSibling.click()">
                        ${imgContent}
                    </div>
                    <input type="file" style="display:none;" accept="image/*" onchange="handleVariationImageUpload(this, '${v.id}')">
                    
                    <div style="flex:1;">
                        <input type="text" class="form-control var-name" placeholder="Nome (ex: Azul)" value="${v.name || ''}" style="margin-bottom:4px;" onchange="saveVariationsStateFromDOM()">
                        <input type="text" class="form-control var-price" placeholder="Preço" value="${formatMoney(v.price || 0)}" oninput="maskMoney(this)" onchange="saveVariationsStateFromDOM()">
                        <input type="hidden" class="var-img-input" value="${v.image || ''}">
                    </div>
                    
                    <button type="button" class="btn-xs" style="color:red; border:none; background:transparent;" onclick="deleteVariation(${idx})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                `;

                container.appendChild(row);
            });
        }

        function saveVariationsStateFromDOM() {
            const rows = document.querySelectorAll('#variations_container .variation-row');
            rows.forEach(row => {
                const id = row.getAttribute('data-id');
                let existing = currentProductVariations.find(v => v.id == id);
                if (existing) {
                    const nameInput = row.querySelector('.var-name');
                    const priceInput = row.querySelector('.var-price');
                    const urlInput = row.querySelector('.var-url');

                    if (nameInput) existing.name = nameInput.value;
                    if (priceInput) existing.price = parseMoney(priceInput.value);
                    if (urlInput) existing.checkout_url = urlInput.value;
                }
            });
        }
    </script>
</body>

</html>