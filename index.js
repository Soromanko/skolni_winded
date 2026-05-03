    // ── STATE ──
    let products = JSON.parse(localStorage.getItem('products')) || [];
    let cart     = [];
    let messages = JSON.parse(localStorage.getItem('chat')) || [];
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
    function openAdd()    { openModal('addModal'); }
    function openChat()   { openModal('chatModal'); renderChat(); }

    // Close modal on backdrop click
    document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

    // ── AUTH ──
    function login() {
    const email    = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;

    if (!email || !password) { showToast('Vyplňte e-mail a heslo.'); return; }

    let users = JSON.parse(localStorage.getItem('users')) || [];
    let user  = users.find(u => u.email === email);

    if (!user) {
    // Register
    users.push({ email, password });
    localStorage.setItem('users', JSON.stringify(users));
    currentUser = { email };
    showToast('Účet vytvořen! Vítejte, ' + email);
} else if (user.password === password) {
    currentUser = user;
    showToast('Vítejte zpět, ' + email);
} else {
    showToast('Špatné heslo.');
    return;
}

    document.getElementById('loginBtn').textContent = '👤 ' + email.split('@')[0];
    closeModal('loginModal');
}

    // ── PRODUCTS ──
    function createCard(p) {
    const imgHtml = p.img
    ? `<img src="${p.img}" alt="${p.title}" loading="lazy">`
    : `<div class="card-placeholder"></div>`;

    const desc = p.desc
    ? `<p class="card-desc">${p.desc.slice(0, 80)}${p.desc.length > 80 ? '…' : ''}</p>`
    : '';

    return `
      <div class="card" data-id="${p.id}">
        <div class="card-img-wrap">${imgHtml}</div>
        <div class="card-body">
          <div class="card-title">${p.title}</div>
          ${desc}
        </div>
        <div class="card-footer">
          <span class="card-price">${Number(p.price).toLocaleString('cs')} Kč</span>
          <button class="btn btn-amber" style="margin:0;padding:7px 14px;font-size:.82rem;"
            onclick="addToCart(${p.id})">Do košíku</button>
        </div>
      </div>`;
}

    function renderProducts(list) {
    const container = document.getElementById('products');
    const source    = list !== undefined ? list : products;

    if (source.length === 0) {
    container.innerHTML = `
        <div class="empty-state">
          <p>Zatím žádné knihy</p>
        </div>`;
    return;
}
    container.innerHTML = source.map(createCard).join('');
}

    function filterProducts() {
    const q = document.getElementById('search').value.toLowerCase().trim();
    const filtered = q
    ? products.filter(p => p.title.toLowerCase().includes(q) || (p.desc || '').toLowerCase().includes(q))
    : products;
    renderProducts(filtered);
}

    // ── CART ──
    function addToCart(id) {
    const item = products.find(p => p.id === id);
    if (!item) return;
    cart.push(item);
    updateCart();
    showToast(`"${item.title}" přidáno do košíku`);
}

    function removeFromCart(idx) {
    cart.splice(idx, 1);
    updateCart();
}

    function updateCart() {
    document.getElementById('cart-count').textContent = cart.length;

    const list = document.getElementById('cart-items');
    if (cart.length === 0) {
    list.innerHTML = '<li style="justify-content:center;color:var(--ink);padding:32px 0;">Košík je prázdný</li>';
} else {
    list.innerHTML = cart.map((item, i) => `
        <li>
          <span class="cart-item-title">${item.title}</span>
          <span class="cart-item-price">${Number(item.price).toLocaleString('cs')} Kč</span>
          <button class="cart-remove" onclick="removeFromCart(${i})" title="Odebrat">✕</button>
        </li>`).join('');
}

    const total = cart.reduce((sum, i) => sum + Number(i.price), 0);
    document.getElementById('cart-total').textContent = total.toLocaleString('cs') + ' Kč';
}

    function toggleCart() {
    document.getElementById('cart').classList.toggle('open');
    document.getElementById('cartOverlay').classList.toggle('open');
}

    function checkout() {
    if (cart.length === 0) { showToast('Košík je prázdný.'); return; }
    if (!currentUser) { showToast('Pro objednávku se přihlaste.'); openLogin(); return; }
    showToast('Objednávka odeslána! ✓');
    cart = [];
    updateCart();
    toggleCart();
}

    // ── ADD ITEM ──
    function addItem() {
    const title = document.getElementById('title').value.trim();
    const price = document.getElementById('price').value.trim();
    const desc  = document.getElementById('desc').value.trim();
    const file  = document.getElementById('image').files[0];

    if (!title || !price) { showToast('Vyplňte název a cenu.'); return; }

    const save = (img) => {
    products.push({ id: Date.now(), title, price: Number(price), desc, img });
    localStorage.setItem('products', JSON.stringify(products));
    renderProducts();
    closeModal('addModal');
    // Reset form
    ['title','price','desc'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('image').value = '';
    showToast('Kniha přidána!');
};

    if (file) {
    const reader = new FileReader();
    reader.onload = e => save(e.target.result);
    reader.readAsDataURL(file);
} else {
    save('');
}
}

    // ── CHAT ──
    function sendMessage() {
    const input = document.getElementById('chatInput');
    const text  = input.value.trim();
    if (!text) return;

    messages.push({
    user: currentUser ? currentUser.email.split('@')[0] : 'anon',
    text,
    time: new Date().toLocaleTimeString('cs', { hour: '2-digit', minute: '2-digit' })
});

    localStorage.setItem('chat', JSON.stringify(messages));
    input.value = '';
    renderChat();
}

    function renderChat() {
    const box = document.getElementById('chatBox');
    box.innerHTML = messages.length === 0
    ? '<p style="color:var(--ink);text-align:center;margin-top:80px;font-size:.9rem;">Zatím žádné zprávy.</p>'
    : messages.map(m => `
          <div class="chat-msg">
            <b>${m.user}</b>
            <span style="color:var(--ink);font-size:.76rem;margin-left:6px;">${m.time || ''}</span><br>
            ${m.text}
          </div>`).join('');
    box.scrollTop = box.scrollHeight;
}

    // ── INIT ──
    renderProducts();
    updateCart();