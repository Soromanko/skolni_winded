<?php header("Content-Type: text/html; charset=utf-8"); ?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Bazar učebnic - WINDED</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="logo">WINDED</div>
    <div class="nav-actions">
        <button class="btn btn-ghost notif-btn" id="notifBtn" onclick="openNotifications()" style="display:none;">
            🔔 <span class="notif-badge" id="notif-count" style="display:none;">0</span>
        </button>

        <button class="btn btn-ghost" onclick="openLogin()" id="loginBtn">Přihlásit</button>

        <div class="profile-drop-wrap" id="profileDropWrap" style="display:none;">
            <button class="btn btn-ghost profile-btn" id="profileBtn" onclick="toggleProfileMenu()">
                👤 <span id="profileName"></span> <span class="profile-caret">▾</span>
            </button>
            <div class="profile-menu" id="profileMenu">
                <button class="profile-menu-item" onclick="openMyOrders(); closeProfileMenu();">📦 Moje objednávky</button>
                <button class="profile-menu-item" onclick="openMyListings(); closeProfileMenu();">📋 Moje inzeráty</button>
                <div class="profile-menu-divider"></div>
                <button class="profile-menu-item profile-menu-logout" onclick="logout(); closeProfileMenu();">🚪 Odhlásit se</button>
            </div>
        </div>

        <!-- Košík ikona ve stylu Alza -->
        <button class="cart-icon-btn" onclick="toggleCart()" title="Košík">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <span class="cart-icon-badge" id="cart-count" style="display:none;">0</span>
        </button>

        <button class="btn btn-amber" onclick="openAdd()">+ Přidat</button>
    </div>
</header>

<!-- hero -->
<section class="hero">
    <h1>Učebnice levně</h1>
    <p>Nakupuj a prodávej staré učebnice přímo mezi studenty</p>
    <div class="hero-actions">
        <button class="btn btn-amber" onclick="openAdd()">Přidat učebnici</button>
    </div>

    <div class="hero-search-wrap">
        <span class="hero-search-icon">🔍</span>
        <input type="text" id="search" placeholder="Hledat název, autora, ISBN…" oninput="filterProducts()">
    </div>

    <div class="hero-filters">
        <div class="filter-group">
            <span class="filter-label">Stav:</span>
            <button class="filter-chip active" onclick="toggleStavFilter('', this)">Vše</button>
            <button class="filter-chip" onclick="toggleStavFilter('novy', this)">Nový</button>
            <button class="filter-chip" onclick="toggleStavFilter('pouzity', this)">Použitý</button>
            <button class="filter-chip" onclick="toggleStavFilter('poskozeny', this)">Poškozený</button>
        </div>
        <div class="filter-group">
            <span class="filter-label">Řadit:</span>
            <button class="filter-chip active" onclick="setSortFilter('newest', this)">Nejnovější</button>
            <button class="filter-chip" onclick="setSortFilter('price_asc', this)">Nejlevnější</button>
            <button class="filter-chip" onclick="setSortFilter('price_desc', this)">Nejdražší</button>
        </div>
    </div>
</section>

<!-- products -->
<div class="section-label">Nabídka knih</div>
<section id="products"></section>

<!-- cart overlay -->
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>

<!-- cart -->
<div id="cart">
    <div class="cart-header">
        <h2>Košík</h2>
        <button class="cart-close" onclick="toggleCart()">✕</button>
    </div>
    <ul id="cart-items"></ul>
    <div class="cart-footer">
        <div class="cart-total">
            <span>Celkem</span>
            <span id="cart-total">0 Kč</span>
        </div>
        <button class="btn btn-amber" onclick="checkout()">Objednat</button>
    </div>
</div>

<!-- ===================== MODALY ===================== -->

<!-- Detail nabídky -->
<div class="modal" id="productDetailModal">
    <div class="modal-content modal-content-wide">
        <button class="modal-x-close" onclick="closeModal('productDetailModal')" title="Zavřít">✕</button>
        <div id="productDetailBody"></div>
    </div>
</div>

<!-- login modal -->
<div class="modal" id="loginModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('loginModal')" title="Zavřít">✕</button>
        <h2>Přihlásit se</h2>
        <div class="form-group">
            <label>E-mail</label>
            <input type="email" id="loginEmail" placeholder="vas@email.cz" autocomplete="username">
        </div>
        <div class="form-group">
            <label>Heslo</label>
            <input type="password" id="loginPassword" placeholder="••••••••" autocomplete="current-password">
        </div>
        <p style="font-size:.82rem;color:var(--ink);margin-top:8px;">
            Nemáte účet? <a href="#" onclick="switchToRegister(); return false;" style="color:var(--amber);text-decoration:underline;">Zaregistrujte se</a>
        </p>
        <p style="font-size:.82rem;color:var(--ink);margin-top:4px;">
            <a href="#" onclick="openForgotPassword(); return false;" style="color:var(--amber);text-decoration:underline;">Zapomněli jste heslo?</a>
        </p>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('loginModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="login()">Přihlásit</button>
        </div>
    </div>
</div>

<!-- register modal -->
<div class="modal" id="registerModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('registerModal')" title="Zavřít">✕</button>
        <h2>Registrace</h2>
        <div class="form-group">
            <label>Jméno</label>
            <input type="text" id="regJmeno" placeholder="Vaše jméno" autocomplete="given-name">
        </div>
        <div class="form-group">
            <label>E-mail</label>
            <input type="email" id="regEmail" placeholder="vas@email.cz" autocomplete="username">
        </div>
        <div class="form-group">
            <label>Heslo</label>
            <input type="password" id="regPassword" placeholder="••••••••" autocomplete="new-password">
        </div>
        <p style="font-size:.82rem;color:var(--ink);margin-top:8px;">
            Máte účet? <a href="#" onclick="switchToLogin(); return false;" style="color:var(--amber);text-decoration:underline;">Přihlaste se</a>
        </p>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('registerModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="register()">Registrovat</button>
        </div>
    </div>
</div>

<!-- forgot password modal -->
<div class="modal" id="forgotPasswordModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('forgotPasswordModal')" title="Zavřít">✕</button>
        <h2>Zapomenuté heslo</h2>
        <p style="font-size:.88rem;color:var(--ink);margin-bottom:16px;">
            Zadejte svůj e-mail a pošleme vám odkaz pro resetování hesla.
        </p>
        <div class="form-group">
            <label>E-mail</label>
            <input type="email" id="forgotEmail" placeholder="vas@email.cz" autocomplete="username">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('forgotPasswordModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="sendForgotPassword()">Odeslat odkaz</button>
        </div>
    </div>
</div>

<!-- reset password modal -->
<div class="modal" id="resetPasswordModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('resetPasswordModal')" title="Zavřít">✕</button>
        <h2>Nové heslo</h2>
        <input type="hidden" id="resetToken">
        <div class="form-group">
            <label>Nové heslo</label>
            <input type="password" id="resetPassword" placeholder="Min. 6 znaků" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>Potvrdit heslo</label>
            <input type="password" id="resetPassword2" placeholder="Zopakujte heslo" autocomplete="new-password">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('resetPasswordModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="submitResetPassword()">Uložit heslo</button>
        </div>
    </div>
</div>

<!-- add modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('addModal')" title="Zavřít">✕</button>
        <h2>Přidat učebnici</h2>
        <div class="form-group">
            <label>Název</label>
            <input type="text" id="title" placeholder="Název knihy">
        </div>
        <div class="form-group">
            <label>Cena (Kč)</label>
            <input type="number" id="price" placeholder="150" min="0">
        </div>
        <div class="form-group">
            <label>Autor / Nakladatelství</label>
            <input type="text" id="brand" placeholder="např. Nakladatelství Fraus">
        </div>
        <div class="form-group">
            <label>ISBN</label>
            <input type="text" id="isbn" placeholder="978-80-...">
        </div>
        <div class="form-group">
            <label>Stav</label>
            <select id="stav">
                <option value="novy">Nový</option>
                <option value="pouzity" selected>Použitý</option>
                <option value="poskozeny">Poškozený</option>
            </select>
        </div>
        <div class="form-group">
            <label>Popis</label>
            <textarea id="desc" placeholder="Stav, vydání, poznámky…"></textarea>
        </div>
        <div class="form-group">
            <label>Fotografie</label>
            <input type="file" id="image" accept="image/*" style="background:none;border:none;padding:0;font-size:.88rem;">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('addModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="addItem()">Přidat</button>
        </div>
    </div>
</div>

<!-- shipping modal -->
<div class="modal" id="shippingModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('shippingModal')" title="Zavřít">✕</button>
        <h2>Doručovací údaje</h2>
        <div class="form-group">
            <label>Jméno</label>
            <input type="text" id="shipJmeno" placeholder="Jan" autocomplete="given-name">
        </div>
        <div class="form-group">
            <label>Příjmení</label>
            <input type="text" id="shipPrijmeni" placeholder="Novák" autocomplete="family-name">
        </div>
        <div class="form-group">
            <label>Adresa</label>
            <textarea id="shipAdresa" placeholder="Ulice 123, 110 00 Praha" autocomplete="street-address"></textarea>
        </div>
        <div class="form-group">
            <label>Telefon</label>
            <input type="tel" id="shipTelefon" placeholder="+420 123 456 789" autocomplete="tel">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('shippingModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="submitCheckout()">Dokončit objednávku</button>
        </div>
    </div>
</div>

<!-- my orders modal -->
<div class="modal" id="myOrdersModal">
    <div class="modal-content" style="max-width:600px;max-height:80vh;display:flex;flex-direction:column;">
        <button class="modal-x-close" onclick="closeModal('myOrdersModal')" title="Zavřít">✕</button>
        <h2>Moje objednávky</h2>
        <div id="myOrdersList" style="overflow-y:auto;flex:1;margin-top:10px;"></div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('myOrdersModal')">Zavřít</button>
        </div>
    </div>
</div>

<!-- my listings modal -->
<div class="modal" id="myListingsModal">
    <div class="modal-content" style="max-width:600px;max-height:80vh;display:flex;flex-direction:column;">
        <button class="modal-x-close" onclick="closeModal('myListingsModal')" title="Zavřít">✕</button>
        <h2>Moje inzeráty</h2>
        <div id="myListingsList" style="overflow-y:auto;flex:1;margin-top:10px;"></div>
        <div class="modal-actions" style="justify-content:space-between;">
            <button class="btn btn-amber" onclick="closeModal('myListingsModal'); openAdd();">+ Přidat inzerát</button>
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('myListingsModal')">Zavřít</button>
        </div>
    </div>
</div>

<!-- notifications modal -->
<div class="modal" id="notificationsModal">
    <div class="modal-content" style="max-width:500px;max-height:80vh;display:flex;flex-direction:column;">
        <button class="modal-x-close" onclick="closeModal('notificationsModal')" title="Zavřít">✕</button>
        <h2>Oznámení</h2>
        <div id="notifList" style="overflow-y:auto;flex:1;margin-top:10px;"></div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('notificationsModal')">Zavřít</button>
        </div>
    </div>
</div>

<!-- rate modal -->
<div class="modal" id="rateModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('rateModal')" title="Zavřít">✕</button>
        <h2>Ohodnotit prodejce</h2>
        <input type="hidden" id="rateOrderId">
        <input type="hidden" id="rateSellerId">
        <input type="hidden" id="rateValue" value="0">
        <div class="form-group">
            <label>Hodnocení</label>
            <div id="starPicker" style="font-size:2rem;cursor:pointer;user-select:none;">
                <span data-v="1">☆</span><span data-v="2">☆</span><span data-v="3">☆</span><span data-v="4">☆</span><span data-v="5">☆</span>
            </div>
        </div>
        <div class="form-group">
            <label>Komentář (volitelné)</label>
            <textarea id="rateComment" placeholder="Jak jste byli spokojeni?"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('rateModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="submitRating()">Odeslat hodnocení</button>
        </div>
    </div>
</div>

<!-- chat modal (soukromý) -->
<div class="modal" id="chatModal">
    <div class="modal-content">
        <button class="modal-x-close" onclick="closeModal('chatModal')" title="Zavřít">✕</button>
        <h2 id="chatModalTitle">Chat s prodejcem</h2>
        <div id="chatBox"></div>
        <div class="chat-input-row">
            <input type="text" id="chatInput" placeholder="Napište zprávu…" onkeydown="if(event.key==='Enter')sendMessage()">
            <button class="btn btn-amber" style="padding:10px 18px;" onclick="sendMessage()">Odeslat</button>
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('chatModal')">Zavřít</button>
        </div>
    </div>
</div>

<!-- toast -->
<div id="toast"></div>

<script src="index.js"></script>
</body>
</html>