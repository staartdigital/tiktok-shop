<?php
// Load Config
$configFile = __DIR__ . '/database/config.json';
if (!file_exists($configFile)) {
    die("Config file not found.");
}
$configData = json_decode(file_get_contents($configFile), true);

$store = $configData['store'] ?? ['currency_symbol' => 'R$'];
$products = $configData['products'] ?? [];

// Helpers (still needed for initial render if we wanted SSR, but we will go full CSR for sorting simplicity or hybrid)
// actually, for "Recomendado" we can keep PHP render, but then sorting needs JS render.
// easier to just do everything JS render to avoid code duplication between PHP loop and JS template.
// But to prevent FOUC (Flash of Unstyled Content), we might want PHP render for initial state.
// However, the user asked for "Filters Functional", implying dynamic behavior.
// I will use JS for everything to ensure consistency.

// Pass data to JS
$jsProducts = [];
foreach ($products as $k => $p) {
    // Pre-calculate display values to simplify JS
    $discountPercent = 0;
    if ($p['original_price'] > 0) {
        $discountPercent = round((($p['original_price'] - $p['current_price']) / $p['original_price']) * 100);
    }

    $jsProducts[] = [
        'id' => $p['id'],
        'title' => $p['title'],
        'current_price' => $p['current_price'],
        'original_price' => $p['original_price'],
        'image' => $p['images'][0] ?? '',
        'rating_value' => $p['rating_value'] ?? 0,
        'sold_count' => $p['sold_count'] ?? 0,
        'discount_percent' => $discountPercent,
        'discount_amount' => $p['original_price'] - $p['current_price'],
        'original_index' => $k // For resetting order
    ];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($store['name'] ?? 'Loja'); ?></title>
    <base href="/">
    <link rel="stylesheet" href="assets/css/globals.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/product-detail.css">
    <?php echo $store['custom_head_code'] ?? ''; ?>
</head>
<style>
    body {
        background-color: #f5f5f5;
    }

    .home-content-padding {
        padding-top: 52px;
        /* Space for fixed navRow */
    }

    .store-profile {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 16px;
        background: white;
    }

    .store-info {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .store-logo {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        border: 1px solid #eee;
        object-fit: cover;
    }

    .store-stats {
        margin-top: 4px;
        font-size: 12px;
        color: #757575;
    }

    .store-actions {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: flex-end;
        width: 100px;
        /* Fixed width for alignment */
    }

    .btn-follow,
    .btn-message {
        width: 100%;
        /* Ensure same width */
        font-size: 12px;
        padding: 0;
        /* Center text */
        border-radius: 999px;
        cursor: pointer;
        box-sizing: border-box;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 28px;
    }

    .btn-follow {
        background: #fe2c55;
        color: white;
        font-weight: 600;
        border: none;
        transition: background 0.2s, color 0.2s;
    }

    .btn-follow.following {
        background: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }

    .btn-message {
        background: white;
        color: #333;
        border: 1px solid #ddd;
        font-weight: 500;
    }

    .coupons-container {
        background: white;
        padding: 12px 16px;
        margin-bottom: 8px;
    }

    .coupons-scroll {
        display: flex;
        overflow-x: auto;
        gap: 8px;
        scrollbar-width: none;
    }

    .coupons-scroll::-webkit-scrollbar {
        display: none;
    }

    .c-coupon {
        background: white;
        border-radius: 4px;
        padding: 8px 12px;
        min-width: 140px;
        border-left: 4px solid #fe2c55;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .c-left {
        font-size: 11px;
        color: #555;
        font-weight: 500;
    }

    .c-highlight {
        color: #fe2c55;
        font-weight: 700;
        font-size: 13px;
        margin-bottom: 2px;
    }

    .c-btn {
        background: #fe2c55;
        color: white;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 2px;
        border: none;
        font-weight: 600;
    }

    .filter-row {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        /* Reduced gap slightly */
        padding: 12px 24px;
        background: white;
        border-bottom: 1px solid #f5f5f5;
        position: sticky;
        top: 54px;
        z-index: 90;
    }

    .filter-item {
        font-size: 14px;
        color: #757575;
        white-space: nowrap;
        cursor: pointer;
    }

    .filter-item.active {
        color: #fe2c55;
        font-weight: 600;
    }

    .product-grid-container {
        padding: 12px 16px;
        min-height: 400px;
    }

    .recommendationsGrid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    /* Ensure search dropdown is positioned relative to the bar */
    .searchBar {
        position: relative;
        flex: 1;
        background: #f1f1f1;
        border-radius: 4px;
        padding: 0 12px;
        height: 36px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 12px;
    }

    .navRow {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        height: 52px;
        box-sizing: border-box;
        background: white;
        justify-content: space-between;
    }

    .backButton {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        color: #333;
    }

    .topIcons {
        display: flex;
        gap: 16px;
        align-items: center;
        color: #333;
    }

    .searchInput {
        border: none;
        background: transparent;
        outline: none;
        font-size: 14px;
        width: 100%;
        color: #333;
    }

    .searchIcon {
        width: 16px;
        height: 16px;
        color: #888;
    }
</style>
<?php echo $store['custom_head_code'] ?? ''; ?>
</head>

<body>
    <div class="mobile-layout" style="padding-top:0;">

        <!-- Top Bar -->
        <!-- Top Bar -->
        <div class="topBar"
            style="position:fixed; top:0; width:100%; max-width:480px; z-index:1000; border-bottom:1px solid #f0f0f0; overflow:visible;">
            <div class="navRow" style="overflow:visible;">
                <button class="backButton" onclick="window.history.back()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="searchBar">
                    <svg class="searchIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" placeholder="Pesquisar" class="searchInput" id="productSearchInput"
                        autocomplete="off" />
                    <!-- Search Results -->
                    <div id="productSearchResults"
                        style="display:none; position:absolute; top:100%; left:0; width:100%; background:white; border:1px solid #eee; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:9999; max-height:300px; overflow-y:auto; margin-top:4px;">
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
        </div>

        <div class="home-content-padding">
            <!-- Store Info -->
            <div class="store-profile">
                <div class="store-info">
                    <img src="<?php echo !empty($store['logo_url']) ? htmlspecialchars($store['logo_url']) : (!empty($store['logo']) ? htmlspecialchars($store['logo']) : 'https://ui-avatars.com/api/?name=' . urlencode($store['name']) . '&background=random'); ?>"
                        class="store-logo">
                    <div>
                        <div style="font-weight:700; font-size:16px;"><?php echo htmlspecialchars($store['name']); ?>
                        </div>
                        <?php
                        // Format sold count for initial display
                        function fmtSold($c)
                        {
                            if ($c >= 1000)
                                return number_format($c / 1000, 1, '.', '') . 'K';
                            return $c;
                        }
                        ?>
                        <div class="store-stats"><?php echo fmtSold($store['sales_count'] ?? 0); ?> vendido(s)</div>

                    </div>
                </div>
                <div class="store-actions">
                    <button class="btn-follow" id="followBtn" onclick="toggleFollow()">Seguir</button>
                    <button class="btn-message" onclick="window.location='/chat'">Mensagem</button>
                </div>
            </div>

            <!-- Coupons -->


            <!-- Filter Row -->
            <div class="filter-row">
                <div class="filter-item active" onclick="applyFilter('recommended', this)">Recomendado</div>
                <div class="filter-item" onclick="applyFilter('bestsellers', this)">Mais vendidos</div>
                <div class="filter-item" onclick="applyFilter('newest', this)">Lançamentos</div>
                <div class="filter-item" onclick="applyFilter('price_asc', this)">Preço</div>
            </div>

            <!-- Product List Container -->
            <div class="product-grid-container">
                <div class="recommendationsGrid" id="productsGrid">
                    <!-- JS will populate this -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Init Data
        const productsData = <?php echo json_encode($jsProducts); ?>;
        const currencySymbol = '<?php echo $store['currency_symbol']; ?>';

        // Helpers
        function formatMoney(cents) {
            let val = (cents / 100).toFixed(2);
            let parts = val.split('.');
            // Use simple Brazil formatting fallback
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            return currencySymbol + ' ' + parts.join(',');
        }

        function formatSold(count) {
            if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
            return count;
        }

        // Render Function
        function renderProducts(list) {
            const container = document.getElementById('productsGrid');
            container.innerHTML = '';

            list.forEach(p => {
                const hasDiscount = p.original_price > p.current_price;

                // Discount tag overlay (The one with icon, if user wants that kept? Use request said "Delete as badges 'Desconto de R$ 10,00'". That might be the tag or the simple span. 
                // The prompt specificially said "badges 'Desconto de R$ 10,00' e 'Frete grátis'". This usually matches the `badgesHtml` part.
                // The "discountBadge" usually refers to the image overlay.
                // I will remove the image overlay and the text badges.
                // Leaving the "discountTag" (with SVG) if it exists, or maybe that IS the "Desconto de R$ 10,00".
                // In my previous code: 
                // `badgesHtml` contained `Desconto de ${formatMoney(p.discount_amount)}` (simple border) AND `Frete grátis`.
                // `discountTag` contained the SVG and "Desconto de ...". 
                // The user said "Delete as badges 'Desconto de R$ 10,00' and 'Frete gratis'". This maps 100% to `badgesHtml`.
                // I will remove `badgesHtml`.

                // Discount tag overlay (icon one) - User didn't explicitly ask to remove the icon one, but maybe they did? 
                // "No card de produto delete a 'discountBadge'." -> Image overlay.
                // "Delete as badges 'Desconto de R$ 10,00' e 'Frete grátis'." -> Simple text ones.

                // I'll keep the `discountTag` (SVG one) for now unless I see it matches the text exactly. 
                // The SVG one says "Desconto de ...". The simple one also says "Desconto de ...".
                // Actually in the previous turn, I generated BOTH. 
                // The `discountTag` block has `Desconto de ${formatMoney(saveAmount)}`.
                // The `badgesHtml` block has `Desconto de ${formatMoney(p.discount_amount)}`.
                // The user likely wants to clean up the card. I will remove `badgesHtml` completely.
                // I will also remove `discountBadge` (image overlay).

                let discountTag = '';
                if (p.discount_amount > 0) {
                    discountTag = `
                    <div class="discountTag">
                        <svg width="12" height="10" viewBox="0 0 667 534" fill="none" style="margin-right: 4px;">
                            <path d="M666.666 166.667C640.145 166.667 614.71 177.203 595.956 195.956C577.202 214.71 566.666 240.145 566.666 266.667C566.666 293.189 577.202 318.624 595.956 337.378C614.71 356.131 640.145 366.667 666.666 366.667L666.666 466.667C666.666 484.348 659.642 501.305 647.14 513.808C634.637 526.31 617.681 533.334 600 533.334L466.666 533.334L466.666 470.601C466.666 461.76 463.154 453.281 456.903 447.03C450.652 440.779 442.174 437.267 433.333 437.267C424.492 437.267 416.014 440.779 409.763 447.03C403.512 453.281 400 461.76 400 470.601L400 533.334L66.666 533.334C48.9852 533.334 32.0287 526.31 19.5264 513.808C7.02395 501.305 1.13666e-05 484.348 1.01189e-05 466.667L1.44901e-05 366.666C55.4279 366.576 100.333 321.616 100.333 266.167C100.333 210.718 55.4279 165.758 2.3276e-05 165.668L2.76035e-05 66.667C3.0397e-05 48.9859 7.02397 32.0288 19.5264 19.5264C32.0287 7.02422 48.9852 8.86041e-05 66.666 4.76048e-07L400 1.50465e-05L400 62.7334C400 71.5739 403.512 80.0525 409.763 86.3037C416.014 92.5549 424.492 96.0674 433.333 96.0674C442.174 96.0674 450.652 92.5549 456.903 86.3037C463.154 80.0525 466.666 71.5739 466.666 62.7334L466.666 1.79606e-05L600 2.37888e-05C617.681 0.000113468 634.637 7.02425 647.14 19.5264C659.642 32.0288 666.666 48.9859 666.666 66.667L666.666 166.667ZM466.666 156.867C466.666 148.027 463.154 139.548 456.903 133.297C450.652 127.046 442.174 123.533 433.333 123.533C424.492 123.533 416.014 127.046 409.763 133.297C403.512 139.548 400 148.027 400 156.867L400 219.601C400 228.441 403.512 236.92 409.763 243.171C416.014 249.422 424.493 252.934 433.333 252.934C442.174 252.934 450.652 249.422 456.903 243.171C463.154 236.92 466.666 228.441 466.666 219.601L466.666 156.867ZM466.666 313.733C466.666 304.893 463.154 296.414 456.903 290.163C450.652 283.912 442.174 280.4 433.333 280.4C424.493 280.4 416.014 283.912 409.763 290.163C403.512 296.414 400 304.893 400 313.733L400 376.467C400 385.307 403.512 393.786 409.763 400.037C416.014 406.288 424.492 409.801 433.333 409.801C442.174 409.801 450.652 406.288 456.903 400.037C463.154 393.786 466.666 385.307 466.666 376.467L466.666 313.733Z" fill="#FF2B56"></path>
                        </svg> Desconto de ${formatMoney(p.discount_amount)}
                    </div>`;
                }

                const itemHtml = `
                <a href="/product/${p.id}" class="productCard" style="display: block; text-decoration: none; color: inherit;">
                    <div class="productImage">
                        <img src="${p.image}" alt="${p.title}" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div class="productInfo">
                         <div class="productTitle" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            ${p.title}
                         </div>
                         <div class="productPriceRow">
                             <span class="productPrice">${formatMoney(p.current_price)}</span>
                             ${p.original_price > p.current_price ? `<span class="productOriginalPrice">${formatMoney(p.original_price)}</span>` : ''}
                         </div>
                         ${discountTag}
                         <div class="ratingRow">
                            <svg viewBox="0 0 24 24" fill="#ffce3d"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                            <span>${Number(p.rating_value).toFixed(1)}</span>
                            <span class="ratingSeparator">|</span>
                            <span>${formatSold(p.sold_count)} vendidos</span>
                         </div>
                    </div>
                </a>
                `;
                container.innerHTML += itemHtml;
            });
        }

        // Filtering
        function applyFilter(mode, el) {
            // UI
            document.querySelectorAll('.filter-item').forEach(d => d.classList.remove('active'));
            el.classList.add('active');

            let filtered = [...productsData];
            if (mode === 'recommended') {
                filtered.sort((a, b) => a.original_index - b.original_index);
            } else if (mode === 'bestsellers') {
                filtered.sort((a, b) => b.sold_count - a.sold_count);
            } else if (mode === 'newest') {
                // Config doesn't have create date, so basically reverse order of config json
                // Assuming newer products added to end of array in config?
                // Request says "Listar produtos do config.json na ordem de baixo pra cima"
                filtered.sort((a, b) => b.original_index - a.original_index);
            } else if (mode === 'price_asc') {
                filtered.sort((a, b) => a.current_price - b.current_price);
            }

            renderProducts(filtered);
        }

        // Follow Toggle
        function toggleFollow() {
            const btn = document.getElementById('followBtn');
            if (btn.innerText === 'Seguir') {
                btn.innerText = 'Seguindo';
                btn.classList.add('following');
            } else {
                btn.innerText = 'Seguir';
                btn.classList.remove('following');
            }
        }

        // Initial Render
        renderProducts(productsData);

        // --- Search JS Logic Repeated (Simplified) ---
        const searchInput = document.getElementById('productSearchInput');
        const searchResults = document.getElementById('productSearchResults');
        const allSearchProducts = productsData; // Use the main products data

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
                                <div style="font-size:12px; font-weight:700; color:#fe2c55;">${formatMoney(p.current_price)}</div>
                            </div>
                        </a>`;
                    });
                    searchResults.innerHTML = html;
                    searchResults.style.display = 'block';
                } else {
                    searchResults.style.display = 'none';
                }
            });
            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>