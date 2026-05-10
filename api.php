<?php
session_start();
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ── PRODUCTS LIST ──
if ($action === 'products') {
    $q    = isset($_GET['q'])    ? '%' . $_GET['q'] . '%' : '%';
    $stav = isset($_GET['stav']) ? trim($_GET['stav'])    : '';
    $sort = isset($_GET['sort']) ? trim($_GET['sort'])    : 'newest';

    $allowed_stav = array('novy', 'pouzity', 'poskozeny');
    $allowed_sort = array('newest', 'price_asc', 'price_desc');
    if (!in_array($stav, $allowed_stav)) $stav = '';
    if (!in_array($sort, $allowed_sort)) $sort = 'newest';

    $where  = "WHERE n.stav_nabidky = 'aktivni'
                 AND (p.nazev LIKE :q OR p.popis LIKE :q OR p.znacka LIKE :q OR p.isbn LIKE :q)";
    $params = array(':q' => $q);

    if ($stav !== '') {
        $where .= " AND p.stav = :stav";
        $params[':stav'] = $stav;
    }

    $orderBy = match($sort) {
        'price_asc'  => 'ORDER BY n.cena ASC',
        'price_desc' => 'ORDER BY n.cena DESC',
        default      => 'ORDER BY n.datum DESC',
    };

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
         $where $orderBy"
    );
    $stmt->execute($params);
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
        echo json_encode(array('status' => 'ok', 'id' => $user['uzivatel_id'], 'email' => $user['email'], 'jmeno' => $user['jmeno']));
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
    echo json_encode(array('status' => 'registered', 'id' => $userId, 'email' => $email, 'jmeno' => $jmeno));
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

// ── FORGOT PASSWORD ──
if ($action === 'forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $email = isset($body['email']) ? trim($body['email']) : '';

    if (!$email) {
        http_response_code(400);
        echo json_encode(array('error' => 'Zadejte e-mail.'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT uzivatel_id, jmeno FROM Uzivatel WHERE email = :email");
    $stmt->execute(array(':email' => $email));
    $user = $stmt->fetch();

    // Vždy vrátíme OK, aby nebylo možné zjistit, zda email existuje
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Zneplatnit staré tokeny tohoto uživatele
        $pdo->prepare("UPDATE PasswordReset SET pouzito = 1 WHERE uzivatel_id = :uid AND pouzito = 0")
            ->execute(array(':uid' => $user['uzivatel_id']));

        $ins = $pdo->prepare(
            "INSERT INTO PasswordReset (uzivatel_id, token, expiry) VALUES (:uid, :token, :expiry)"
        );
        $ins->execute(array(':uid' => $user['uzivatel_id'], ':token' => $token, ':expiry' => $expiry));

        // Sestavit odkaz – přizpůsobit doménu podle nasazení
        $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST']
            . strtok($_SERVER['REQUEST_URI'], '?')
            . '?reset_token=' . $token;

        $subject = '=?UTF-8?B?' . base64_encode('Resetování hesla – WINDED') . '?=';
        $message  = "Dobrý den, " . $user['jmeno'] . ",\n\n";
        $message .= "Pro resetování hesla klikněte na odkaz níže (platný 1 hodinu):\n\n";
        $message .= $resetLink . "\n\n";
        $message .= "Pokud jste o reset nepožádali, ignorujte tento e-mail.\n\nWINDED Marketplace";
        $headers  = implode("\r\n", array(
            'From: WINDED Marketplace <noreply@' . $_SERVER['HTTP_HOST'] . '>',
            'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
            'Content-Type: text/plain; charset=UTF-8',
            'MIME-Version: 1.0',
            'X-Mailer: PHP/' . phpversion(),
        ));
        $mailSent = mail($email, $subject, $message, $headers);
        // Poznámka: mail() vyžaduje nakonfigurovaný MTA (sendmail/postfix) nebo php.ini [SMTP] na Windows.
        // Pro spolehlivé odesílání e-mailů použijte PHPMailer s SMTP (smtp.gmail.com apod.).
    }

    echo json_encode(array('status' => 'ok'));
    exit;
}

// ── RESET PASSWORD ──
if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $token    = isset($body['token'])    ? trim($body['token'])    : '';
    $password = isset($body['password']) ? $body['password']       : '';

    if (!$token || strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(array('error' => 'Neplatný token nebo heslo je příliš krátké (min. 6 znaků).'));
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT r.reset_id, r.uzivatel_id FROM PasswordReset r
         WHERE r.token = :token AND r.pouzito = 0 AND r.expiry > NOW()"
    );
    $stmt->execute(array(':token' => $token));
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(400);
        echo json_encode(array('error' => 'Token je neplatný nebo vypršel. Požádejte o nový reset.'));
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE Uzivatel SET heslo = :heslo WHERE uzivatel_id = :uid")
        ->execute(array(':heslo' => $hash, ':uid' => $row['uzivatel_id']));

    $pdo->prepare("UPDATE PasswordReset SET pouzito = 1 WHERE reset_id = :rid")
        ->execute(array(':rid' => $row['reset_id']));

    echo json_encode(array('status' => 'ok'));
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

    $body     = json_decode(file_get_contents('php://input'), true);
    $items    = isset($body['items'])    ? $body['items']           : array();
    $jmeno    = isset($body['jmeno'])    ? trim($body['jmeno'])     : '';
    $prijmeni = isset($body['prijmeni']) ? trim($body['prijmeni'])  : '';
    $adresa   = isset($body['adresa'])   ? trim($body['adresa'])    : '';
    $telefon  = isset($body['telefon'])  ? trim($body['telefon'])   : '';

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(array('error' => 'Košík je prázdný.'));
        exit;
    }
    if (!$jmeno || !$prijmeni || !$adresa) {
        http_response_code(400);
        echo json_encode(array('error' => 'Vyplňte jméno, příjmení a adresu.'));
        exit;
    }

    $buyerId   = (int)$_SESSION['user']['id'];
    $buyerName = $jmeno . ' ' . $prijmeni;

    // Získat e-mail kupujícího pro potvrzovací e-mail
    $buyerEmailStmt = $pdo->prepare("SELECT email FROM Uzivatel WHERE uzivatel_id = :uid");
    $buyerEmailStmt->execute(array(':uid' => $buyerId));
    $buyerEmailRow = $buyerEmailStmt->fetch();
    $buyerEmail = $buyerEmailRow ? $buyerEmailRow['email'] : '';

    if ($telefon) {
        $pdo->prepare("UPDATE Uzivatel SET telefon = :tel WHERE uzivatel_id = :uid")
            ->execute(array(':tel' => $telefon, ':uid' => $buyerId));
    }

    $errors   = array();
    $ordered  = array();

    try {
        $pdo->beginTransaction();

        $checkStmt = $pdo->prepare(
            "SELECT stav_nabidky, uzivatel_id FROM Nabidka WHERE nabidka_id = :nid FOR UPDATE"
        );
        $insOrder = $pdo->prepare(
            "INSERT INTO Objednavka (nabidka_id, kupujici_id, jmeno, prijmeni, adresa)
             VALUES (:nid, :uid, :jmeno, :prijmeni, :adresa)"
        );
        $updNabidka = $pdo->prepare(
            "UPDATE Nabidka SET stav_nabidky = 'prodano', objednavka_id = :oid WHERE nabidka_id = :nid"
        );
        $sellerStmt = $pdo->prepare(
            "SELECT u.uzivatel_id, u.jmeno, u.email FROM Nabidka n
             JOIN Uzivatel u ON u.uzivatel_id = n.uzivatel_id
             WHERE n.nabidka_id = :nid"
        );
        $notifIns = $pdo->prepare(
            "INSERT INTO Notifikace (uzivatel_id, typ, text) VALUES (:uid, :typ, :text)"
        );

        foreach ($items as $item) {
            $nid = intval($item['id']);
            if ($nid <= 0) continue;

            // Zkontrolovat dostupnost a zabránit nákupu vlastního inzerátu
            $checkStmt->execute(array(':nid' => $nid));
            $nabRow = $checkStmt->fetch();

            if (!$nabRow) {
                $errors[] = 'Položka "' . htmlspecialchars($item['title']) . '" nebyla nalezena.';
                continue;
            }
            if ($nabRow['stav_nabidky'] !== 'aktivni') {
                $errors[] = 'Položka "' . htmlspecialchars($item['title']) . '" již není dostupná.';
                continue;
            }
            if ((int)$nabRow['uzivatel_id'] === $buyerId) {
                $errors[] = 'Nemůžete koupit vlastní inzerát "' . htmlspecialchars($item['title']) . '".';
                continue;
            }

            // Vložit objednávku
            $insOrder->execute(array(
                ':nid'      => $nid,
                ':uid'      => $buyerId,
                ':jmeno'    => $jmeno,
                ':prijmeni' => $prijmeni,
                ':adresa'   => $adresa,
            ));
            $orderId = $pdo->lastInsertId();

            // Označit nabídku jako prodanou a propojit s objednávkou
            $updNabidka->execute(array(':oid' => $orderId, ':nid' => $nid));

            $ordered[] = array('title' => $item['title'], 'order_id' => $orderId);

            // Notifikace a e-mail prodejci
            $sellerStmt->execute(array(':nid' => $nid));
            $sellerRow = $sellerStmt->fetch();
            if ($sellerRow) {
                $notifIns->execute(array(
                    ':uid'  => $sellerRow['uzivatel_id'],
                    ':typ'  => 'prodej',
                    ':text' => 'Vaše nabídka "' . htmlspecialchars($item['title']) . '" byla zakoupena uživatelem '
                        . htmlspecialchars($buyerName) . '. Adresa doručení: ' . htmlspecialchars($adresa) . '.',
                ));

                if (!empty($sellerRow['email'])) {
                    $subj = '=?UTF-8?B?' . base64_encode('Vaše nabídka byla prodána – WINDED') . '?=';
                    $msg  = "Dobrý den, " . $sellerRow['jmeno'] . ",\n\n"
                        . "Vaše nabídka \"" . $item['title'] . "\" byla zakoupena.\n"
                        . "Kupující: " . $buyerName . "\n"
                        . "Adresa doručení: " . $adresa . "\n"
                        . ($telefon ? "Telefon: " . $telefon . "\n" : '')
                        . "\nWINDED Marketplace";
                    @mail($sellerRow['email'], $subj, $msg,
                        'From: noreply@winded.cz' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8');
                }
            }

            // Notifikace kupujícímu
            $notifIns->execute(array(
                ':uid'  => $buyerId,
                ':typ'  => 'nakup',
                ':text' => 'Vaše objednávka "' . htmlspecialchars($item['title']) . '" byla přijata. Doručíme na: '
                    . htmlspecialchars($adresa) . '.',
            ));
        }

        $pdo->commit();

        // Potvrzovací e-mail kupujícímu (jednou za celou objednávku)
        if (!empty($ordered) && !empty($buyerEmail)) {
            $titles = implode(', ', array_column($ordered, 'title'));
            $subj = '=?UTF-8?B?' . base64_encode('Potvrzení objednávky – WINDED') . '?=';
            $msg  = "Dobrý den, " . $jmeno . ",\n\n"
                . "Děkujeme za objednávku na WINDED!\n\n"
                . "Objednané položky: " . $titles . "\n"
                . "Adresa doručení: " . $adresa . "\n\n"
                . "WINDED Marketplace";
            @mail($buyerEmail, $subj, $msg,
                'From: noreply@winded.cz' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8');
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(array('error' => 'Chyba při zpracování objednávky: ' . $e->getMessage()));
        exit;
    }

    if (empty($ordered) && !empty($errors)) {
        http_response_code(400);
        echo json_encode(array('error' => implode(' ', $errors)));
        exit;
    }

    $response = array('status' => 'ok');
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    echo json_encode($response);
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

    // Zkontrolovat duplicitu
    $dupChk = $pdo->prepare("SELECT hodnoceni_id FROM Hodnoceni WHERE objednavka_id = :oid AND hodnotitel_id = :hid");
    $dupChk->execute(array(':oid' => $objednavkaId, ':hid' => $_SESSION['user']['id']));
    if ($dupChk->fetch()) {
        http_response_code(409);
        echo json_encode(array('error' => 'Tuto objednávku jste již hodnotili.'));
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
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(array('error' => 'Pro psaní zpráv se přihlaste.'));
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $text = isset($body['text']) ? trim($body['text']) : '';
    $user = $_SESSION['user']['jmeno'];
    $uid  = $_SESSION['user']['id'];

    if (!$text) {
        http_response_code(400);
        echo json_encode(array('error' => 'Prázdná zpráva.'));
        exit;
    }

    $ins = $pdo->prepare(
        "INSERT INTO ChatZprava (uzivatel_id, zprava) VALUES (:uid, :zprava)"
    );
    $ins->execute(array(':uid' => $uid, ':zprava' => $text));

    $time = date('H:i');
    echo json_encode(array('status' => 'ok', 'user' => $user, 'text' => $text, 'time' => $time));
    exit;
}

http_response_code(404);
echo json_encode(array('error' => 'Unknown action'));