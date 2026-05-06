<?php
session_start();
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ── PRODUCTS LIST ──
if ($action === 'products') {
    $q = isset($_GET['q']) ? '%' . $_GET['q'] . '%' : '%';
    $stmt = $pdo->prepare(
        "SELECT n.nabidka_id AS id,
                p.nazev      AS title,
                p.popis      AS description,
                p.stav       AS condition_val,
                p.znacka     AS brand,
                p.isbn       AS isbn,
                n.cena       AS price,
                n.uzivatel_id AS seller_id,
                f.url        AS img
         FROM   Nabidka  n
         JOIN   Polozka  p ON p.polozka_id  = n.polozka_id
         LEFT JOIN (
             SELECT nabidka_id, url
             FROM   Fotka
             WHERE  poradi = (
                 SELECT MIN(poradi) FROM Fotka f2
                 WHERE f2.nabidka_id = Fotka.nabidka_id
             )
         ) f ON f.nabidka_id = n.nabidka_id
         WHERE  n.stav_nabidky = 'aktivni'
           AND  (p.nazev LIKE :q OR p.popis LIKE :q)
         ORDER BY n.datum DESC"
    );
    $stmt->execute(array(':q' => $q));
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── LOGIN ──
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $email = isset($body['email']) ? trim($body['email']) : '';
    $pass  = isset($body['password']) ? $body['password'] : '';

    if (!$email || !$pass) {
        http_response_code(400);
        echo json_encode(array('error' => 'Vyplňte e-mail a heslo.'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM Uzivatel WHERE email = :email");
    $stmt->execute(array(':email' => $email));
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(array('error' => 'Účet nenalezen. Zaregistrujte se prosím.'));
    } elseif (password_verify($pass, $user['heslo'])) {
        $_SESSION['user'] = array('id' => $user['uzivatel_id'], 'email' => $user['email'], 'jmeno' => $user['jmeno']);
        echo json_encode(array('status' => 'ok', 'email' => $user['email'], 'jmeno' => $user['jmeno']));
    } else {
        http_response_code(401);
        echo json_encode(array('error' => 'Špatné heslo.'));
    }
    exit;
}

// ── REGISTER ──
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $email = isset($body['email'])    ? trim($body['email'])    : '';
    $pass  = isset($body['password']) ? $body['password']       : '';
    $jmeno = isset($body['jmeno'])    ? trim($body['jmeno'])    : '';

    if (!$email || !$pass || !$jmeno) {
        http_response_code(400);
        echo json_encode(array('error' => 'Vyplňte jméno, e-mail a heslo.'));
        exit;
    }

    $chk = $pdo->prepare("SELECT uzivatel_id FROM Uzivatel WHERE email = :email");
    $chk->execute(array(':email' => $email));
    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode(array('error' => 'Tento e-mail je již zaregistrován.'));
        exit;
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $ins = $pdo->prepare(
        "INSERT INTO Uzivatel (jmeno, prijmeni, email, heslo)
         VALUES (:jmeno, '', :email, :heslo)"
    );
    $ins->execute(array(':jmeno' => $jmeno, ':email' => $email, ':heslo' => $hash));
    $userId = $pdo->lastInsertId();
    $_SESSION['user'] = array('id' => $userId, 'email' => $email, 'jmeno' => $jmeno);
    echo json_encode(array('status' => 'registered', 'email' => $email, 'jmeno' => $jmeno));
    exit;
}

// ── LOGOUT ──
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_destroy();
    echo json_encode(array('status' => 'ok'));
    exit;
}

// ── SESSION CHECK ──
if ($action === 'me') {
    if (isset($_SESSION['user'])) {
        echo json_encode($_SESSION['user']);
    } else {
        echo json_encode(null);
    }
    exit;
}

// ── ADD LISTING ──
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(array('error' => 'Nejste přihlášeni.'));
        exit;
    }

    $title  = isset($_POST['title'])  ? trim($_POST['title'])  : '';
    $price  = isset($_POST['price'])  ? floatval($_POST['price']) : 0;
    $desc   = isset($_POST['desc'])   ? trim($_POST['desc'])   : '';
    $brand  = isset($_POST['brand'])  ? trim($_POST['brand'])  : '';
    $isbn   = isset($_POST['isbn'])   ? trim($_POST['isbn'])   : '';
    $stav   = isset($_POST['stav'])   ? trim($_POST['stav'])   : 'pouzity';

    $allowed_stav = array('novy', 'pouzity', 'poskozeny');
    if (!in_array($stav, $allowed_stav)) $stav = 'pouzity';

    if (!$title || $price <= 0) {
        http_response_code(400);
        echo json_encode(array('error' => 'Vyplňte název a cenu.'));
        exit;
    }

    $catId = 1;
    $catCheck = $pdo->query("SELECT kategorie_id FROM Kategorie WHERE kategorie_id = 1");
    if (!$catCheck->fetch()) {
        $pdo->exec("INSERT INTO Kategorie (kategorie_id, nazev) VALUES (1, 'Učebnice')");
    }

    $insItem = $pdo->prepare(
        "INSERT INTO Polozka (kategorie_id, nazev, popis, znacka, isbn, stav)
         VALUES (:kat, :nazev, :popis, :znacka, :isbn, :stav)"
    );
    $insItem->execute(array(
        ':kat'   => $catId,
        ':nazev' => $title,
        ':popis' => $desc,
        ':znacka'=> $brand,
        ':isbn'  => $isbn,
        ':stav'  => $stav,
    ));
    $polozkaId = $pdo->lastInsertId();

    $insOffer = $pdo->prepare(
        "INSERT INTO Nabidka (uzivatel_id, polozka_id, cena) VALUES (:uid, :pid, :cena)"
    );
    $insOffer->execute(array(
        ':uid'  => $_SESSION['user']['id'],
        ':pid'  => $polozkaId,
        ':cena' => $price,
    ));
    $nabidkaId = $pdo->lastInsertId();

    $imgUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext     = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        if (in_array(strtolower($ext), $allowed)) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = $nabidkaId . '_' . time() . '.' . $ext;
            $dest     = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $imgUrl = $dest;
                $insFoto = $pdo->prepare(
                    "INSERT INTO Fotka (nabidka_id, url, poradi) VALUES (:nid, :url, 0)"
                );
                $insFoto->execute(array(':nid' => $nabidkaId, ':url' => $imgUrl));
            }
        }
    }

    echo json_encode(array(
        'status' => 'ok',
        'id'     => $nabidkaId,
        'title'  => $title,
        'price'  => $price,
        'desc'   => $desc,
        'img'    => $imgUrl,
    ));
    exit;
}

// ── CHECKOUT ──
if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(array('error' => 'Nejste přihlášeni.'));
        exit;
    }

    $body    = json_decode(file_get_contents('php://input'), true);
    $items   = isset($body['items'])   ? $body['items']   : array();
    $jmeno   = isset($body['jmeno'])   ? trim($body['jmeno'])   : '';
    $prijmeni= isset($body['prijmeni'])? trim($body['prijmeni']): '';
    $adresa  = isset($body['adresa'])  ? trim($body['adresa'])  : '';
    $telefon = isset($body['telefon']) ? trim($body['telefon']) : '';

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(array('error' => 'Košík je prázdný.'));
        exit;
    }
    if (!$jmeno || !$prijmeni || !$adresa) {
        http_response_code(400);
        echo json_encode(array('error' => 'Vyplňte doručovací údaje.'));
        exit;
    }

    if ($telefon) {
        $updUser = $pdo->prepare("UPDATE Uzivatel SET telefon = :tel WHERE uzivatel_id = :uid");
        $updUser->execute(array(':tel' => $telefon, ':uid' => $_SESSION['user']['id']));
    }

    $ins = $pdo->prepare(
        "INSERT INTO Objednavka (nabidka_id, kupujici_id, jmeno, prijmeni, adresa) 
         VALUES (:nid, :uid, :jmeno, :prijmeni, :adresa)"
    );
    $upd = $pdo->prepare(
        "UPDATE Nabidka SET stav_nabidky = 'prodano' WHERE nabidka_id = :nid"
    );
    $linkOrder = $pdo->prepare(
        "UPDATE Nabidka SET objednavka_id = :oid WHERE nabidka_id = :nid"
    );
    $sellerStmt = $pdo->prepare("SELECT uzivatel_id FROM Nabidka WHERE nabidka_id = :nid");

    $notifIns = $pdo->prepare(
        "INSERT INTO Notifikace (uzivatel_id, typ, text) VALUES (:uid, :typ, :text)"
    );

    $buyerName = $jmeno . ' ' . $prijmeni;

    foreach ($items as $item) {
        $nid = intval($item['id']);
        $ins->execute(array(
            ':nid' => $nid,
            ':uid' => $_SESSION['user']['id'],
            ':jmeno' => $jmeno,
            ':prijmeni' => $prijmeni,
            ':adresa' => $adresa
        ));
        $orderId = $pdo->lastInsertId();

        $upd->execute(array(':nid' => $nid));
        $linkOrder->execute(array(':oid' => $orderId, ':nid' => $nid));

        $sellerStmt->execute(array(':nid' => $nid));
        $sellerRow = $sellerStmt->fetch();
        if ($sellerRow) {
            $notifIns->execute(array(
                ':uid'  => $sellerRow['uzivatel_id'],
                ':typ'  => 'prodej',
                ':text' => 'Vaše nabídka "' . htmlspecialchars($item['title']) . '" byla zakoupena uživatelem ' . htmlspecialchars($buyerName) . '. Adresa: ' . htmlspecialchars($adresa) . '.',
            ));
        }
        $notifIns->execute(array(
            ':uid'  => $_SESSION['user']['id'],
            ':typ'  => 'nakup',
            ':text' => 'Vaše objednávka "' . htmlspecialchars($item['title']) . '" byla přijata. Doručíme na: ' . htmlspecialchars($adresa) . '.',
        ));
    }

    echo json_encode(array('status' => 'ok'));
    exit;
}

// ── MY ORDERS (buyer) ──
if ($action === 'my_orders') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(array('error' => 'Nejste přihlášeni.'));
        exit;
    }
    $stmt = $pdo->prepare(
        "SELECT o.objednavka_id AS id,
                o.stav,
                o.vytvoreno,
                p.nazev   AS title,
                n.cena    AS price,
                f.url     AS img,
                n.nabidka_id,
                n.uzivatel_id AS seller_id,
                u.jmeno   AS seller_name,
                (SELECT COUNT(*) FROM Hodnoceni h
                 WHERE h.objednavka_id = o.objednavka_id
                   AND h.hodnotitel_id = :uid2) AS already_rated
         FROM   Objednavka o
         JOIN   Nabidka  n ON n.nabidka_id  = o.nabidka_id
         JOIN   Polozka  p ON p.polozka_id  = n.polozka_id
         JOIN   Uzivatel u ON u.uzivatel_id = n.uzivatel_id
         LEFT JOIN (
             SELECT nabidka_id, url FROM Fotka
             WHERE poradi = (SELECT MIN(poradi) FROM Fotka f2 WHERE f2.nabidka_id = Fotka.nabidka_id)
         ) f ON f.nabidka_id = n.nabidka_id
         WHERE  o.kupujici_id = :uid
         ORDER BY o.vytvoreno DESC"
    );
    $stmt->execute(array(':uid' => $_SESSION['user']['id'], ':uid2' => $_SESSION['user']['id']));
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── MY LISTINGS (seller) ──
if ($action === 'my_listings') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(array('error' => 'Nejste přihlášeni.'));
        exit;
    }
    $stmt = $pdo->prepare(
        "SELECT n.nabidka_id AS id,
                p.nazev      AS title,
                n.cena       AS price,
                n.stav_nabidky AS stav,
                n.datum,
                f.url        AS img
         FROM   Nabidka n
         JOIN   Polozka p ON p.polozka_id = n.polozka_id
         LEFT JOIN (
             SELECT nabidka_id, url FROM Fotka
             WHERE poradi = (SELECT MIN(poradi) FROM Fotka f2 WHERE f2.nabidka_id = Fotka.nabidka_id)
         ) f ON f.nabidka_id = n.nabidka_id
         WHERE  n.uzivatel_id = :uid
         ORDER BY n.datum DESC"
    );
    $stmt->execute(array(':uid' => $_SESSION['user']['id']));
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── NOTIFICATIONS ──
if ($action === 'notifications') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(array());
        exit;
    }
    $stmt = $pdo->prepare(
        "SELECT notifikace_id AS id, typ, text, precteno, vytvoreno
         FROM   Notifikace
         WHERE  uzivatel_id = :uid
         ORDER BY vytvoreno DESC
         LIMIT 30"
    );
    $stmt->execute(array(':uid' => $_SESSION['user']['id']));
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── MARK NOTIFICATIONS READ ──
if ($action === 'notifications_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) { echo json_encode(array('status'=>'ok')); exit; }
    $pdo->prepare("UPDATE Notifikace SET precteno = 1 WHERE uzivatel_id = :uid")
        ->execute(array(':uid' => $_SESSION['user']['id']));
    echo json_encode(array('status' => 'ok'));
    exit;
}

// ── RATE SELLER ──
if ($action === 'rate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(array('error' => 'Nejste přihlášeni.'));
        exit;
    }
    $body         = json_decode(file_get_contents('php://input'), true);
    $objednavkaId = isset($body['order_id'])   ? intval($body['order_id'])   : 0;
    $sellerId     = isset($body['seller_id'])  ? intval($body['seller_id'])  : 0;
    $rating       = isset($body['rating'])     ? intval($body['rating'])     : 0;
    $komentar     = isset($body['comment'])    ? trim($body['comment'])      : '';

    if ($rating < 1 || $rating > 5 || !$objednavkaId || !$sellerId) {
        http_response_code(400);
        echo json_encode(array('error' => 'Neplatné hodnocení.'));
        exit;
    }

    $chk = $pdo->prepare("SELECT objednavka_id FROM Objednavka WHERE objednavka_id = :oid AND kupujici_id = :uid");
    $chk->execute(array(':oid' => $objednavkaId, ':uid' => $_SESSION['user']['id']));
    if (!$chk->fetch()) {
        http_response_code(403);
        echo json_encode(array('error' => 'Přístup odepřen.'));
        exit;
    }

    $ins = $pdo->prepare(
        "INSERT INTO Hodnoceni (objednavka_id, hodnotitel_id, hodnoceny_id, komentar, rating)
         VALUES (:oid, :hid, :hcid, :kom, :rat)"
    );
    $ins->execute(array(
        ':oid'  => $objednavkaId,
        ':hid'  => $_SESSION['user']['id'],
        ':hcid' => $sellerId,
        ':kom'  => $komentar,
        ':rat'  => $rating,
    ));

    $notifIns = $pdo->prepare(
        "INSERT INTO Notifikace (uzivatel_id, typ, text) VALUES (:uid, :typ, :text)"
    );
    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    $notifIns->execute(array(
        ':uid'  => $sellerId,
        ':typ'  => 'hodnoceni',
        ':text' => 'Získali jste nové hodnocení: ' . $stars . ($komentar ? ' — "' . htmlspecialchars($komentar) . '"' : ''),
    ));

    echo json_encode(array('status' => 'ok'));
    exit;
}

// ── CHAT: GET MESSAGES ──
if ($action === 'chat') {
    $stmt = $pdo->query(
        "SELECT m.zprava AS text,
                m.cas    AS time,
                u.jmeno  AS user
         FROM   ChatZprava m
         JOIN   Uzivatel   u ON u.uzivatel_id = m.uzivatel_id
         ORDER BY m.cas ASC
         LIMIT 200"
    );
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── CHAT: SEND MESSAGE ──
if ($action === 'chat_send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $text = isset($body['text']) ? trim($body['text']) : '';
    $user = isset($_SESSION['user']) ? $_SESSION['user']['jmeno'] : 'anon';
    $uid  = isset($_SESSION['user']) ? $_SESSION['user']['id']    : null;

    if (!$text) {
        http_response_code(400);
        echo json_encode(array('error' => 'Prázdná zpráva.'));
        exit;
    }

    if ($uid) {
        $ins = $pdo->prepare(
            "INSERT INTO ChatZprava (uzivatel_id, zprava) VALUES (:uid, :zprava)"
        );
        $ins->execute(array(':uid' => $uid, ':zprava' => $text));
    }

    $time = date('H:i');
    echo json_encode(array('status' => 'ok', 'user' => $user, 'text' => $text, 'time' => $time));
    exit;
}

http_response_code(404);
echo json_encode(array('error' => 'Unknown action'));