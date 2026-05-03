<!doctype html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Bazar</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="logo">WINDED</div>
    <div class="nav-center">
        <input type="text" id="search" placeholder="Hledat učebnice…" oninput="filterProducts()">
    </div>
    <div class="nav-actions">
        <button class="btn btn-ghost" onclick="openChat()">Chat</button>
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
            Nemáte účet? Zadejte nové údaje pro registraci.
        </p>
        <div class="modal-actions">
            <button class="btn btn-ghost" style="color:var(--ink);" onclick="closeModal('loginModal')">Zrušit</button>
            <button class="btn btn-amber" onclick="login()">Přihlásit</button>
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