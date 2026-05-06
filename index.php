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
    <div class="nav-center">
        <span class="search-icon">🔍</span>
        <input type="text" id="search" placeholder="Hledat učebnice…" oninput="filterProducts()">
    </div>
    <div class="nav-actions">
        <button class="btn btn-ghost notif-btn" id="notifBtn" onclick="openNotifications()" style="display:none;">
            🔔 <span class="notif-badge" id="notif-count" style="display:none;">0</span>
        </button>
        <button class="btn btn-ghost" onclick="openChat()">Chat</button>
        <button class="btn btn-ghost" onclick="openMyOrders()" id="myOrdersBtn" style="display:none;">Moje objednávky</button>
        <button class="btn btn-ghost" onclick="openMyListings()" id="myListingsBtn" style="display:none;">Moje inzeráty</button>
        <button class="btn btn-ghost" onclick="openLogin()" id="loginBtn">Přihlásit</button>
        <button class="btn btn-outline" onclick="toggleCart()">
            Košík <span class="cart-badge" id="cart-count">0</span>
        </button>
        <button class="btn btn-amber" onclick="openAdd()">+ Přidat</button>
    </div>
</header>

<!-- hero -->
<section class="hero">
    <h1>Učebnice levně</h1>
    <p>Nakupuj a prodávej staré učebnice přímo mezi studenty</p>
    <div class="hero-actions">
        <button class="btn btn-amber" onclick="openAdd()">
            Přidat učebnici
        </button>
        <button class="btn btn-outline" onclick="document.getElementById('products').scrollIntoView({behavior:'smooth'})">
            Procházet nabídku ↓
        </button>
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
        <button class="btn btn-amber" onclick="checkout()">
            Objednat
        </button>
    </div>
</div>

<!-- login modal -->
<div class="modal" id="loginModal">
    <div class="modal-content">
        <h2>Přihlásit se</h2>
        <div class="form-group">
            <label>E-mail</label>
            <input type="email" id="loginEmail" placeholder="vas@email.cz">
        </div>
        <div class="form-group">
            <label>Heslo</label>
            <input type="password" id="loginPassword" placeholder="••••••••">
        </div>
        <p style="font-size:.82rem;color:var(--ink);margin-top:8px;">
            Nemáte účet? <a href="#" onclick="switchToRegister(); return false;" style="color:var(--amber);text-decoration:underline;">Zaregistrujte se</a>
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
        <h2>Registrace</h2>
        <div class="form-group">
            <label>Jméno</label>
            <input type="text" id="regJmeno" placeholder="Vaše jméno">
        </div>
        <div class="form-group">
            <label>E-mail</label>
            <input type="email" id="regEmail" placeholder="vas@email.cz">
        </div>
        <div class="form-group">
            <label>Heslo</label>
            <input type="password" id="regPassword" placeholder="••••••••">
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

<!-- add modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
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
        <h2>Doručovací údaje</h2>
        <div class="form-group">
            <label>Jméno</label>
            <input type="text" id="shipJmeno" placeholder="Jan">
        </div>
        <div class="form-group">
            <label>Příjmení</label>
            <input type="text" id="shipPrijmeni" placeholder="Novák">
        </div>
        <div class="form-group">
            <label>Adresa</label>
            <textarea id="shipAdresa" placeholder="Ulice 123, 110 00 Praha"></textarea>
        </div>
        <div class="form-group">
            <label>Telefon</label>
            <input type="text" id="shipTelefon" placeholder="+420 123 456 789">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('shippingModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="submitCheckout()">Dokončit objednávku</button>
        </div>
    </div>
</div>

<!-- my orders modal -->
<div class="modal" id="myOrdersModal">
    <div class="modal-content" style="max-width: 600px; max-height: 80vh; display: flex; flex-direction: column;">
        <h2>Moje objednávky</h2>
        <div id="myOrdersList" style="overflow-y: auto; flex: 1; margin-top: 10px;"></div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('myOrdersModal')">Zavřít</button>
        </div>
    </div>
</div>

<!-- my listings modal -->
<div class="modal" id="myListingsModal">
    <div class="modal-content" style="max-width: 600px; max-height: 80vh; display: flex; flex-direction: column;">
        <h2>Moje inzeráty</h2>
        <div id="myListingsList" style="overflow-y: auto; flex: 1; margin-top: 10px;"></div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('myListingsModal')">Zavřít</button>
        </div>
    </div>
</div>

<!-- notifications modal -->
<div class="modal" id="notificationsModal">
    <div class="modal-content" style="max-width: 500px; max-height: 80vh; display: flex; flex-direction: column;">
        <h2>Oznámení</h2>
        <div id="notifList" style="overflow-y: auto; flex: 1; margin-top: 10px;"></div>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('notificationsModal')">Zavřít</button>
        </div>
    </div>
</div>

<!-- rate modal -->
<div class="modal" id="rateModal">
    <div class="modal-content">
        <h2>Ohodnotit prodejce</h2>
        <input type="hidden" id="rateOrderId">
        <input type="hidden" id="rateSellerId">
        <input type="hidden" id="rateValue" value="0">
        <div class="form-group">
            <label>Hodnocení</label>
            <div id="starPicker" style="font-size: 2rem; cursor: pointer; user-select: none;">
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

<!-- chat modal -->
<div class="modal" id="chatModal">
    <div class="modal-content">
        <h2>Komunita</h2>
        <div id="chatBox"></div>
        <div class="chat-input-row">
            <input type="text" id="chatInput" placeholder="Napište zprávu…" onkeydown="if(event.key==='Enter')sendMessage()">
            <button class="btn btn-amber" onclick="sendMessage()">Odeslat</button>
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