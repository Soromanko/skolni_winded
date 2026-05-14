// ── STATE ──
let products    = [];
let cart        = [];
let messages    = [];
let currentUser = null;

let activeStavFilters = new Set();
let currentSort = 'newest';

// Chat state
let chatNabidkaId  = null;
let chatDruhyId    = null;

// ── TOAST ──
function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 2800);
}

// ── MODAL HELPERS ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── PROFILE DROPDOWN ──
function toggleProfileMenu() {
    document.getElementById('profileDropWrap').classList.toggle('open');
}
function closeProfileMenu() {
    var wrap = document.getElementById('profileDropWrap');
    if (wrap) wrap.classList.remove('open');
}
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('profileDropWrap');
    if (wrap && !wrap.contains(e.target)) closeProfileMenu();
});

// ── AUTH MODALS ──
function openLogin() {
    document.getElementById('loginEmail').value    = '';
    document.getElementById('loginPassword').value = '';
    openModal('loginModal');
}
function openAdd() {
    if (!currentUser) { showToast('Pro přidání se přihlaste.'); openLogin(); return; }
    openModal('addModal');
}
function openForgotPassword() {
    closeModal('loginModal');
    document.getElementById('forgotEmail').value = '';
    openModal('forgotPasswordModal');
}

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
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            currentUser = data;
            showLoggedInButtons();
            closeModal('loginModal');
            showToast('Vítejte zpět, ' + data.jmeno + '!');
            loadNotifications();
            renderProducts(products); // překreslit karty kvůli chat tlačítkům
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
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            currentUser = data;
            showLoggedInButtons();
            closeModal('registerModal');
            showToast('Účet vytvořen! Vítejte, ' + data.jmeno + '!');
            loadNotifications();
            renderProducts(products);
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
            clearUserForms();
            showToast('Odhlášeno.');
            renderProducts(products);
        });
}

function clearUserForms() {
    ['loginEmail','loginPassword','regJmeno','regEmail','regPassword'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
    clearShippingForm();
}
function clearShippingForm() {
    ['shipJmeno','shipPrijmeni','shipAdresa','shipTelefon'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
}
function switchToRegister() {
    closeModal('loginModal');
    document.getElementById('regJmeno').value    = '';
    document.getElementById('regEmail').value    = '';
    document.getElementById('regPassword').value = '';
    openModal('registerModal');
}
function switchToLogin() {
    closeModal('registerModal');
    openLogin();
}
function showLoggedInButtons() {
    document.getElementById('notifBtn').style.display        = '';
    document.getElementById('loginBtn').style.display        = 'none';
    document.getElementById('profileDropWrap').style.display = '';
    document.getElementById('profileName').textContent       = currentUser.jmeno;
}
function hideLoggedInButtons() {
    document.getElementById('notifBtn').style.display        = 'none';
    document.getElementById('notif-count').style.display     = 'none';
    document.getElementById('loginBtn').style.display        = '';
    document.getElementById('profileDropWrap').style.display = 'none';
}

// ── RESET HESLA ──
function sendForgotPassword() {
    var email = document.getElementById('forgotEmail').value.trim();
    if (!email) { showToast('Zadejte svůj e-mail.'); return; }
    fetch('api.php?action=forgot_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email })
    })
        .then(function(r) { return r.json(); })
        .then(function() {
            closeModal('forgotPasswordModal');
            showToast('Pokud účet existuje, odeslali jsme resetovací odkaz.');
        })
        .catch(function() { showToast('Chyba připojení.'); });
}
function submitResetPassword() {
    var token = document.getElementById('resetToken').value;
    var pass1 = document.getElementById('resetPassword').value;
    var pass2 = document.getElementById('resetPassword2').value;
    if (!token) { showToast('Neplatný token.'); return; }
    if (pass1.length < 6) { showToast('Heslo musí mít alespoň 6 znaků.'); return; }
    if (pass1 !== pass2) { showToast('Hesla se neshodují.'); return; }
    fetch('api.php?action=reset_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token, password: pass1 })
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            closeModal('resetPasswordModal');
            window.history.replaceState({}, document.title, window.location.pathname);
            showToast('Heslo změněno. Přihlaste se.');
            openLogin();
        })
        .catch(function() { showToast('Chyba připojení.'); });
}

// ── PRODUCTS ──
function toggleStavFilter(val, btn) {
    var group  = btn.closest('.filter-group');
    var allBtn = group.querySelector('.filter-chip');
    if (val === '') {
        activeStavFilters.clear();
        group.querySelectorAll('.filter-chip').forEach(function(b) { b.classList.remove('active'); });
        allBtn.classList.add('active');
    } else {
        allBtn.classList.remove('active');
        if (activeStavFilters.has(val)) {
            activeStavFilters.delete(val);
            btn.classList.remove('active');
        } else {
            activeStavFilters.add(val);
            btn.classList.add('active');
        }
        if (activeStavFilters.size === 0) allBtn.classList.add('active');
    }
    loadProducts();
}

function setSortFilter(val, btn) {
    currentSort = val;
    btn.closest('.filter-group').querySelectorAll('.filter-chip').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    loadProducts();
}

function loadProducts() {
    var q   = (document.getElementById('search') || {}).value;
    q = q ? q.trim() : '';
    var url = 'api.php?action=products';
    if (q) url += '&q=' + encodeURIComponent(q);
    if (activeStavFilters.size > 0) url += '&stav=' + encodeURIComponent(Array.from(activeStavFilters).join(','));
    if (currentSort && currentSort !== 'newest') url += '&sort=' + encodeURIComponent(currentSort);
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) { products = data; renderProducts(data); })
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
    if (p.brand)         meta += '<span class="card-meta">' + escapeHtml(p.brand) + '</span> ';
    if (p.condition_val) meta += '<span class="card-meta card-condition">' + (conditionLabels[p.condition_val] || p.condition_val) + '</span>';
    if (p.isbn)          meta += '<span class="card-meta">ISBN: ' + escapeHtml(p.isbn) + '</span>';

    var isOwn = currentUser && String(p.seller_id) === String(currentUser.id);

    var chatBtn = (!isOwn)
        ? '<button class="btn btn-ghost card-chat-btn" onclick="event.stopPropagation();openProductChat(' + p.id + ',' + p.seller_id + ',\'' + escapeHtml(p.title).replace(/'/g,"\\'") + '\')">💬</button>'
        : '';

    return '<div class="card" data-id="' + p.id + '" onclick="openProductDetail(' + p.id + ')">' +
        '<div class="card-img-wrap">' + imgHtml + '</div>' +
        '<div class="card-body">' +
        '<div class="card-title">' + escapeHtml(p.title) + '</div>' +
        (meta ? '<div style="margin-bottom:4px;">' + meta + '</div>' : '') +
        desc +
        '</div>' +
        '<div class="card-footer">' +
        '<span class="card-price">' + Number(p.price).toLocaleString('cs') + ' Kč</span>' +
        '<div style="display:flex;gap:6px;align-items:center;">' +
        chatBtn +
        '<button class="btn btn-amber" style="margin:0;padding:7px 14px;font-size:.82rem;" ' +
        'onclick="event.stopPropagation();addToCart(' + p.id + ')">Do košíku</button>' +
        '</div>' +
        '</div>' +
        '</div>';
}

function renderProducts(list) {
    var container = document.getElementById('products');
    var source    = list !== undefined ? list : products;
    if (!source.length) {
        container.innerHTML = '<div class="empty-state"><div class="icon">📚</div><p>Zatím žádné knihy</p></div>';
        return;
    }
    container.innerHTML = source.map(createCard).join('');
}

function filterProducts() { loadProducts(); }

// ── PRODUCT DETAIL MODAL ──
function openProductDetail(id) {
    var p = products.find(function(x) { return x.id == id; });
    if (!p) return;

    var isOwn = currentUser && String(p.seller_id) === String(currentUser.id);
    var condLabel = conditionLabels[p.condition_val] || p.condition_val || '';

    var imgHtml = p.img
        ? '<img src="' + p.img + '" alt="' + escapeHtml(p.title) + '" style="width:100%;max-height:340px;object-fit:cover;border-radius:10px;margin-bottom:20px;">'
        : '<div style="width:100%;height:200px;background:var(--cream);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:4rem;margin-bottom:20px;">📚</div>';

    var actionRow;
    if (isOwn) {
        actionRow = '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">' +
            '<span style="color:#aaa;font-size:.88rem;font-style:italic;">Toto je váš inzerát</span>' +
            '</div>';
    } else {
        actionRow = '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">' +
            '<button class="btn btn-ghost" style="border:1.5px solid var(--amber);color:var(--amber);" ' +
            'onclick="closeModal(\'productDetailModal\');openProductChat(' + p.id + ',' + p.seller_id + ',\'' + escapeHtml(p.title).replace(/'/g,"\\'") + '\')">💬 Napsat prodejci</button>' +
            '<button class="btn btn-amber" style="flex:1;justify-content:center;" ' +
            'onclick="addToCart(' + p.id + ');closeModal(\'productDetailModal\');">🛒 Do košíku</button>' +
            '</div>';
    }

    var html =
        imgHtml +
        '<h2 style="font-family:\'Playfair Display\',serif;font-size:1.5rem;margin-bottom:10px;padding-right:28px;">' + escapeHtml(p.title) + '</h2>' +
        '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">' +
        (condLabel ? '<span class="card-meta card-condition">' + condLabel + '</span>' : '') +
        (p.brand   ? '<span class="card-meta">' + escapeHtml(p.brand) + '</span>' : '') +
        (p.isbn    ? '<span class="card-meta">ISBN: ' + escapeHtml(p.isbn) + '</span>' : '') +
        '</div>' +
        (p.description ? '<p style="font-size:.93rem;color:#555;line-height:1.6;margin-bottom:16px;">' + escapeHtml(p.description) + '</p>' : '') +
        '<div style="font-size:.85rem;color:var(--ink);margin-bottom:18px;">Prodejce: <b>' + escapeHtml(p.seller_name || '') + '</b></div>' +
        '<div style="font-size:1.6rem;font-weight:800;color:var(--ink);margin-bottom:22px;">' + Number(p.price).toLocaleString('cs') + ' Kč</div>' +
        actionRow;

    document.getElementById('productDetailBody').innerHTML = html;
    openModal('productDetailModal');
}

// ── CART ──
function addToCart(id) {
    var item = products.find(function(p) { return p.id == id; });
    if (!item) return;
    if (currentUser && String(item.seller_id) === String(currentUser.id)) {
        showToast('Nemůžete přidat vlastní inzerát do košíku.');
        return;
    }
    if (cart.find(function(c) { return c.id == id; })) {
        showToast('Tato kniha je již v košíku.');
        return;
    }
    cart.push(item);
    updateCart();
    showToast('"' + item.title + '" přidáno do košíku');
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    updateCart();
}

function updateCart() {
    var count = cart.length;
    var badge = document.getElementById('cart-count');
    if (count > 0) {
        badge.textContent   = count;
        badge.style.display = '';
    } else {
        badge.style.display = 'none';
    }

    var list = document.getElementById('cart-items');
    if (count === 0) {
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
    var total = cart.reduce(function(s, i) { return s + Number(i.price); }, 0);
    document.getElementById('cart-total').textContent = total.toLocaleString('cs') + ' Kč';
}

function toggleCart() {
    document.getElementById('cart').classList.toggle('open');
    document.getElementById('cartOverlay').classList.toggle('open');
}

function checkout() {
    if (!cart.length) { showToast('Košík je prázdný.'); return; }
    if (!currentUser) { showToast('Pro objednávku se přihlaste.'); openLogin(); return; }
    toggleCart();
    clearShippingForm();
    openModal('shippingModal');
}

function submitCheckout() {
    var jmeno    = document.getElementById('shipJmeno').value.trim();
    var prijmeni = document.getElementById('shipPrijmeni').value.trim();
    var adresa   = document.getElementById('shipAdresa').value.trim();
    var telefon  = document.getElementById('shipTelefon').value.trim();
    if (!jmeno || !prijmeni || !adresa) { showToast('Vyplňte jméno, příjmení a adresu.'); return; }

    var items = cart.map(function(item) { return { id: item.id, title: item.title }; });
    fetch('api.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: items, jmeno: jmeno, prijmeni: prijmeni, adresa: adresa, telefon: telefon })
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            showToast('Objednávka odeslána! ✓');
            if (data.warnings && data.warnings.length) setTimeout(function() { showToast(data.warnings[0]); }, 3000);
            cart = [];
            updateCart();
            closeModal('shippingModal');
            clearShippingForm();
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
    if (parseFloat(price) <= 0) { showToast('Cena musí být větší než 0.'); return; }
    var fd = new FormData();
    fd.append('title', title); fd.append('price', price); fd.append('desc', desc);
    fd.append('brand', brand); fd.append('isbn', isbn);   fd.append('stav', stav);
    if (file) fd.append('image', file);
    fetch('api.php?action=add', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            closeModal('addModal');
            ['title','price','desc','brand','isbn'].forEach(function(id) { document.getElementById(id).value = ''; });
            document.getElementById('stav').value  = 'pouzity';
            document.getElementById('image').value = '';
            showToast('Kniha přidána! 📚');
            loadProducts();
        })
        .catch(function() { showToast('Chyba při přidávání knihy.'); });
}

// ── MY ORDERS ──
function openMyOrders() {
    if (!currentUser) { showToast('Přihlaste se.'); openLogin(); return; }
    fetch('api.php?action=my_orders')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var stavLabels = { 'cekajici':'Čeká na zpracování','zaplaceno':'Zaplaceno','odeslano':'Odesláno','dokonceno':'Dokončeno','zruseno':'Zrušeno' };
            var html = '';
            if (!data.length) {
                html = '<p style="color:var(--ink);text-align:center;padding:32px 0;">Žádné objednávky.</p>';
            } else {
                data.forEach(function(o) {
                    var rateBtn = !parseInt(o.already_rated)
                        ? '<button class="btn btn-amber" style="padding:5px 12px;font-size:.8rem;" onclick="openRateModal(' + o.id + ',' + o.seller_id + ')">Ohodnotit prodejce</button>'
                        : '<span style="font-size:.8rem;color:var(--ink);">✓ Hodnoceno</span>';
                    var chatBtn = '<button class="btn btn-ghost" style="padding:5px 12px;font-size:.8rem;color:var(--ink);border:1px solid var(--cream);" ' +
                        'onclick="openProductChat(' + o.nabidka_id + ',' + o.seller_id + ',\'' + escapeHtml(o.title).replace(/'/g,"\\'") + '\')">💬 Chat</button>';
                    html += '<div class="order-row">' +
                        (o.img ? '<img src="' + o.img + '" style="width:52px;height:52px;object-fit:cover;border-radius:8px;">' : '<div style="width:52px;height:52px;background:var(--cream);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">📚</div>') +
                        '<div style="flex:1;"><div style="font-weight:600;">' + escapeHtml(o.title) + '</div>' +
                        '<div style="font-size:.82rem;color:var(--ink);">Prodejce: ' + escapeHtml(o.seller_name) + '</div>' +
                        '<div style="font-size:.82rem;color:var(--ink);">Stav: <b>' + (stavLabels[o.stav] || o.stav) + '</b></div>' +
                        '<div style="font-size:.78rem;color:#aaa;">' + o.vytvoreno + '</div></div>' +
                        '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">' +
                        '<span style="font-weight:700;">' + Number(o.price).toLocaleString('cs') + ' Kč</span>' +
                        rateBtn + chatBtn + '</div></div>';
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
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var html = '';
            if (!data.length) {
                html = '<p style="color:var(--ink);text-align:center;padding:32px 0;">Žádné inzeráty. Přidejte první knihu!</p>';
            } else {
                data.forEach(function(l) {
                    var badge = l.stav === 'prodano'
                        ? '<span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:999px;font-size:.75rem;">Prodáno</span>'
                        : l.stav === 'zruseno'
                            ? '<span style="background:#e57373;color:#fff;padding:2px 8px;border-radius:999px;font-size:.75rem;">Zrušeno</span>'
                            : '<span style="background:var(--amber);color:#fff;padding:2px 8px;border-radius:999px;font-size:.75rem;">Aktivní</span>';
                    html += '<div class="order-row">' +
                        (l.img ? '<img src="' + l.img + '" style="width:52px;height:52px;object-fit:cover;border-radius:8px;">' : '<div style="width:52px;height:52px;background:var(--cream);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">📚</div>') +
                        '<div style="flex:1;"><div style="font-weight:600;">' + escapeHtml(l.title) + '</div>' +
                        '<div style="font-size:.78rem;color:#aaa;">' + l.datum + '</div></div>' +
                        '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">' +
                        '<span style="font-weight:700;">' + Number(l.price).toLocaleString('cs') + ' Kč</span>' +
                        badge + '</div></div>';
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
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var unread = data.filter(function(n) { return !parseInt(n.precteno); }).length;
            var badge  = document.getElementById('notif-count');
            badge.textContent   = unread;
            badge.style.display = unread > 0 ? '' : 'none';

            var typIcons = { 'prodej':'💰','nakup':'📦','hodnoceni':'⭐','zprava':'💬' };
            var html = '';
            if (!data.length) {
                html = '<p style="color:var(--ink);text-align:center;padding:32px 0;">Žádná oznámení.</p>';
            } else {
                data.forEach(function(n) {
                    html += '<div class="notif-row' + (parseInt(n.precteno) ? '' : ' notif-unread') + '" id="notif-' + n.id + '">' +
                        '<span style="font-size:1.3rem;">' + (typIcons[n.typ] || '🔔') + '</span>' +
                        '<div style="flex:1;"><div style="font-size:.88rem;">' + escapeHtml(n.text) + '</div>' +
                        '<div style="font-size:.75rem;color:#aaa;">' + n.vytvoreno + '</div></div>' +
                        '<button onclick="deleteNotification(' + n.id + ')" title="Smazat" ' +
                        'style="background:none;border:none;cursor:pointer;color:#aaa;font-size:1rem;padding:4px 6px;border-radius:50%;" ' +
                        'onmouseover="this.style.color=\'#e57373\'" onmouseout="this.style.color=\'#aaa\'">✕</button>' +
                        '</div>';
                });
            }
            document.getElementById('notifList').innerHTML = html;
        })
        .catch(function() {});
}

function deleteNotification(id) {
    fetch('api.php?action=notification_delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            var el = document.getElementById('notif-' + id);
            if (el) el.remove();
            loadNotifications();
        })
        .catch(function() { showToast('Nepodařilo se smazat oznámení.'); });
}

function openNotifications() {
    loadNotifications();
    openModal('notificationsModal');
    fetch('api.php?action=notifications_read', { method: 'POST' })
        .then(function() { document.getElementById('notif-count').style.display = 'none'; });
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
    var orderId  = parseInt(document.getElementById('rateOrderId').value);
    var sellerId = parseInt(document.getElementById('rateSellerId').value);
    var rating   = parseInt(document.getElementById('rateValue').value);
    var comment  = document.getElementById('rateComment').value.trim();
    if (rating < 1 || rating > 5) { showToast('Vyberte hodnocení (1–5 hvězdiček).'); return; }
    fetch('api.php?action=rate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, seller_id: sellerId, rating: rating, comment: comment })
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            showToast('Hodnocení odesláno! ⭐');
            closeModal('rateModal');
        })
        .catch(function() { showToast('Chyba při odesílání hodnocení.'); });
}

// ── CHAT ──
function openProductChat(nabidkaId, druhyId, nazev) {
    if (!currentUser) { showToast('Pro chat se přihlaste.'); openLogin(); return; }

    chatNabidkaId = nabidkaId;
    chatDruhyId   = druhyId;

    document.getElementById('chatModalTitle').textContent = 'Chat – ' + nazev;
    document.getElementById('chatBox').innerHTML = '';
    document.getElementById('chatInput').value   = '';

    openModal('chatModal');
    loadChat();
}

function loadChat() {
    if (!chatNabidkaId || !chatDruhyId) return;
    fetch('api.php?action=chat&nabidka_id=' + chatNabidkaId + '&druhy_id=' + chatDruhyId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            messages = data;
            renderChat();
        })
        .catch(function() { renderChat(); });
}

function sendMessage() {
    if (!currentUser) { showToast('Pro psaní zpráv se přihlaste.'); return; }
    if (!chatNabidkaId || !chatDruhyId) { showToast('Žádný chat není otevřen.'); return; }

    var input = document.getElementById('chatInput');
    var text  = input.value.trim();
    if (!text) return;

    fetch('api.php?action=chat_send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            text:        text,
            nabidka_id:  chatNabidkaId,
            prijemce_id: chatDruhyId
        })
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast(data.error); return; }
            messages.push({
                user:         data.user,
                text:         data.text,
                time:         data.time,
                odesilatel_id: currentUser.id
            });
            input.value = '';
            renderChat();
        })
        .catch(function() { showToast('Zprávu se nepodařilo odeslat.'); });
}

function renderChat() {
    var box = document.getElementById('chatBox');
    if (!messages.length) {
        box.innerHTML = '<p style="color:var(--ink);text-align:center;margin-top:60px;font-size:.9rem;">Zatím žádné zprávy. Napište první!</p>';
        return;
    }
    box.innerHTML = messages.map(function(m) {
        var isMine = currentUser && String(m.odesilatel_id) === String(currentUser.id);
        return '<div class="chat-msg' + (isMine ? ' chat-msg-mine' : '') + '">' +
            '<b>' + escapeHtml(m.user) + '</b>' +
            '<span style="font-size:.76rem;margin-left:6px;opacity:.6;">' + (m.time || '') + '</span><br>' +
            escapeHtml(m.text) +
            '</div>';
    }).join('');
    box.scrollTop = box.scrollHeight;
}


// ── MY CONVERSATIONS ──
function openMyConversations() {
    if (!currentUser) { showToast('Přihlaste se.'); openLogin(); return; }
    var list = document.getElementById('convList');
    list.innerHTML = '<p style="text-align:center;padding:24px 0;color:var(--ink);">Načítám…</p>';
    openModal('conversationsModal');
    fetch('api.php?action=my_conversations')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.length) {
                list.innerHTML = '<p style="color:var(--ink);text-align:center;padding:32px 0;">Žádné zprávy.</p>';
                return;
            }
            list.innerHTML = data.map(function(c) {
                var initials = escapeHtml(c.druhy_jmeno).charAt(0).toUpperCase();
                return '<div class="conv-row" onclick="closeModal(\'conversationsModal\');openProductChat(' +
                    c.nabidka_id + ',' + c.druhy_id + ',\'' + escapeHtml(c.nazev_nabidky).replace(/'/g,"\\'") + '\')">' +
                    '<div class="conv-avatar">' + initials + '</div>' +
                    '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:600;font-size:.9rem;">' + escapeHtml(c.druhy_jmeno) + '</div>' +
                    '<div style="font-size:.78rem;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(c.nazev_nabidky) + '</div>' +
                    (c.posledni_zprava ? '<div style="font-size:.76rem;color:#aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(c.posledni_zprava) + '</div>' : '') +
                    '</div>' +
                    '<div style="font-size:.72rem;color:#bbb;white-space:nowrap;margin-left:8px;">' + (c.posledni_cas || '') + '</div>' +
                    '</div>';
            }).join('');
        })
        .catch(function() { list.innerHTML = '<p style="color:#e57373;text-align:center;padding:24px;">Chyba načítání.</p>'; });
}

// ── INIT ──
(function checkResetToken() {
    var token = new URLSearchParams(window.location.search).get('reset_token');
    if (token) {
        document.getElementById('resetToken').value     = token;
        document.getElementById('resetPassword').value  = '';
        document.getElementById('resetPassword2').value = '';
        openModal('resetPasswordModal');
    }
})();

fetch('api.php?action=me')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data && data.id) {
            currentUser = data;
            showLoggedInButtons();
            loadNotifications();
        }
    })
    .catch(function() {});

loadProducts();
updateCart();