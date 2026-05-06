// ── STATE ──
let products    = [];
let cart        = [];
let messages    = [];
let currentUser = null;

// ── TOAST ──
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
}

// ── MODAL HELPERS ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openLogin()  { openModal('loginModal'); }
function openAdd()    {
    if (!currentUser) { showToast('Pro přidání se přihlaste.'); openLogin(); return; }
    openModal('addModal');
}
function openChat()   { openModal('chatModal'); loadChat(); }

// Close modal on backdrop click
document.querySelectorAll('.modal').forEach(function(m) {
    m.addEventListener('click', function(e) { if (e.target === m) closeModal(m.id); });
});

// ── AUTH ──
function login() {
    var email    = document.getElementById('loginEmail').value.trim();
    var password = document.getElementById('loginPassword').value;

    if (!email || !password) { showToast('Vyplňte e-mail a heslo.'); return; }

    fetch('api.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: password })
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            currentUser = data;
            showLoggedInButtons();
            closeModal('loginModal');
            showToast('Vítejte zpět, ' + data.jmeno);
            loadNotifications();
        })
        .catch(function() { showToast('Chyba připojení.'); });
}

function register() {
    var jmeno    = document.getElementById('regJmeno').value.trim();
    var email    = document.getElementById('regEmail').value.trim();
    var password = document.getElementById('regPassword').value;

    if (!jmeno || !email || !password) { showToast('Vyplňte všechna pole.'); return; }

    fetch('api.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ jmeno: jmeno, email: email, password: password })
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            currentUser = data;
            showLoggedInButtons();
            closeModal('registerModal');
            showToast('Účet vytvořen! Vítejte, ' + data.jmeno);
            loadNotifications();
        })
        .catch(function() { showToast('Chyba připojení.'); });
}

function logout() {
    fetch('api.php?action=logout', { method: 'POST' })
        .then(function() {
            currentUser = null;
            cart = [];
            updateCart();
            hideLoggedInButtons();
            showToast('Odhlášeno.');
        });
}

function switchToRegister() {
    closeModal('loginModal');
    openModal('registerModal');
}

function switchToLogin() {
    closeModal('registerModal');
    openModal('loginModal');
}

function showLoggedInButtons() {
    document.getElementById('notifBtn').style.display = '';
    document.getElementById('myOrdersBtn').style.display = '';
    document.getElementById('myListingsBtn').style.display = '';
    document.getElementById('loginBtn').textContent = '👤 ' + currentUser.jmeno;
    document.getElementById('loginBtn').onclick = logout;
}
function hideLoggedInButtons() {
    document.getElementById('notifBtn').style.display = 'none';
    document.getElementById('myOrdersBtn').style.display = 'none';
    document.getElementById('myListingsBtn').style.display = 'none';
    document.getElementById('notif-count').style.display = 'none';
    document.getElementById('loginBtn').textContent = 'Přihlásit';
    document.getElementById('loginBtn').onclick = openLogin;
}

// ── PRODUCTS ──
function loadProducts(q) {
    var url = 'api.php?action=products';
    if (q) url += '&q=' + encodeURIComponent(q);

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            products = data;
            renderProducts(data);
        })
        .catch(function() { showToast('Nepodařilo se načíst produkty.'); });
}

var conditionLabels = { 'novy': 'Nový', 'pouzity': 'Použitý', 'poskozeny': 'Poškozený' };

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function createCard(p) {
    var imgHtml = p.img
        ? '<img src="' + p.img + '" alt="' + escapeHtml(p.title) + '" loading="lazy">'
        : '<div class="card-placeholder">📚</div>';

    var desc = p.description
        ? '<p class="card-desc">' + escapeHtml(p.description).slice(0, 80) + (p.description.length > 80 ? '…' : '') + '</p>'
        : '';

    var meta = '';
    if (p.brand) meta += '<span class="card-meta">' + escapeHtml(p.brand) + '</span> ';
    if (p.condition_val) meta += '<span class="card-meta card-condition">' + (conditionLabels[p.condition_val] || p.condition_val) + '</span>';
    if (p.isbn) meta += '<span class="card-meta">ISBN: ' + escapeHtml(p.isbn) + '</span>';

    return '<div class="card" data-id="' + p.id + '">' +
        '<div class="card-img-wrap">' + imgHtml + '</div>' +
        '<div class="card-body">' +
        '<div class="card-title">' + escapeHtml(p.title) + '</div>' +
        (meta ? '<div style="margin-bottom:4px;">' + meta + '</div>' : '') +
        desc +
        '</div>' +
        '<div class="card-footer">' +
        '<span class="card-price">' + Number(p.price).toLocaleString('cs') + ' Kč</span>' +
        '<button class="btn btn-amber" style="margin:0;padding:7px 14px;font-size:.82rem;" ' +
        'onclick="addToCart(' + p.id + ')">Do košíku</button>' +
        '</div>' +
        '</div>';
}

function renderProducts(list) {
    var container = document.getElementById('products');
    var source    = list !== undefined ? list : products;

    if (source.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">📚</div><p>Zatím žádné knihy</p></div>';
        return;
    }
    container.innerHTML = source.map(createCard).join('');
}

function filterProducts() {
    var q = document.getElementById('search').value.trim();
    loadProducts(q);
}

// ── CART ──
function addToCart(id) {
    var item = products.find(function(p) { return p.id == id; });
    if (!item) return;
    cart.push(item);
    updateCart();
    showToast('"' + item.title + '" přidáno do košíku');
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    updateCart();
}

function updateCart() {
    document.getElementById('cart-count').textContent = cart.length;

    var list = document.getElementById('cart-items');
    if (cart.length === 0) {
        list.innerHTML = '<li style="justify-content:center;color:var(--ink);padding:32px 0;">Košík je prázdný</li>';
    } else {
        list.innerHTML = cart.map(function(item, i) {
            return '<li>' +
                '<span class="cart-item-title">' + escapeHtml(item.title) + '</span>' +
                '<span class="cart-item-price">' + Number(item.price).toLocaleString('cs') + ' Kč</span>' +
                '<button class="cart-remove" onclick="removeFromCart(' + i + ')" title="Odebrat">✕</button>' +
                '</li>';
        }).join('');
    }

    var total = cart.reduce(function(sum, i) { return sum + Number(i.price); }, 0);
    document.getElementById('cart-total').textContent = total.toLocaleString('cs') + ' Kč';
}

function toggleCart() {
    document.getElementById('cart').classList.toggle('open');
    document.getElementById('cartOverlay').classList.toggle('open');
}

function checkout() {
    if (cart.length === 0) { showToast('Košík je prázdný.'); return; }
    if (!currentUser)      { showToast('Pro objednávku se přihlaste.'); openLogin(); return; }
    toggleCart();
    openModal('shippingModal');
}

function submitCheckout() {
    var jmeno    = document.getElementById('shipJmeno').value.trim();
    var prijmeni = document.getElementById('shipPrijmeni').value.trim();
    var adresa   = document.getElementById('shipAdresa').value.trim();
    var telefon  = document.getElementById('shipTelefon').value.trim();

    if (!jmeno || !prijmeni || !adresa) {
        showToast('Vyplňte jméno, příjmení a adresu.');
        return;
    }

    fetch('api.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            items: cart,
            jmeno: jmeno,
            prijmeni: prijmeni,
            adresa: adresa,
            telefon: telefon
        })
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            showToast('Objednávka odeslána! ✓');
            cart = [];
            updateCart();
            closeModal('shippingModal');
            ['shipJmeno','shipPrijmeni','shipAdresa','shipTelefon'].forEach(function(id) {
                document.getElementById(id).value = '';
            });
            loadProducts();
            loadNotifications();
        })
        .catch(function() { showToast('Chyba při odesílání objednávky.'); });
}

// ── ADD ITEM ──
function addItem() {
    var title = document.getElementById('title').value.trim();
    var price = document.getElementById('price').value.trim();
    var desc  = document.getElementById('desc').value.trim();
    var brand = document.getElementById('brand').value.trim();
    var isbn  = document.getElementById('isbn').value.trim();
    var stav  = document.getElementById('stav').value;
    var file  = document.getElementById('image').files[0];

    if (!title || !price) { showToast('Vyplňte název a cenu.'); return; }

    var formData = new FormData();
    formData.append('title', title);
    formData.append('price', price);
    formData.append('desc',  desc);
    formData.append('brand', brand);
    formData.append('isbn',  isbn);
    formData.append('stav',  stav);
    if (file) formData.append('image', file);

    fetch('api.php?action=add', {
        method: 'POST',
        body: formData
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            closeModal('addModal');
            ['title', 'price', 'desc', 'brand', 'isbn'].forEach(function(id) {
                document.getElementById(id).value = '';
            });
            document.getElementById('stav').value = 'pouzity';
            document.getElementById('image').value = '';
            showToast('Kniha přidána!');
            loadProducts();
        })
        .catch(function() { showToast('Chyba při přidávání knihy.'); });
}

// ── MY ORDERS ──
function openMyOrders() {
    if (!currentUser) { showToast('Přihlaste se.'); openLogin(); return; }
    fetch('api.php?action=my_orders')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var stavLabels = {
                'cekajici':'Čeká na zpracování','zaplaceno':'Zaplaceno',
                'odeslano':'Odesláno','dokonceno':'Dokončeno','zruseno':'Zrušeno'
            };
            var html = '';
            if (!data.length) {
                html = '<p style="color:var(--ink);text-align:center;padding:32px 0;">Žádné objednávky.</p>';
            } else {
                data.forEach(function(o) {
                    var rateBtn = '';
                    if (!parseInt(o.already_rated)) {
                        rateBtn = '<button class="btn btn-amber" style="padding:5px 12px;font-size:.8rem;" ' +
                            'onclick="openRateModal(' + o.id + ',' + o.seller_id + ')">Ohodnotit prodejce</button>';
                    } else {
                        rateBtn = '<span style="font-size:.8rem;color:var(--ink);">✓ Hodnoceno</span>';
                    }
                    html += '<div class="order-row">' +
                        (o.img ? '<img src="' + o.img + '" style="width:52px;height:52px;object-fit:cover;border-radius:8px;">' : '<div style="width:52px;height:52px;background:var(--cream);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">📚</div>') +
                        '<div style="flex:1;">' +
                        '<div style="font-weight:600;">' + escapeHtml(o.title) + '</div>' +
                        '<div style="font-size:.82rem;color:var(--ink);">Prodejce: ' + escapeHtml(o.seller_name) + '</div>' +
                        '<div style="font-size:.82rem;color:var(--ink);">Stav: <b>' + (stavLabels[o.stav] || o.stav) + '</b></div>' +
                        '<div style="font-size:.78rem;color:#aaa;">' + o.vytvoreno + '</div>' +
                        '</div>' +
                        '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">' +
                        '<span style="font-weight:700;">' + Number(o.price).toLocaleString('cs') + ' Kč</span>' +
                        rateBtn +
                        '</div>' +
                        '</div>';
                });
            }
            document.getElementById('myOrdersList').innerHTML = html;
            openModal('myOrdersModal');
        })
        .catch(function() { showToast('Nepodařilo se načíst objednávky.'); });
}

// ── MY LISTINGS ──
function openMyListings() {
    if (!currentUser) { showToast('Přihlaste se.'); openLogin(); return; }
    fetch('api.php?action=my_listings')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var stavLabels = { 'aktivni':'Aktivní', 'prodano':'Prodáno', 'zruseno':'Zrušeno' };
            var html = '';
            if (!data.length) {
                html = '<p style="color:var(--ink);text-align:center;padding:32px 0;">Žádné inzeráty.</p>';
            } else {
                data.forEach(function(l) {
                    var badge = l.stav === 'prodano'
                        ? '<span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:999px;font-size:.75rem;">Prodáno</span>'
                        : l.stav === 'zruseno'
                            ? '<span style="background:#e57373;color:#fff;padding:2px 8px;border-radius:999px;font-size:.75rem;">Zrušeno</span>'
                            : '<span style="background:var(--amber);color:#fff;padding:2px 8px;border-radius:999px;font-size:.75rem;">Aktivní</span>';
                    html += '<div class="order-row">' +
                        (l.img ? '<img src="' + l.img + '" style="width:52px;height:52px;object-fit:cover;border-radius:8px;">' : '<div style="width:52px;height:52px;background:var(--cream);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">📚</div>') +
                        '<div style="flex:1;">' +
                        '<div style="font-weight:600;">' + escapeHtml(l.title) + '</div>' +
                        '<div style="font-size:.78rem;color:#aaa;">' + l.datum + '</div>' +
                        '</div>' +
                        '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">' +
                        '<span style="font-weight:700;">' + Number(l.price).toLocaleString('cs') + ' Kč</span>' +
                        badge +
                        '</div>' +
                        '</div>';
                });
            }
            document.getElementById('myListingsList').innerHTML = html;
            openModal('myListingsModal');
        })
        .catch(function() { showToast('Nepodařilo se načíst inzeráty.'); });
}

// ── NOTIFICATIONS ──
function loadNotifications() {
    if (!currentUser) return;
    fetch('api.php?action=notifications')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var unread = data.filter(function(n) { return !parseInt(n.precteno); }).length;
            var badge = document.getElementById('notif-count');
            if (unread > 0) {
                badge.textContent = unread;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }

            var typIcons = { 'prodej': '💰', 'nakup': '📦', 'hodnoceni': '⭐' };
            var html = '';
            if (!data.length) {
                html = '<p style="color:var(--ink);text-align:center;padding:32px 0;">Žádná oznámení.</p>';
            } else {
                data.forEach(function(n) {
                    html += '<div class="notif-row' + (parseInt(n.precteno) ? '' : ' notif-unread') + '">' +
                        '<span style="font-size:1.3rem;">' + (typIcons[n.typ] || '🔔') + '</span>' +
                        '<div style="flex:1;">' +
                        '<div style="font-size:.88rem;">' + n.text + '</div>' +
                        '<div style="font-size:.75rem;color:#aaa;">' + n.vytvoreno + '</div>' +
                        '</div>' +
                        '</div>';
                });
            }
            document.getElementById('notifList').innerHTML = html;
        });
}

function openNotifications() {
    loadNotifications();
    openModal('notificationsModal');
    fetch('api.php?action=notifications_read', { method: 'POST' })
        .then(function() {
            document.getElementById('notif-count').style.display = 'none';
        });
}

// ── RATINGS ──
function openRateModal(orderId, sellerId) {
    document.getElementById('rateOrderId').value  = orderId;
    document.getElementById('rateSellerId').value = sellerId;
    document.getElementById('rateValue').value    = 0;
    document.getElementById('rateComment').value  = '';
    document.querySelectorAll('#starPicker span').forEach(function(s) { s.textContent = '☆'; });
    closeModal('myOrdersModal');
    openModal('rateModal');
}

document.getElementById('starPicker').addEventListener('click', function(e) {
    if (e.target.tagName !== 'SPAN') return;
    var val = parseInt(e.target.getAttribute('data-v'));
    document.getElementById('rateValue').value = val;
    document.querySelectorAll('#starPicker span').forEach(function(s) {
        s.textContent = parseInt(s.getAttribute('data-v')) <= val ? '★' : '☆';
    });
});

function submitRating() {
    var orderId   = parseInt(document.getElementById('rateOrderId').value);
    var sellerId  = parseInt(document.getElementById('rateSellerId').value);
    var rating    = parseInt(document.getElementById('rateValue').value);
    var comment   = document.getElementById('rateComment').value.trim();

    if (rating < 1 || rating > 5) { showToast('Vyberte hodnocení (1–5 hvězdiček).'); return; }

    fetch('api.php?action=rate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, seller_id: sellerId, rating: rating, comment: comment })
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            showToast('Hodnocení odesláno! ⭐');
            closeModal('rateModal');
        })
        .catch(function() { showToast('Chyba při odesílání hodnocení.'); });
}

// ── CHAT ──
function loadChat() {
    fetch('api.php?action=chat')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            messages = data;
            renderChat();
        })
        .catch(function() { renderChat(); });
}

function sendMessage() {
    var input = document.getElementById('chatInput');
    var text  = input.value.trim();
    if (!text) return;

    fetch('api.php?action=chat_send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text: text })
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            messages.push({ user: data.user, text: data.text, time: data.time });
            input.value = '';
            renderChat();
        })
        .catch(function() { showToast('Zprávu se nepodařilo odeslat.'); });
}

function renderChat() {
    var box = document.getElementById('chatBox');
    box.innerHTML = messages.length === 0
        ? '<p style="color:var(--ink);text-align:center;margin-top:80px;font-size:.9rem;">Zatím žádné zprávy.</p>'
        : messages.map(function(m) {
            return '<div class="chat-msg">' +
                '<b>' + escapeHtml(m.user) + '</b>' +
                '<span style="color:var(--ink);font-size:.76rem;margin-left:6px;">' + (m.time || '') + '</span><br>' +
                escapeHtml(m.text) +
                '</div>';
        }).join('');
    box.scrollTop = box.scrollHeight;
}

// ── INIT ──
fetch('api.php?action=me')
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data) {
            currentUser = data;
            showLoggedInButtons();
            loadNotifications();
        }
    });

loadProducts();
updateCart();