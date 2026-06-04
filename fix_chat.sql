-- =====================================================================
--  OPRAVA CHATU A NOTIFIKACÍ NA ŽIVÉ DATABÁZI (InfinityFree / phpMyAdmin)
-- ---------------------------------------------------------------------
--  Spusť celé v phpMyAdmin -> vyber databázi -> záložka SQL -> vlož -> Proveď.
--
--  Proč: chat na hostingu dosud nefungoval, takže tabulka ChatZprava tam
--  může mít starou strukturu (např. bez sloupce prijemce_id z dob, kdy
--  byl chat "globální"). Tím padaly jak Zprávy (SELECT), tak odesílání
--  (INSERT) na chybu 500. Tohle tabulku srovná do správného stavu.
--
--  POZN.: ChatZprava se zahodí a vytvoří znovu. Jelikož chat dosud
--  nefungoval, NEPŘIJDEŠ o žádná data. Ostatních tabulek se to netýká.
-- =====================================================================

-- 1) ChatZprava – zahodit a vytvořit znovu se správnou strukturou
DROP TABLE IF EXISTS ChatZprava;

CREATE TABLE ChatZprava (
                            zprava_id     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
                            nabidka_id    INT UNSIGNED     NOT NULL,
                            odesilatel_id INT UNSIGNED     NOT NULL,
                            prijemce_id   INT UNSIGNED     NOT NULL,
                            zprava        TEXT             NOT NULL,
                            cas           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE INDEX idx_chat_nabidka ON ChatZprava (nabidka_id);
CREATE INDEX idx_chat_cas      ON ChatZprava (cas);

-- 2) Notifikace – zajistit, že enum 'typ' obsahuje hodnotu 'zprava'
--    (nezničí stávající notifikace, jen rozšíří povolené hodnoty)
ALTER TABLE Notifikace
    MODIFY COLUMN typ ENUM('prodej','nakup','hodnoceni','zprava') NOT NULL;