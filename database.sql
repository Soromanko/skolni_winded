CREATE DATABASE IF NOT EXISTS marketplace
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE marketplace;

CREATE TABLE IF NOT EXISTS Uzivatel (
                                        uzivatel_id  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                                        jmeno        VARCHAR(100)    NOT NULL,
                                        prijmeni     VARCHAR(100)    NOT NULL DEFAULT '',
                                        email        VARCHAR(255)    NOT NULL UNIQUE,
                                        telefon      VARCHAR(20)     DEFAULT NULL,
                                        heslo        VARCHAR(255)    NOT NULL,
                                        PRIMARY KEY (uzivatel_id)
);

CREATE TABLE IF NOT EXISTS Kategorie (
                                         kategorie_id INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                                         nazev        VARCHAR(150)    NOT NULL,
                                         PRIMARY KEY (kategorie_id)
);

CREATE TABLE IF NOT EXISTS Polozka (
                                       polozka_id   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                                       kategorie_id INT UNSIGNED    NOT NULL,
                                       nazev        VARCHAR(255)    NOT NULL,
                                       model        VARCHAR(150)    DEFAULT NULL,
                                       znacka       VARCHAR(150)    DEFAULT NULL,
                                       isbn         VARCHAR(20)     DEFAULT NULL,
                                       popis        TEXT            DEFAULT NULL,
                                       stav         ENUM('novy','pouzity','poskozeny') NOT NULL DEFAULT 'pouzity',
                                       PRIMARY KEY (polozka_id),
                                       CONSTRAINT fk_polozka_kategorie
                                           FOREIGN KEY (kategorie_id) REFERENCES Kategorie (kategorie_id)
                                               ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Nabidka (
                                       nabidka_id   INT UNSIGNED        NOT NULL AUTO_INCREMENT,
                                       uzivatel_id  INT UNSIGNED        NOT NULL,
                                       polozka_id   INT UNSIGNED        NOT NULL,
                                       cena         DECIMAL(10, 2)      NOT NULL,
                                       datum        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                       stav_nabidky ENUM('aktivni','prodano','zruseno') NOT NULL DEFAULT 'aktivni',
                                       PRIMARY KEY (nabidka_id),
                                       CONSTRAINT fk_nabidka_uzivatel
                                           FOREIGN KEY (uzivatel_id) REFERENCES Uzivatel (uzivatel_id)
                                               ON UPDATE CASCADE ON DELETE RESTRICT,
                                       CONSTRAINT fk_nabidka_polozka
                                           FOREIGN KEY (polozka_id) REFERENCES Polozka (polozka_id)
                                               ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Fotka (
                                     fotka_id     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                                     nabidka_id   INT UNSIGNED    NOT NULL,
                                     url          VARCHAR(500)    NOT NULL,
                                     poradi       TINYINT UNSIGNED NOT NULL DEFAULT 0,
                                     PRIMARY KEY (fotka_id),
                                     CONSTRAINT fk_fotka_nabidka
                                         FOREIGN KEY (nabidka_id) REFERENCES Nabidka (nabidka_id)
                                             ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Objednavka (
                                          objednavka_id INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                                          nabidka_id    INT UNSIGNED   NOT NULL,
                                          kupujici_id   INT UNSIGNED   NOT NULL,
                                          jmeno         VARCHAR(100)   NOT NULL DEFAULT '',
                                          prijmeni      VARCHAR(100)   NOT NULL DEFAULT '',
                                          adresa        VARCHAR(300)   NOT NULL DEFAULT '',
                                          stav          ENUM('cekajici','zaplaceno','odeslano','dokonceno','zruseno')
                                                                       NOT NULL DEFAULT 'cekajici',
                                          vytvoreno     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                          PRIMARY KEY (objednavka_id),
                                          CONSTRAINT fk_objednavka_nabidka
                                              FOREIGN KEY (nabidka_id)  REFERENCES Nabidka  (nabidka_id)
                                                  ON UPDATE CASCADE ON DELETE RESTRICT,
                                          CONSTRAINT fk_objednavka_kupujici
                                              FOREIGN KEY (kupujici_id) REFERENCES Uzivatel (uzivatel_id)
                                                  ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Add objednavka_id to Nabidka per RS diagram (FK)(O) - nullable FK
ALTER TABLE Nabidka
    ADD COLUMN objednavka_id INT UNSIGNED NULL AFTER stav_nabidky,
    ADD CONSTRAINT fk_nabidka_objednavka
        FOREIGN KEY (objednavka_id) REFERENCES Objednavka (objednavka_id)
            ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS Hodnoceni (
                                         hodnoceni_id  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                                         objednavka_id INT UNSIGNED   NOT NULL,
                                         hodnotitel_id INT UNSIGNED   NOT NULL,
                                         hodnoceny_id  INT UNSIGNED   NOT NULL,
                                         komentar      TEXT           DEFAULT NULL,
                                         rating        TINYINT UNSIGNED NOT NULL,
                                         PRIMARY KEY (hodnoceni_id),
                                         CONSTRAINT rating_range CHECK (rating BETWEEN 1 AND 5),
                                         CONSTRAINT fk_hodnoceni_objednavka
                                             FOREIGN KEY (objednavka_id) REFERENCES Objednavka (objednavka_id)
                                                 ON UPDATE CASCADE ON DELETE CASCADE,
                                         CONSTRAINT fk_hodnoceni_hodnotitel
                                             FOREIGN KEY (hodnotitel_id) REFERENCES Uzivatel (uzivatel_id)
                                                 ON UPDATE CASCADE ON DELETE RESTRICT,
                                         CONSTRAINT fk_hodnoceni_hodnoceny
                                             FOREIGN KEY (hodnoceny_id)  REFERENCES Uzivatel (uzivatel_id)
                                                 ON UPDATE CASCADE ON DELETE RESTRICT,
                                         CONSTRAINT uq_hodnoceni_order_reviewer
                                             UNIQUE (objednavka_id, hodnotitel_id)
);

-- Starý globální chat nahrazen soukromým chatem mezi prodejcem a kupujícím
CREATE TABLE IF NOT EXISTS ChatZprava (
                                          zprava_id    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                                          nabidka_id   INT UNSIGNED    NOT NULL,
                                          odesilatel_id INT UNSIGNED   NOT NULL,
                                          prijemce_id  INT UNSIGNED    NOT NULL,
                                          zprava       TEXT            NOT NULL,
                                          cas          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                          PRIMARY KEY (zprava_id),
                                          CONSTRAINT fk_zprava_nabidka
                                              FOREIGN KEY (nabidka_id) REFERENCES Nabidka (nabidka_id)
                                                  ON UPDATE CASCADE ON DELETE CASCADE,
                                          CONSTRAINT fk_zprava_odesilatel
                                              FOREIGN KEY (odesilatel_id) REFERENCES Uzivatel (uzivatel_id)
                                                  ON UPDATE CASCADE ON DELETE CASCADE,
                                          CONSTRAINT fk_zprava_prijemce
                                              FOREIGN KEY (prijemce_id) REFERENCES Uzivatel (uzivatel_id)
                                                  ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Notifikace (
                                          notifikace_id INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                                          uzivatel_id   INT UNSIGNED   NOT NULL,
                                          typ           ENUM('prodej','nakup','hodnoceni','zprava') NOT NULL,
                                          text          TEXT           NOT NULL,
                                          precteno      TINYINT(1)     NOT NULL DEFAULT 0,
                                          vytvoreno     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                          PRIMARY KEY (notifikace_id),
                                          CONSTRAINT fk_notifikace_uzivatel
                                              FOREIGN KEY (uzivatel_id) REFERENCES Uzivatel (uzivatel_id)
                                                  ON UPDATE CASCADE ON DELETE CASCADE
);

-- Tabulka pro resetování hesla
CREATE TABLE IF NOT EXISTS PasswordReset (
                                             reset_id     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                                             uzivatel_id  INT UNSIGNED    NOT NULL,
                                             token        VARCHAR(64)     NOT NULL UNIQUE,
                                             expiry       DATETIME        NOT NULL,
                                             pouzito      TINYINT(1)      NOT NULL DEFAULT 0,
                                             PRIMARY KEY (reset_id),
                                             CONSTRAINT fk_reset_uzivatel
                                                 FOREIGN KEY (uzivatel_id) REFERENCES Uzivatel (uzivatel_id)
                                                     ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX idx_polozka_kategorie   ON Polozka    (kategorie_id);
CREATE INDEX idx_nabidka_uzivatel    ON Nabidka    (uzivatel_id);
CREATE INDEX idx_nabidka_polozka     ON Nabidka    (polozka_id);
CREATE INDEX idx_nabidka_stav        ON Nabidka    (stav_nabidky);
CREATE INDEX idx_fotka_nabidka       ON Fotka      (nabidka_id);
CREATE INDEX idx_objednavka_nabidka  ON Objednavka (nabidka_id);
CREATE INDEX idx_objednavka_kupujici ON Objednavka (kupujici_id);
CREATE INDEX idx_hodnoceni_hodnoceny ON Hodnoceni  (hodnoceny_id);
CREATE INDEX idx_chat_nabidka        ON ChatZprava (nabidka_id);
CREATE INDEX idx_chat_cas            ON ChatZprava (cas);
CREATE INDEX idx_notifikace_uzivatel ON Notifikace (uzivatel_id);
CREATE INDEX idx_reset_token         ON PasswordReset (token);