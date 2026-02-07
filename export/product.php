<?php
// Load Config
$configFile = __DIR__ . '/database/config.json';
if (!file_exists($configFile)) {
    die("Config file not found.");
}

$configData = json_decode(file_get_contents($configFile), true);


$productId = null;

if (isset($_GET['product_id'])) {
    $productId = (int) $_GET['product_id'];
} elseif (preg_match('#/product/(\d+)#', $_SERVER['REQUEST_URI'], $m)) {
    $productId = (int) $m[1];
}

$store = $configData['store'] ?? ['currency_symbol' => 'R$']; // Fallback
$products = $configData['products'];

// Find product by ID
$product = null;
if ($productId !== null) {
    foreach ($products as $p) {
        if ($p['id'] === $productId) {
            $product = $p;
            break;
        }
    }
}

if (!$product) {
    http_response_code(404);
    require '404.php';
    exit;
}

// Helper for formatting price
// Helper for formatting price
function getSeparators()
{
    global $store;
    $locale = $store['locale'] ?? 'pt-BR';
    if ($locale === 'pt-BR')
        return [',', '.'];
    return ['.', ','];
}

function formatMoney($cents)
{
    global $store;
    list($dec, $thou) = getSeparators();
    return $store['currency_symbol'] . ' ' . number_format($cents / 100, 2, $dec, $thou);
}

// Calculate discount
$discountPercent = 0;
if ($product['original_price'] > 0) {
    $discountPercent = round((($product['original_price'] - $product['current_price']) / $product['original_price']) * 100);
}

// Calculate Installments (Simulated logic: 5x)
$installmentsCount = 5;
$installmentValue = $product['current_price'] / $installmentsCount;
// Add simple interest or rounding logic if needed, keeping it simple

function formatRatingCount($count)
{
    list($dec, $thou) = getSeparators();
    if ($count >= 1000) {
        return number_format($count / 1000, 1, $dec, $thou) . ' mil';
    }
    return number_format($count, 0, $dec, $thou);
}

function formatSoldCount($count)
{
    if ($count >= 1000) {
        return number_format($count / 1000, 1, '.', '') . 'K';
    }
    return $count;
}

function getDeliveryEstimate()
{
    $now = new DateTime();
    $start = (clone $now)->modify('+6 days');
    $end = (clone $now)->modify('+12 days');

    $months = [
        1 => 'jan',
        2 => 'fev',
        3 => 'mar',
        4 => 'abr',
        5 => 'mai',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'set',
        10 => 'out',
        11 => 'nov',
        12 => 'dez'
    ];

    $dayStart = $start->format('d');
    $dayEnd = $end->format('d');
    $month = $months[(int) $end->format('n')];

    return "Receba até {$dayStart}-{$dayEnd} de {$month}";
}

// Prepare Data for Search (Client-Side)
$searchProducts = [];
foreach ($products as $p) {
    $searchProducts[] = [
        'id' => $p['id'],
        'title' => $p['title'],
        'image' => $p['images'][0] ?? '',
        'priceFormatted' => formatMoney($p['current_price'])
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Detalhes do Produto</title>
    <base href="/">
    <link rel="stylesheet" href="assets/css/globals.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/product-detail.css">
    <style>
        /* Fixes for direct CSS usage vs CSS Modules */
        .descriptionContent {
            max-height: 100px;
            overflow: hidden;
            position: relative;
            transition: max-height 0.3s ease;
        }

        .descriptionContent.expanded {
            max-height: none;
            /* JS will handle this or we use a large max-height */
            max-height: 2000px;
        }

        .arrowRight img {
            display: block;
        }
    </style>
    <?php echo $store['custom_head_code'] ?? ''; ?>
</head>

<body>

    <div class="mobile-layout">
        <!-- Top Bar -->
        <div class="topBar">
            <div class="navRow">
                <button class="backButton" onclick="window.history.back()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="searchBar" style="position:relative;">
                    <svg class="searchIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" placeholder="Pesquisar" class="searchInput" id="productSearchInput"
                        autocomplete="off" />

                    <!-- Search Results Dropdown -->
                    <div id="productSearchResults"
                        style="display:none; position:absolute; top:100%; left:0; width:100%; background:white; border:1px solid #eee; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:1001; max-height:300px; overflow-y:auto; margin-top:4px;">
                    </div>
                </div>
                <div class="topIcons">
                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAACXBIWXMAAAsTAAALEwEAmpwYAAACGUlEQVR4nO3Xz4tNYRzH8dec/FpQSqKwsEUkKSULC0uhSbMSaxvZiaIpUlJMym404g+ws7KzsJiFspopojCL2RCD8evo1HPr9HTunevee869p867nsXt9nyfz+c8z/f5fh8aGhoaGmrCTXzFFBI1ZBlpGNN1NJFG40HdTKR1N5HW3UQa5UD8e+RN5AWP4X7ddiIvVh1NpJGBjJE2sRXjuIbHbQwIYkcmJ/bjDuY73Do/C+YlwzSxDufxqoPo1vgTDBoFE2M4gw9txH7CU0ziBHZjwwoxk6pMbMOzAtGf8QjHsbbH2EnZJo5hMVrgIy528YX7MTE1iMATUVf5G7cGKDzPqugyyHa3L8ZDErYCLuCockgGvQMHsZQLNh/yoAySQefAerzNBXuDHSvMyY7ULhwI4zD2dWE6KSOBp3LBfmBv9P8WnMY9vAxntVMteFil+D3Ruc9umozVOIvnXRSvePyqSryoh5kNxWsiHKNOIrPH++tQnbN5c8PohXaGr9UKeg5PCsQuh6J2BUfCkSqiyEBSZtG6HLUFcYP2Dhewqct4sYHSK+5sm+PxHZew5j/j5WOULn47/haIfx9a5l6IxZbasJ0qEL8Q8qJX2iX8dBnd5mS0SNbzHOozZmXiFdw2d/VPZeIzXkRJu1n/LFX5XJzLLTYzoJhXwzvidhVv3cWcgZNqyLdc37JRDbmBL7g+bCENDQ0NDcrkH4S4TaYRlwsWAAAAAElFTkSuQmCC"
                        alt="forward-arrow" class="topIcon" style="width: 28px; height: 28px; margin-top: -2px;">
                    <div class="relative flex items-center"
                        style="position: relative; display: flex; align-items: center;">
                        <svg class="topIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1" />
                            <circle cx="20" cy="21" r="1" />
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                        </svg>
                        <span class="badge badge-red" style="position: absolute; top: -5px; right: -5px;">0</span>
                    </div>
                    <svg class="topIcon" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="12" r="2" />
                        <circle cx="19" cy="12" r="2" />
                        <circle cx="5" cy="12" r="2" />
                    </svg>
                </div>
            </div>
            <div class="tabsRow">
                <div class="tabItem activeTab" onclick="scrollToSection('overview')" id="tab-overview"><span>Visão
                        geral</span></div>
                <div class="tabItem" onclick="scrollToSection('description')" id="tab-description">
                    <span>Descrição</span>
                </div>
                <div class="tabItem" onclick="scrollToSection('reviews')" id="tab-reviews"><span>Avaliações</span></div>
                <div class="tabItem" onclick="scrollToSection('recommendations')" id="tab-recommendations">
                    <span>Recomendações</span>
                </div>
            </div>
        </div>

        <!-- Product Image -->
        <div class="mainImageContainer" id="overview"
            style="width: 100%; aspect-ratio: 1/1; background: #fff; position: relative;">
            <div class="imageSlider" id="productImageSlider"
                style="display: flex; overflow-x: auto; scroll-snap-type: x mandatory; width: 100%; height: 100%; scrollbar-width: none;">
                <?php foreach ($product['images'] as $img): ?>
                    <div class="slideItem" style="flex-shrink: 0; width: 100%; height: 100%; scroll-snap-align: center;">
                        <img src="<?php echo htmlspecialchars($img); ?>"
                            alt="<?php echo htmlspecialchars($product['title']); ?>"
                            style="width: 100%; height: 100%; object-fit: cover; display: block;">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="imageCounter"
                style="position: absolute; bottom: 12px; right: 12px; background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                <span id="currentSlide">1</span>/<?php echo count($product['images']); ?>
            </div>
        </div>

        <!-- Header Section -->
        <div class="header">
            <svg class="lightningBackground" viewBox="0 0 24 24" fill="none">
                <defs>
                    <linearGradient id="lightningOpacityGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0.3" />
                        <stop offset="50%" stop-color="#ffffff" stop-opacity="0.1" />
                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <polygon points="8.29 1.71 18.5 1.71 13.86 9.14 17.57 9.14 7.36 20.29 9.21 12.86 5.5 12.86 8.29 1.71"
                    fill="url(#lightningOpacityGradient)" />
            </svg>
            <div class="priceContainer">
                <div class="priceMain">
                    <?php if ($discountPercent > 0): ?>
                        <span class="badge badge-discount">-<?php echo $discountPercent; ?>%</span>
                    <?php endif; ?>
                    <span class="currentPrice"><?php echo formatMoney($product['current_price']); ?></span>
                </div>
                <?php if ($product['original_price'] > $product['current_price']): ?>
                    <div class="originalPrice"><?php echo formatMoney($product['original_price']); ?></div>
                <?php endif; ?>
            </div>
            <div class="headerRight">
                <?php if (!empty($product['offer']['timer_initial_seconds']) && $product['offer']['timer_initial_seconds'] > 0): ?>
                    <div class="flashSale">
                        <svg class="flashIcon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7 2v11h3v9l7-12h-4l4-8z" />
                        </svg>
                        <span>Oferta Relâmpago</span>
                    </div>
                    <div class="timer" id="headerTimer">Termina em
                        <?php echo htmlspecialchars($product['offer']['timer_display'] ?? '00:00:00'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <!-- Installments -->
            <div class="installments">
                <div class="installmentsLeft">
                    <svg class="walletIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                    <span>1x <?php echo formatMoney($product['current_price']); ?> <span style="color: #26aa99;">sem
                            juros</span></span>
                </div>
            </div>

            <!-- Coupons -->
            <div class="coupons">
                <div class="coupon">
                    <svg width="12" height="10" viewBox="0 0 667 534" fill="none" xmlns="http://www.w3.org/2000/svg"
                        style="margin-right: 4px; display: inline-block; vertical-align: middle;">
                        <path
                            d="M666.666 166.667C640.145 166.667 614.71 177.203 595.956 195.956C577.202 214.71 566.666 240.145 566.666 266.667C566.666 293.189 577.202 318.624 595.956 337.378C614.71 356.131 640.145 366.667 666.666 366.667L666.666 466.667C666.666 484.348 659.642 501.305 647.14 513.808C634.637 526.31 617.681 533.334 600 533.334L466.666 533.334L466.666 470.601C466.666 461.76 463.154 453.281 456.903 447.03C450.652 440.779 442.174 437.267 433.333 437.267C424.492 437.267 416.014 440.779 409.763 447.03C403.512 453.281 400 461.76 400 470.601L400 533.334L66.666 533.334C48.9852 533.334 32.0287 526.31 19.5264 513.808C7.02395 501.305 1.13666e-05 484.348 1.01189e-05 466.667L1.44901e-05 366.666C55.4279 366.576 100.333 321.616 100.333 266.167C100.333 210.718 55.4279 165.758 2.3276e-05 165.668L2.76035e-05 66.667C3.0397e-05 48.9859 7.02397 32.0288 19.5264 19.5264C32.0287 7.02422 48.9852 8.86041e-05 66.666 4.76048e-07L400 1.50465e-05L400 62.7334C400 71.5739 403.512 80.0525 409.763 86.3037C416.014 92.5549 424.492 96.0674 433.333 96.0674C442.174 96.0674 450.652 92.5549 456.903 86.3037C463.154 80.0525 466.666 71.5739 466.666 62.7334L466.666 1.79606e-05L600 2.37888e-05C617.681 0.000113468 634.637 7.02425 647.14 19.5264C659.642 32.0288 666.666 48.9859 666.666 66.667L666.666 166.667ZM466.666 156.867C466.666 148.027 463.154 139.548 456.903 133.297C450.652 127.046 442.174 123.533 433.333 123.533C424.492 123.533 416.014 127.046 409.763 133.297C403.512 139.548 400 148.027 400 156.867L400 219.601C400 228.441 403.512 236.92 409.763 243.171C416.014 249.422 424.493 252.934 433.333 252.934C442.174 252.934 450.652 249.422 456.903 243.171C463.154 236.92 466.666 228.441 466.666 219.601L466.666 156.867ZM466.666 313.733C466.666 304.893 463.154 296.414 456.903 290.163C450.652 283.912 442.174 280.4 433.333 280.4C424.493 280.4 416.014 283.912 409.763 290.163C403.512 296.414 400 304.893 400 313.733L400 376.467C400 385.307 403.512 393.786 409.763 400.037C416.014 406.288 424.492 409.801 433.333 409.801C442.174 409.801 450.652 406.288 456.903 400.037C463.154 393.786 466.666 385.307 466.666 376.467L466.666 313.733Z"
                            fill="#fe2c55"></path>
                    </svg> Desconto de <?php echo $discountPercent; ?>%
                </div>
                <div class="coupon coupon-bonus">
                    <svg width="20px" height="20px" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"
                        class="shieldIcon"
                        style="width: 14px; height: 14px; margin-right: 4px; display: inline-block; vertical-align: middle; fill: #26aa99;">
                        <path d="M0 0h48v48H0z" fill="none"></path>
                        <g id="Shopicon">
                            <path
                                d="M24,44c0,0,20-4,20-34L24,4L4,10C4,42,24,44,24,44z M32.829,18L22,28.828L15.171,22L18,19.172l4,4l8-8L32.829,18z"
                                fill="currentColor"></path>
                        </g>
                    </svg>
                    Garantia TikTok Shop
                </div>
            </div>

            <!-- Title -->
            <div class="titleRow">
                <h1 class="title">
                    <?php echo htmlspecialchars($product['title']); ?>
                </h1>
                <svg class="bookmarkIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                </svg>
            </div>

            <!-- Stats -->
            <div class="stats">
                <div class="rating">
                    <svg class="starIcon" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    <span class="ratingValue"><?php echo number_format($product['rating_value'], 1, '.', ''); ?></span>
                    <span class="ratingCount">(<?php echo formatRatingCount($product['rating_count']); ?>)</span>
                </div>
                <span class="separator">|</span>
                <span class="soldCount"><?php echo formatSoldCount($product['sold_count']); ?> vendidos</span>
            </div>

            <!-- Shipping -->
            <div class="shippingInfo" onclick="openShippingModal()">
                <div class="shippingRow">
                    <svg width="20px" height="20px" viewBox="0 -2 20 20" xmlns="http://www.w3.org/2000/svg"
                        class="truckIcon" fill="none" stroke="currentColor">
                        <g id="delivery-truck" transform="translate(-2 -4)">
                            <path id="primary" d="M9.17,17H13V6a1,1,0,0,0-1-1H5" fill="none" stroke-linecap="round"
                                stroke-linejoin="round" stroke-width="2"></path>
                            <path id="primary-2" data-name="primary" d="M3,13v3a1,1,0,0,0,1,1h.87" fill="none"
                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            <path id="primary-3" data-name="primary"
                                d="M14.87,17H13V7h4.25a1,1,0,0,1,1,.73L19,10.5l1.24.31a1,1,0,0,1,.76,1V16a1,1,0,0,1-1,1h-.89"
                                fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            <path id="primary-4" data-name="primary"
                                d="M9,17a2,2,0,1,1-2-2A2,2,0,0,1,9,17Zm8-2a2,2,0,1,0,2,2A2,2,0,0,0,17,15ZM3,9H9"
                                fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                        </g>
                    </svg>
                    <div class="shippingContent">
                        <span class="freeShipping">Frete grátis</span>
                        <span><?php echo getDeliveryEstimate(); ?></span>
                    </div>
                    <span class="arrowRight" style="margin-left: auto;">
                        <img src="assets/images/arrow-right.svg" alt="forward"
                            style="width: 16px; height: 16px; display: block;" />
                    </span>
                </div>
                <div class="shippingFee">
                    Taxa de envio: <span
                        class="strikethrough"><?php echo formatMoney($product['shipping']['shipping_fee_original']); ?></span>
                </div>
            </div>

            <!-- Variations Section -->
            <?php
            $variations = $product['variations'] ?? [];
            if (!empty($variations) && !empty($product['has_variations'])): ?>
                <div class="variationsSection" onclick="openVariationsModal()">
                    <div class="variationsInfo">

                        <div class="variationsLabel">
                            <svg width="17" height="17" viewBox="0 0 18 18" fill="none" style="margin-right:8px;">
                                <rect x="2" y="2" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                                <rect x="10" y="2" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                                <rect x="2" y="10" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                                <rect x="10" y="10" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                        </div>

                        <div class="variationsPreview">
                            <?php foreach (array_slice($variations, 0, 5) as $v): ?>
                                <?php if (!empty($v['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($v['image']); ?>" class="variationPreviewThumb">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="variationsCount"><?php echo count($variations); ?> opções disponíveis</div>
                    </div>
                    <span class="arrowRight">
                        <img src="assets/images/arrow-right.svg" alt="forward"
                            style="width: 16px; height: 16px; display: block;" />
                    </span>
                </div>
            <?php endif; ?>

            <!-- Protection -->
            <div class="protection">
                <svg width="20px" height="20px" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"
                    class="shieldIcon">
                    <path d="M0 0h48v48H0z" fill="none"></path>
                    <g id="Shopicon">
                        <path
                            d="M24,44c0,0,20-4,20-34L24,4L4,10C4,42,24,44,24,44z M32.829,18L22,28.828L15.171,22L18,19.172l4,4l8-8L32.829,18z"
                            fill="#8B4D0B"></path>
                    </g>
                </svg>
                <div class="protectionContent">
                    <div class="protectionTitle">Proteção do cliente</div>
                    <div class="protectionList">
                        <div class="protectionItem">
                            <svg width="14px" height="14px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"
                                style="margin-right: 4px; flex-shrink: 0; min-width: 14px;">
                                <path fill="#8B4D0B" fill-rule="evenodd"
                                    d="M13.7071,4.29289 C14.0976,4.68342 14.0976,5.31658 13.7071,5.70711 L7.70711,11.7071 C7.31658,12.0976 6.68342,12.0976 6.29289,11.7071 L3.29289,8.70711 C2.90237,8.31658 2.90237,7.68342 3.29289,7.29289 C3.68342,6.90237 4.31658,6.90237 4.70711,7.29289 L7,9.58579 L12.2929,4.29289 C12.6834,3.90237 13.3166,3.90237 13.7071,4.29289 Z">
                                </path>
                            </svg> Devolução gratuita
                        </div>
                        <div class="protectionItem">
                            <svg width="14px" height="14px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"
                                style="margin-right: 4px; flex-shrink: 0; min-width: 14px;">
                                <path fill="#8B4D0B" fill-rule="evenodd"
                                    d="M13.7071,4.29289 C14.0976,4.68342 14.0976,5.31658 13.7071,5.70711 L7.70711,11.7071 C7.31658,12.0976 6.68342,12.0976 6.29289,11.7071 L3.29289,8.70711 C2.90237,8.31658 2.90237,7.68342 3.29289,7.29289 C3.68342,6.90237 4.31658,6.90237 4.70711,7.29289 L7,9.58579 L12.2929,4.29289 C12.6834,3.90237 13.3166,3.90237 13.7071,4.29289 Z">
                                </path>
                            </svg> Reembolso automático por danos
                        </div>
                        <div class="protectionItem">
                            <svg width="14px" height="14px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"
                                style="margin-right: 4px; flex-shrink: 0; min-width: 14px;">
                                <path fill="#8B4D0B" fill-rule="evenodd"
                                    d="M13.7071,4.29289 C14.0976,4.68342 14.0976,5.31658 13.7071,5.70711 L7.70711,11.7071 C7.31658,12.0976 6.68342,12.0976 6.29289,11.7071 L3.29289,8.70711 C2.90237,8.31658 2.90237,7.68342 3.29289,7.29289 C3.68342,6.90237 4.31658,6.90237 4.70711,7.29289 L7,9.58579 L12.2929,4.29289 C12.6834,3.90237 13.3166,3.90237 13.7071,4.29289 Z">
                                </path>
                            </svg> Pagamento seguro
                        </div>
                        <div class="protectionItem">
                            <svg width="14px" height="14px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"
                                style="margin-right: 4px; flex-shrink: 0; min-width: 14px;">
                                <path fill="#8B4D0B" fill-rule="evenodd"
                                    d="M13.7071,4.29289 C14.0976,4.68342 14.0976,5.31658 13.7071,5.70711 L7.70711,11.7071 C7.31658,12.0976 6.68342,12.0976 6.29289,11.7071 L3.29289,8.70711 C2.90237,8.31658 2.90237,7.68342 3.29289,7.29289 C3.68342,6.90237 4.31658,6.90237 4.70711,7.29289 L7,9.58579 L12.2929,4.29289 C12.6834,3.90237 13.3166,3.90237 13.7071,4.29289 Z">
                                </path>
                            </svg> Reembolso automático por atraso na coleta
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="description" id="description">
                <h2 class="descriptionTitle">Descrição</h2>
                <div class="descriptionContent" id="descriptionContent">

                    <div><?php echo nl2br($product['description_body']); ?></div>
                    <div class="descriptionGradient" id="descriptionGradient"></div>
                </div>
                <button class="viewMoreDescription" id="toggleDescriptionBtn">
                    <span id="toggleText">Ver mais</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        id="toggleIcon">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>
            </div>


            <!-- Seller Info -->
            <div class="sellerInfo">
                <div class="sellerHeader">
                    <div class="sellerAvatar">
                        <img src="<?php echo htmlspecialchars($store['logo']); ?>" alt="Seller Logo">
                    </div>
                    <div class="sellerDetails">
                        <div class="sellerName"><?php echo htmlspecialchars($store['name']); ?></div>
                        <div class="sellerSales"><?php echo formatSoldCount($store['sales_count']); ?> vendido(s)</div>
                    </div>
                    <a href="/chat" class="visitButton">Mensagem</a>
                </div>
                <div class="sellerStats">
                    <div class="statItem">
                        <strong><?php echo htmlspecialchars($store['response_rate']); ?>%</strong> responde em 24 horas
                    </div>
                    <div class="statItem">
                        <strong><?php echo htmlspecialchars($store['on_time_delivery_rate']); ?>%</strong> envios
                        pontuais
                    </div>
                </div>
            </div>

            <!-- Reviews -->
            <div class="reviewsSection" id="reviews">
                <?php
                $productReviews = $product['reviews'] ?? [];
                $reviewCount = count($productReviews);
                ?>
                <div class="reviewsTitle">Avaliações dos clientes (<?php echo $reviewCount; ?>)</div>
                <div class="ratingSummary">
                    <span
                        style="font-size: 16px;"><?php echo number_format($product['rating_value'], 1, '.', ''); ?></span>
                    <span style="font-size: 12px; font-weight: normal; color: #757575;"> /5</span>
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <svg class="starIcon" viewBox="0 0 24 24"
                            fill="<?php echo ($i < round($product['rating_value'])) ? '#ffce3d' : '#e0e0e0'; ?>">
                            <path
                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    <?php endfor; ?>
                </div>

                <?php
                // Global Gallery Initialization
                $allGalleryItems = [];
                $globalImageIndex = 0;
                ?>

                <?php foreach ($productReviews as $index => $review): ?>
                    <?php
                    $isHidden = $index >= 3;
                    $style = $isHidden ? 'style="display:none;"' : '';
                    $extraClass = $isHidden ? 'hidden-review' : '';
                    ?>
                    <div class="reviewItem <?php echo $extraClass; ?>" <?php echo $style; ?>>
                        <div class="reviewUser">
                            <?php if (!empty($review['user_avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($review['user_avatar']); ?>" class="userAvatar"
                                    alt="User">
                            <?php else: ?>
                                <?php
                                $initial = strtoupper(mb_substr($review['user_name'], 0, 1));
                                $colors = ['userAvatarPurple', 'userAvatarGreen'];
                                $colorClass = $colors[$index % count($colors)];
                                ?>
                                <div class="userAvatar <?php echo $colorClass; ?>"><?php echo $initial; ?></div>
                            <?php endif; ?>
                            <span class="userName"><?php echo htmlspecialchars($review['user_name']); ?></span>
                        </div>
                        <div class="reviewStars">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <svg class="starIcon" viewBox="0 0 24 24"
                                    fill="<?php echo ($i < $review['rating']) ? '#ffce3d' : '#e0e0e0'; ?>">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <div class="reviewMeta"><?php echo htmlspecialchars($review['date']); ?> | Variação:
                            <?php echo htmlspecialchars($review['variation']); ?>
                        </div>
                        <div class="reviewText">
                            <?php echo htmlspecialchars($review['text']); ?>
                        </div>
                        <?php if (!empty($review['images'])): ?>
                            <div class="reviewMedia">
                                <?php
                                // We build the metadata for the *current* image context
                                $currentReviewMeta = [
                                    'user_name' => $review['user_name'],
                                    'user_avatar' => $review['user_avatar'],
                                    'rating' => $review['rating'],
                                    'date' => $review['date'],
                                    'variation' => $review['variation'],
                                    'text' => $review['text'],
                                    'initial' => $initial ?? '',
                                    'colorClass' => $colorClass ?? '',
                                    'totalInReview' => count($review['images']),
                                    'indexInReview' => -1 // Will set inside loop
                                ];
                                ?>
                                <?php foreach ($review['images'] as $imgIdx => $img): ?>
                                    <?php
                                    // Add to global gallery
                                    $item = $currentReviewMeta;
                                    $item['src'] = $img;
                                    $item['indexInReview'] = $imgIdx;
                                    $allGalleryItems[] = $item;

                                    // Current global index for onclick
                                    $myGlobalIndex = $globalImageIndex;
                                    $globalImageIndex++;
                                    ?>
                                    <div class="mediaPlaceholder" onclick='openReviewLightbox(<?php echo $myGlobalIndex; ?>)'
                                        style="cursor:pointer;">
                                        <img src="<?php echo htmlspecialchars($img); ?>"
                                            style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($reviewCount > 3): ?>
                    <button class="viewMoreDescription" onclick="toggleReviews(this)">
                        Ver mais avaliações
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Recommendations -->
            <?php
            $filteredRecommendations = array_filter($products, function ($p) use ($product) {
                return $p['id'] !== $product['id'];
            });
            ?>

            <?php if (!empty($filteredRecommendations)): ?>
                <div class="recommendationsSection" id="recommendations">
                    <div class="recommendationsTitle">Você também pode gostar</div>
                    <div class="recommendationsGrid">
                        <?php foreach ($filteredRecommendations as $recProd): ?>
                            <?php
                            // Simulate discount logic
                            $hasDiscount = $recProd['original_price'] > $recProd['current_price'];
                            $saveAmount = $recProd['original_price'] - $recProd['current_price'];
                            ?>
                            <a href="/product/<?php echo $recProd['id']; ?>" class="productCard"
                                style="display: block; text-decoration: none; color: inherit;">
                                <div class="productImage">
                                    <img src="<?php echo htmlspecialchars($recProd['images'][0]); ?>"
                                        alt="<?php echo htmlspecialchars($recProd['title']); ?>"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div class="productInfo">
                                    <div class="productTitle"
                                        style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo htmlspecialchars($recProd['title']); ?>
                                    </div>
                                    <div class="productPriceRow">
                                        <span class="productPrice"><?php echo formatMoney($recProd['current_price']); ?></span>
                                        <?php if ($hasDiscount): ?>
                                            <span
                                                class="productOriginalPrice"><?php echo formatMoney($recProd['original_price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($saveAmount > 0): ?>
                                        <div class="discountTag">
                                            <svg width="12" height="10" viewBox="0 0 667 534" fill="none"
                                                xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px;">
                                                <path
                                                    d="M666.666 166.667C640.145 166.667 614.71 177.203 595.956 195.956C577.202 214.71 566.666 240.145 566.666 266.667C566.666 293.189 577.202 318.624 595.956 337.378C614.71 356.131 640.145 366.667 666.666 366.667L666.666 466.667C666.666 484.348 659.642 501.305 647.14 513.808C634.637 526.31 617.681 533.334 600 533.334L466.666 533.334L466.666 470.601C466.666 461.76 463.154 453.281 456.903 447.03C450.652 440.779 442.174 437.267 433.333 437.267C424.492 437.267 416.014 440.779 409.763 447.03C403.512 453.281 400 461.76 400 470.601L400 533.334L66.666 533.334C48.9852 533.334 32.0287 526.31 19.5264 513.808C7.02395 501.305 1.13666e-05 484.348 1.01189e-05 466.667L1.44901e-05 366.666C55.4279 366.576 100.333 321.616 100.333 266.167C100.333 210.718 55.4279 165.758 2.3276e-05 165.668L2.76035e-05 66.667C3.0397e-05 48.9859 7.02397 32.0288 19.5264 19.5264C32.0287 7.02422 48.9852 8.86041e-05 66.666 4.76048e-07L400 1.50465e-05L400 62.7334C400 71.5739 403.512 80.0525 409.763 86.3037C416.014 92.5549 424.492 96.0674 433.333 96.0674C442.174 96.0674 450.652 92.5549 456.903 86.3037C463.154 80.0525 466.666 71.5739 466.666 62.7334L466.666 1.79606e-05L600 2.37888e-05C617.681 0.000113468 634.637 7.02425 647.14 19.5264C659.642 32.0288 666.666 48.9859 666.666 66.667L666.666 166.667ZM466.666 156.867C466.666 148.027 463.154 139.548 456.903 133.297C450.652 127.046 442.174 123.533 433.333 123.533C424.492 123.533 416.014 127.046 409.763 133.297C403.512 139.548 400 148.027 400 156.867L400 219.601C400 228.441 403.512 236.92 409.763 243.171C416.014 249.422 424.493 252.934 433.333 252.934C442.174 252.934 450.652 249.422 456.903 243.171C463.154 236.92 466.666 228.441 466.666 219.601L466.666 156.867ZM466.666 313.733C466.666 304.893 463.154 296.414 456.903 290.163C450.652 283.912 442.174 280.4 433.333 280.4C424.493 280.4 416.014 283.912 409.763 290.163C403.512 296.414 400 304.893 400 313.733L400 376.467C400 385.307 403.512 393.786 409.763 400.037C416.014 406.288 424.492 409.801 433.333 409.801C442.174 409.801 450.652 406.288 456.903 400.037C463.154 393.786 466.666 385.307 466.666 376.467L466.666 313.733Z"
                                                    fill="#FF2B56"></path>
                                            </svg> Desconto de <?php echo formatMoney($saveAmount); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ratingRow">
                                        <svg viewBox="0 0 24 24" fill="#ffce3d">
                                            <path
                                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                        </svg>
                                        <span><?php echo number_format($recProd['rating_value'], 1, '.', ''); ?></span>
                                        <span class="ratingSeparator">|</span>
                                        <span><?php echo formatSoldCount($recProd['sold_count']); ?> vendidos</span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Bar -->
        <div class="bottomBar">
            <div class="shippingBanner">
                <div class="shippingBannerLeft">
                    <svg width="20px" height="20px" viewBox="0 -2 20 20" xmlns="http://www.w3.org/2000/svg"
                        class="truckIcon" fill="none" stroke="currentColor"
                        style="width: 18px; height: 18px; color: #26aa99;">
                        <g id="delivery-truck" transform="translate(-2 -4)">
                            <path id="primary" d="M9.17,17H13V6a1,1,0,0,0-1-1H5" fill="none" stroke-linecap="round"
                                stroke-linejoin="round" stroke-width="2"></path>
                            <path id="primary-2" data-name="primary" d="M3,13v3a1,1,0,0,0,1,1h.87" fill="none"
                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            <path id="primary-3" data-name="primary"
                                d="M14.87,17H13V7h4.25a1,1,0,0,1,1,.73L19,10.5l1.24.31a1,1,0,0,1,.76,1V16a1,1,0,0,1-1,1h-.89"
                                fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            <path id="primary-4" data-name="primary"
                                d="M9,17a2,2,0,1,1-2-2A2,2,0,0,1,9,17Zm8-2a2,2,0,1,0,2,2A2,2,0,0,0,17,15ZM3,9H9"
                                fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                        </g>
                    </svg>
                    <span class="shippingBannerText">O <span class="shippingBannerHighlight">frete grátis</span> expira
                        em breve</span>
                </div>
                <div class="shippingBannerRight">
                    <span class="shippingTimer" id="bottomTimer">00:00:00</span>
                    <span class="closeBanner">✕</span>
                </div>
            </div>

            <div class="actionButtons">
                <button class="actionIconBtn" onclick="window.location.href='/'">
                    <svg class="nav-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M5 11V17C5 18.8856 5 19.8284 5.58579 20.4142C6.17157 21 7.11438 21 9 21H15C16.8856 21 17.8284 21 18.4142 20.4142C19 19.8284 19 18.8856 19 17V11"
                            stroke="#000" stroke-width="2"></path>
                        <path
                            d="M4.62127 4.51493C4.80316 3.78737 4.8941 3.42359 5.16536 3.21179C5.43663 3 5.8116 3 6.56155 3H17.4384C18.1884 3 18.5634 3 18.8346 3.21179C19.1059 3.42359 19.1968 3.78737 19.3787 4.51493L20.5823 9.32938C20.6792 9.71675 20.7276 9.91044 20.7169 10.0678C20.6892 10.4757 20.416 10.8257 20.0269 10.9515C19.8769 11 19.6726 11 19.2641 11V11C18.7309 11 18.4644 11 18.2405 10.9478C17.6133 10.8017 17.0948 10.3625 16.8475 9.76781C16.7593 9.55555 16.7164 9.29856 16.6308 8.78457V8.78457C16.6068 8.64076 16.5948 8.56886 16.5812 8.54994C16.5413 8.49439 16.4587 8.49439 16.4188 8.54994C16.4052 8.56886 16.3932 8.64076 16.3692 8.78457L16.2877 9.27381C16.2791 9.32568 16.2747 9.35161 16.2704 9.37433C16.0939 10.3005 15.2946 10.9777 14.352 10.9995C14.3289 11 14.3026 11 14.25 11V11C14.1974 11 14.1711 11 14.148 10.9995C13.2054 10.9777 12.4061 10.3005 12.2296 9.37433C12.2253 9.35161 12.2209 9.32568 12.2123 9.27381L12.1308 8.78457C12.1068 8.64076 12.0948 8.56886 12.0812 8.54994C12.0413 8.49439 11.9587 8.49439 11.9188 8.54994C11.9052 8.56886 11.8932 8.64076 11.8692 8.78457L11.7877 9.27381C11.7791 9.32568 11.7747 9.35161 11.7704 9.37433C11.5939 10.3005 10.7946 10.9777 9.85199 10.9995C9.82887 11 9.80258 11 9.75 11V11C9.69742 11 9.67113 11 9.64801 10.9995C8.70541 10.9777 7.90606 10.3005 7.7296 9.37433C7.72527 9.35161 7.72095 9.32568 7.7123 9.27381L7.63076 8.78457C7.60679 8.64076 7.59481 8.56886 7.58122 8.54994C7.54132 8.49439 7.45868 8.49439 7.41878 8.54994C7.40519 8.56886 7.39321 8.64076 7.36924 8.78457V8.78457C7.28357 9.29856 7.24074 9.55555 7.15249 9.76781C6.90524 10.3625 6.38675 10.8017 5.75951 10.9478C5.53563 11 5.26905 11 4.73591 11V11C4.32737 11 4.12309 11 3.97306 10.9515C3.58403 10.8257 3.31078 10.4757 3.28307 10.0678C3.27239 9.91044 3.32081 9.71675 3.41765 9.32938L4.62127 4.51493Z"
                            stroke="#000" stroke-width="2"></path>
                    </svg>
                    <span>Loja</span>
                </button>
                <button class="actionIconBtn" onclick="window.location.href='/chat'">
                    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                        <path
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span>Chat</span>
                </button>

                <button class="btn btn-secondary flex-1 text-center text-base"
                    style="padding: 0 4px; line-height: 1.2; height: 48px;" onclick="handleMainAction()">
                    Adicionar<br />ao carrinho
                </button>

                <button class="btn btn-primary flex-1 flex-col justify-center text-center"
                    style="padding: 0 4px; line-height: 1.1; height: 48px;" onclick="handleMainAction()">
                    <div style="font-size: 14px;">Comprar agora</div>
                    <div class="buyNowSubtext">Frete grátis</div>
                </button>
            </div>
        </div>
    </div>

    <!-- Variations Modal -->
    <?php if (!empty($variations)): ?>
        <div class="bottomSheetOverlay" id="variationsModal" onclick="if(event.target === this) closeVariationsModal()">
            <div class="bottomSheetContent">
                <!-- Header -->
                <div class="modalHeader">
                    <img src="<?php echo htmlspecialchars($variations[0]['image'] ?? $product['images'][0] ?? ''); ?>"
                        id="modalProductImage" class="modalProductThumb">
                    <div class="modalProductInfo">
                        <div class="modalPrice" id="modalPrice"></div> <!-- JS will saturate -->
                        <div class="modalSelectedText" id="modalSelectedText">Padrão</div>
                    </div>
                    <button class="modalCloseBtn" onclick="closeVariationsModal()">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="modalBody">
                    <div class="variationGroupTitle"><?php echo htmlspecialchars($product['variation_type'] ?? 'Opção'); ?>
                        (<?php echo count($variations); ?>)</div>
                    <div class="variationsGrid">
                        <?php foreach ($variations as $index => $v): ?>
                            <div class="variationOption <?php echo $index === 0 ? 'selected' : ''; ?>"
                                onclick="selectVariation(<?php echo $index; ?>)" id="var-opt-<?php echo $index; ?>"
                                data-price="<?php echo $v['price']; ?>"
                                data-image="<?php echo htmlspecialchars($v['image']); ?>"
                                data-name="<?php echo htmlspecialchars($v['name']); ?>">

                                <?php if (!empty($v['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($v['image']); ?>" loading="lazy">
                                <?php endif; ?>
                                <span>
                                    <?php echo htmlspecialchars($v['name']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>


                </div>

                <!-- Footer -->
                <div class="modalFooter">
                    <button class="btn btn-primary" style="width:100%; height:48px;"
                        onclick="handleCheckout(currentCheckoutUrl)">
                        Comprar Agora
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Shipping Modal -->
    <div class="bottomSheetOverlay" id="shippingModal" onclick="if(event.target === this) closeShippingModal()">
        <div class="bottomSheetContent">
            <div class="modalHeader">
                <div style="font-size:16px; font-weight:700;">Envio e Entrega</div>
                <button class="modalCloseBtn" onclick="closeShippingModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modalBody">
                <div style="margin-bottom:16px;">
                    <div style="font-weight:600; margin-bottom:4px;">Entrega Padrão</div>
                    <div style="color:#26aa99; font-size:13px; font-weight:500;">Frete Grátis</div>
                    <div style="color:#757575; font-size:12px; margin-top:2px;">Chega em
                        <?php echo str_replace('Receba até ', '', getDeliveryEstimate()); ?>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <div style="font-size:12px; color:#555;">Enviado de Brasil</div>
                    <div style="font-size:12px; color:#555;">Garantia de entrega no prazo</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- Variations Logic ---
        let currentQty = 1;
        // Globals for variations (passed from PHP)
        // Ensure we default to empty array if not defined/null
        const variationsData = <?php echo json_encode($variations ?? []); ?>;
        const hasVariations = <?php echo json_encode($product['has_variations'] ?? false); ?>;
        const checkoutUrl = '<?php echo htmlspecialchars($product['checkout_url'] ?? ''); ?>';
        let currentCheckoutUrl = checkoutUrl;

        function handleMainAction() {
            if (hasVariations) {
                openVariationsModal();
            } else {
                handleCheckout(checkoutUrl);
            }
        }

        function openVariationsModal() {
            const modal = document.getElementById('variationsModal');
            if (modal) {
                modal.classList.add('open');
                // Ensure first is selected if none
                if (!document.querySelector('.variationOption.selected')) {
                    selectVariation(0);
                } else {
                    // Ensure UI consistency
                    const selected = document.querySelector('.variationOption.selected');
                    if (selected) {
                        const idx = parseInt(selected.id.replace('var-opt-', ''));
                        selectVariation(idx);
                    }
                }
            }
        }

        function closeVariationsModal() {
            const modal = document.getElementById('variationsModal');
            if (modal) modal.classList.remove('open');
        }

        function selectVariation(index) {
            // Remove previous selected
            document.querySelectorAll('.variationOption').forEach(el => el.classList.remove('selected'));

            // Add to new
            const el = document.getElementById('var-opt-' + index);
            if (el) el.classList.add('selected');

            // Update Header Info
            const v = variationsData[index];
            if (v) {
                // Update Checkout URL
                currentCheckoutUrl = (v.checkout_url && v.checkout_url.trim() !== '') ? v.checkout_url : checkoutUrl;

                const imgEl = document.getElementById('modalProductImage');
                if (imgEl) imgEl.src = v.image;

                const txtEl = document.getElementById('modalSelectedText');
                if (txtEl) txtEl.textContent = v.name;

                // Format Price JS side
                const storeLocale = '<?php echo $store['locale']; ?>';
                const currency = '<?php echo $store['currency_symbol']; ?>';

                let dec = ',', thou = '.';
                if (storeLocale !== 'pt-BR') { dec = '.'; thou = ','; }

                let val = (v.price / 100).toFixed(2);
                let parts = val.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thou);

                const priceEl = document.getElementById('modalPrice');
                if (priceEl) priceEl.textContent = currency + ' ' + parts.join(dec);
            }
        }

        function updateQuantity(change) {
            currentQty += change;
            if (currentQty < 1) currentQty = 1;
            const qtyEl = document.getElementById('qtyValue');
            if (qtyEl) qtyEl.textContent = currentQty;
        }

        // Initialize price for index 0 if variations exist
        <?php if (!empty($variations)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                selectVariation(0);
            });
        <?php endif; ?>

        // --- Shipping Modal ---
        function openShippingModal() {
            const modal = document.getElementById('shippingModal');
            if (modal) modal.classList.add('open');
        }

        function closeShippingModal() {
            const modal = document.getElementById('shippingModal');
            if (modal) modal.classList.remove('open');
        }

        // Countdown Timer Logic
        function startCountdown(durationInSeconds) {
            let timer = durationInSeconds;
            const headerTimerEl = document.getElementById('headerTimer');
            const bottomTimerEl = document.getElementById('bottomTimer');

            function updateDisplay() {
                const hours = Math.floor(timer / 3600);
                const minutes = Math.floor((timer % 3600) / 60);
                const seconds = timer % 60;

                const formattedTime =
                    (hours < 10 ? "0" + hours : hours) + ":" +
                    (minutes < 10 ? "0" + minutes : minutes) + ":" +
                    (seconds < 10 ? "0" + seconds : seconds);

                if (headerTimerEl) headerTimerEl.textContent = "Termina em " + formattedTime;
                if (bottomTimerEl) bottomTimerEl.textContent = formattedTime;

                if (--timer < 0) {
                    timer = 0; // Stop at 0 or logic to restart
                    // Optional: Clear interval if desired
                    // clearInterval(intervalId); 
                }
            }

            updateDisplay(); // Initial call
            const intervalId = setInterval(updateDisplay, 1000);
        }

        // Initialize countdown dynamically
        const initialSeconds = <?php echo isset($product['offer']['timer_initial_seconds']) ? $product['offer']['timer_initial_seconds'] : 0; ?>;
        if (initialSeconds > 0) {
            startCountdown(initialSeconds);
        }

        // Dynamic Description Expansion (50% rule)
        const toggleBtn = document.getElementById('toggleDescriptionBtn');
        const descContent = document.getElementById('descriptionContent');
        const descGradient = document.getElementById('descriptionGradient');
        const toggleText = document.getElementById('toggleText');
        const toggleIcon = document.getElementById('toggleIcon');

        if (toggleBtn && descContent) {
            let isExpanded = false;

            function updateDescriptionHeight() {
                if (!isExpanded) {
                    const fullHeight = descContent.scrollHeight;
                    const targetHeight = fullHeight * 0.5;
                    descContent.style.maxHeight = `${targetHeight}px`;
                }
            }

            // Apply initial height
            setTimeout(updateDescriptionHeight, 100);
            window.addEventListener('load', updateDescriptionHeight);

            toggleBtn.addEventListener('click', () => {
                isExpanded = !isExpanded;
                const fullHeight = descContent.scrollHeight;

                if (isExpanded) {
                    descContent.style.maxHeight = `${fullHeight}px`;
                    descGradient.style.display = 'none';
                    toggleText.innerText = 'Ver menos';
                    toggleIcon.style.transform = 'rotate(180deg)';
                } else {
                    const halfHeight = fullHeight * 0.5;
                    descContent.style.maxHeight = `${halfHeight}px`;
                    descGradient.style.display = 'block';
                    toggleText.innerText = 'Ver mais';
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
            });
        }


        // Image Slider Counter
        const slider = document.getElementById('productImageSlider');
        const counter = document.getElementById('currentSlide');

        if (slider && counter) {
            slider.addEventListener('scroll', () => {
                const index = Math.round(slider.scrollLeft / slider.clientWidth) + 1;
                counter.textContent = index;
            });
        }

        // Scroll Spy / Active Tab
        const tabs = document.querySelectorAll('.tabItem');
        const sections = ['recommendations', 'reviews', 'description', 'overview'];

        function scrollToSection(id) {
            const element = document.getElementById(id);
            if (element) {
                const topOffset = 100;
                const elementPosition = element.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - topOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });

                setActiveTab(id);
            }
        }

        function setActiveTab(id) {
            tabs.forEach(tab => {
                if (tab.id === 'tab-' + id) {
                    tab.classList.add('activeTab');
                } else {
                    tab.classList.remove('activeTab');
                }
            });
        }

        window.addEventListener('scroll', () => {
            const scrollPosition = window.scrollY + 150; // Offset

            for (const sectionId of sections) {
                const element = document.getElementById(sectionId);
                if (element && element.offsetTop <= scrollPosition) {
                    setActiveTab(sectionId);
                    break;
                }
            }
        });

        function handleCheckout(url) {
            // Trim whitespace and check for empty strings or '#', 'null', etc if desirable
            if (!url || url.trim() === '' || url === '#') {
                showToast("Nenhum link de checkout configurado para esta loja. Configure um link para começar a vender.", "error");
                return;
            }
            window.location.href = url;
        }

        function toggleReviews(btn) {
            const hidden = document.querySelectorAll('.hidden-review');
            hidden.forEach(el => el.style.display = 'block');
            btn.style.display = 'none';
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
            }, 6000);
        }
    </script>
    <!-- Review Lightbox -->
    <div id="reviewLightbox"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:black; z-index:9999; flex-direction:column;">

        <!-- Header / Close -->
        <div style="position:absolute; top:20px; right:20px; z-index:10001;">
            <button onclick="closeReviewLightbox()"
                style="background:rgba(255,255,255,0.2); width:40px; height:40px; border-radius:50%; border:none; color:white; font-size:24px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>

        <!-- Main Content Area -->
        <div
            style="flex:1; width:100%; position:relative; display:flex; align-items:center; justify-content:center; overflow:hidden;">
            <button id="lbPrev"
                style="position:absolute; left:10px; z-index:10000; color:white; background:rgba(0,0,0,0.5); width:44px; height:44px; border-radius:50%; border:none; font-size:24px; cursor:pointer;"
                onclick="paginateReviewImage(-1)">&#10094;</button>

            <img id="lightboxImage" src="" style="width:100%; height:100%; object-fit:contain;">

            <button id="lbNext"
                style="position:absolute; right:10px; z-index:10000; color:white; background:rgba(0,0,0,0.5); width:44px; height:44px; border-radius:50%; border:none; font-size:24px; cursor:pointer;"
                onclick="paginateReviewImage(1)">&#10095;</button>
        </div>

        <!-- Footer / Info -->
        <div
            style="background:rgba(20,20,20,0.95); padding:20px; color:white; width:100%; box-sizing:border-box; border-top:1px solid #333;">
            <div style="max-width:600px; margin:0 auto;">
                <div style="display:flex; gap:12px; margin-bottom:12px; align-items: center;">
                    <img id="lbAvatar" src=""
                        style="width:40px; height:40px; border-radius:50%; background:#444; object-fit:cover; flex-shrink:0;">
                    <div id="lbAvatarPlaceholder" class="userAvatar"
                        style="width:40px; height:40px; font-size:18px; display:none; flex-shrink:0;"></div>

                    <div style="display:flex; flex-direction:column;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div id="lbName" style="font-weight:700; font-size:14px; color: white;"></div>
                            <div id="lbStars" style="display:flex; gap:1px;"></div>
                        </div>
                        <div id="lbVariation" style="font-size:12px; color:#aaa; margin-top:2px;"></div>
                    </div>
                </div>
                <div id="lbText" style="font-size:13px; line-height:1.4; color:#eee;"></div>
                <div id="lightboxCounter" style="font-size:11px; color:#666; margin-top:8px; text-align:right;">
                </div>
            </div>
        </div>
    </div>

    <script>
        // Inject PHP Gallery Data
        const globalGallery = <?php echo json_encode($allGalleryItems); ?>;

        let currentItem = null;
        let lightboxIndex = 0; // Global Index

        function openReviewLightbox(index) {
            lightboxIndex = index;
            if (lightboxIndex < 0) lightboxIndex = 0;
            if (lightboxIndex >= globalGallery.length) lightboxIndex = 0;

            updateLightboxUI();
            document.getElementById('reviewLightbox').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeReviewLightbox() {
            document.getElementById('reviewLightbox').style.display = 'none';
            document.body.style.overflow = '';
        }

        function paginateReviewImage(dir) {
            event.stopPropagation();
            if (globalGallery.length === 0) return;

            let newIndex = lightboxIndex + dir;
            // Wrap around global list
            if (newIndex < 0) newIndex = globalGallery.length - 1;
            if (newIndex >= globalGallery.length) newIndex = 0;

            lightboxIndex = newIndex;
            updateLightboxUI();
        }

        function updateLightboxUI() {
            if (globalGallery.length === 0) return;

            currentItem = globalGallery[lightboxIndex];
            if (!currentItem) return;

            // Image
            const img = document.getElementById('lightboxImage');
            img.src = currentItem.src;

            // Counter: Show "Image X of Y (Review Name)" or just "X / Y" relative to *this review*?
            // User request implies continuous flow. Usually "1/3" refers to the specific review's images.
            // Let's keep it relative to the review for clarity.
            document.getElementById('lightboxCounter').innerText = (currentItem.indexInReview + 1) + ' / ' + currentItem.totalInReview;

            // Nav Buttons (always show if we have > 1 total images in entire gallery, or maybe just always?)
            // If there is only 1 image TOTAL globally, hide.
            const showNav = globalGallery.length > 1;
            document.getElementById('lbPrev').style.display = showNav ? 'block' : 'none';
            document.getElementById('lbNext').style.display = showNav ? 'block' : 'none';

            // User Info
            document.getElementById('lbName').textContent = currentItem.user_name || 'Usuário';

            const avatarParams = currentItem.user_avatar || '';
            const avatarEl = document.getElementById('lbAvatar');
            const avatarPlc = document.getElementById('lbAvatarPlaceholder');

            if (avatarParams) {
                avatarEl.src = avatarParams;
                avatarEl.style.display = 'block';
                avatarPlc.style.display = 'none';
            } else {
                avatarEl.style.display = 'none';
                avatarPlc.style.display = 'flex';
                avatarPlc.innerHTML = currentItem.initial || '';
                avatarPlc.className = 'userAvatar ' + (currentItem.colorClass || 'userAvatarPurple');
                // Force styles
                avatarPlc.style.width = '40px';
                avatarPlc.style.height = '40px';
                avatarPlc.style.fontSize = '18px';
                avatarPlc.style.flexShrink = '0';
            }

            document.getElementById('lbVariation').textContent = 'Variação: ' + (currentItem.variation || '-');
            document.getElementById('lbText').textContent = currentItem.text || '';

            // Stars
            const starsContainer = document.getElementById('lbStars');
            starsContainer.innerHTML = '';
            const rating = currentItem.rating || 5;
            for (let i = 0; i < 5; i++) {
                const color = (i < rating) ? '#ffce3d' : '#555';
                starsContainer.innerHTML += `<svg width="12" height="12" viewBox="0 0 24 24" fill="${color}"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>`;
            }
        }
    </script>
    <script>
        const allSearchProducts = <?php echo json_encode($searchProducts); ?>;

        const searchInput = document.getElementById('productSearchInput');
        const searchResults = document.getElementById('productSearchResults');

        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function (e) {
                const query = e.target.value.toLowerCase().trim();
                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }

                const matches = allSearchProducts.filter(p => p.title.toLowerCase().includes(query));

                if (matches.length > 0) {
                    let html = '';
                    matches.forEach(p => {
                        html += `
                        <a href="/product/${p.id}" style="display:flex; gap:10px; padding:10px; border-bottom:1px solid #f9f9f9; text-decoration:none; align-items:center; transition:background 0.2s;">
                            <img src="${p.image}" style="width:40px; height:40px; object-fit:cover; border-radius:4px; flex-shrink:0;">
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:13px; font-weight:500; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.title}</div>
                                <div style="font-size:12px; font-weight:700; color:#fe2c55;">${p.priceFormatted}</div>
                            </div>
                        </a>
                        `;
                    });
                    searchResults.innerHTML = html;
                    searchResults.style.display = 'block';

                    const links = searchResults.querySelectorAll('a');
                    links.forEach(link => {
                        link.onmouseenter = () => link.style.background = '#f5f5f5';
                        link.onmouseleave = () => link.style.background = 'white';
                    });

                } else {
                    searchResults.style.display = 'none';
                }
            });

            // Close when clicking outside
            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });

            // Focus handler
            searchInput.addEventListener('focus', function () {
                if (this.value.trim().length >= 2) {
                    this.dispatchEvent(new Event('input'));
                }
            });
        }
    </script>
</body>

</html>