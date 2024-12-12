# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.3.12] - 2024-07-15

### Changed

- Aggiunto avvertimento in caso sia presente una nota nel dettaglio territorio in modo da richiamare l'attenzione alla sua lettura.

### Removed

- Rimosso pulsante FAB (Floating Action Button) per la modifica dell'assegnazione. Rimpiazzato dal già esistente corrispettivo raggiungibile dal menu a tendina in alto a destra.

## [v1.3.11] - 2024-07-15

### Changed

- Come richiesto nel dettaglio territorio ora la nota viene mostrata per intero, senza dover prima cliccare su un pulsante.

## [v1.3.10] - 2024-03-19

### Changed

- Ora cliccando su un settore del grafico a torta la tabella risultante conterrà ID territorio e zona nel caso di territori "dentro" (durante la campagna sia da assegnare che completati), mentre ID territorio e assegnazione nel caso di territori "fuori" (durante la campagna sia da far rientrare che in lavorazione).

## [v1.3.9] - 2024-03-18

### Changed

- Ora cliccando su un settore del grafico a torta la tabella risultante conterrà ID territorio e zona (non più ID e assegnazione).

### Fixed

- Ora il titolo della pagina della lista dei territori viene aggiornato correttamente all'applicazione dei filtri.

## [v1.3.8] - 2024-03-18

### Added

- Ora quando la lista dei territori rileva che la navigazione precedente proviene da un dettaglio territorio o dalla lista stessa, vengono ripristinati i filtri e l'ordinamento impostati in precedenza. Inoltre, viene effettuato uno "scroll" fino alla riga che aveva causato la navigazione precedente. Risolve issue [#23](https://github.com/LukeSavefrogs/registro-territori/issues/23).

## [v1.3.7] - 2024-03-18

### Fixed

**Home**:

- Ora durante la campagna eventuali territori percorsi nella stessa campagna dell'anno precedente (es. un territorio percorso durante la commemorazione 2023 che viene rilavorato per la prima volta durante la commemorazione 2024) figurano correttamente come "Da lavorare" e non più come "Completati".
- Corretto bug sporadico che causava la visualizzazione doppia della tabella nel dialog quando si cliccava su una sezione del grafico a torta e poi si cliccava su un'altra diversa.
- Ora durante la percorrenza "classica" i territori "da far rientrare" non figurano più come dentro, ma come fuori.

## [v1.3.6] - 2024-03-11

### Changed

- Corretta regressione per cui i nomi dell'ultima assegnazione nella lista territori comparivano per intero. Ora inoltre è possibile cercare tramite il filtro anche se il nome è troncato (è possibile cercare `Rossi` anche se nella tabella viene mostrato come `Ros.`).

## [v1.3.5] - 2024-03-11

### Added

- Aggiunta API per creare/modificare/eliminare utenti. Alla creazione di un utente, quest'ultimo riceve una mail di notifica.
- Ora nel dettaglio territorio viene mostrata l'ora di uscita/riconsegna del territorio.

### Changed

- Rinominati campi del grafico a torta della pagina Home. Ora `Dentro` viene mostrato come `Completati`, `Fuori` come `In lavorazione` e `Non assegnati` come `Da lavorare`.

**Database:**

- Ora la connessione al database è parametrizzata in modo da usare il database corretto anche su altri domini Altervista.
- Cambiato tipo dei campi `data_uscita` e `data_rientro` da `DATE` a `DATETIME` in modo da permettere il salvataggio dell'orario.

### Removed

- Rimossa possibilità di modificare data di uscita di un territorio per evitare problemi di consistenza di dati.

### Fixed

- In seguito alla modifica sul campo del DB nella pagina "Lista Territori" potevano esserci territori con stato `-0gg`. Ora invece viene preso solo il valore assoluto e viene restituito errore se dovessero essere trovati territori con un numero negativo come stato.
- In seguito alla modifica dei campi `data_uscita` e `data_rientro` sul database, la chiamata per restituire la lista di assegnazioni non restituiva quelle avvenute entro N ore (dove N è la differenza di Timezone).

**Database:**

- Corretto **bug di compatibilità** causato dall'upgrade di MySQL (sul server di test) dalla versione 5.6 alla 8.0. Questa modifica prepara il terreno per l'upgrade di MySQL anche sul server di produzione.

## [v1.3.4.4] - 2023-08-14

### Fixed

- Corretto bug all'interno della pagina statistiche per colpa del quale non era possibile cambiare il periodo di percorrenza di analisi. Risolve issue [#26](https://github.com/LukeSavefrogs/registro-territori/issues/26)

## [v1.3.4.3] - 2023-08-14

### Added

- Aggiunta [icona](https://web.dev/learn/pwa/installation/#ios-and-ipados-installation) per la PWA su iOS.

## [v1.3.4.2] - 2023-08-13

### Added

- Aggiunto pulsante per l'installazione della PWA nella home page.

### Fixed

- Ora l'installazione della PWA funziona correttamente.

## [v1.3.4.1] - 2023-08-13

### Added

- Aggiunte shortcut al `manifest.json`

### Changed

- Modificati campi `name` e `description` nel `manifest.json`

## [v1.3.4] - 2023-08-13

### Added

- Aggiunta la possibilità di modificare Email, Nome e Cognome del proprio utente all'interno della pagina "Impostazioni utente".

### Removed

- Rimosse dipendenze locali in favore dei CDN.

### Changed

- Ora nella sidebar oltre a nome e cognome dell'utente loggato viene mostrata anche la sua email.
- Aggiornata dipendenza `MaterializeCSS` alla versione `1.2.2`.
- Scarica Material Icons da CDN anziché da locale.

### Fixed

- Corretto bug per cui nel grafico nella home page figurava una **campagna errata** in caso l'ultima uscita (con campagna diversa da quella corretta) fosse stata eliminata.

## [v1.3.3] - 2023-08-11

### Added

- Nuovo widget nella home page per mostrare le assegnazioni più recenti. Risolve issue [#5](https://github.com/LukeSavefrogs/registro-territori/issues/5)

## [v1.3.2.2] - 2023-08-10

### Removed

- Rimosso `console.log` lasciato erroneamente in fase di debug durante le modifiche alla Home.

## [v1.3.2.1] - 2023-08-10

### Changed

- Ora le percentuali vengono sempre mostrate sul grafico a torta nella homepage, anche in caso di testo che esce dal bordo del settore. Migliora issue [#16](https://github.com/LukeSavefrogs/registro-territori/issues/16).
- Aggiunta variabile `$APP_VERSION` nel file `index.php` per poter gestire centralmente la versione.

## [v1.3.2] - 2023-08-10

### Added

- Aggiunte percentuali fisse sul grafico a torta nella home page. Risolve issue [#16](https://github.com/LukeSavefrogs/registro-territori/issues/16).

### Changed

- Ora se si rimane con il cursore sopra un settore del grafico a torta nella home, questo verrà messo in risalto espandendosi leggermente.

### Removed

- Rimossi tooltip presenti di default su `Chart.js` dal grafico a torta nella home page.

## [v1.3.1] - 2023-08-10

### Changed

- Il grafico nella home page ora è racchiuso in una `card`, risultando più pulito e ordinato anche su schermi più grandi.
- Cambiato leggermente lo stile del titolo del grafico a torta nella Home (titolo diviso in titolo+sottotitolo).
- Aggiornata dipendenza `Chart.js` alla versione `4.3.3`.

### Fixed

- Risolto bug per cui la selezione della tipologia di campagna in fase di creazione di una nuova assegnazione tramite `select` falliva casualmente. Risolve issue [#11](https://github.com/LukeSavefrogs/registro-territori/issues/11).

## [v1.3.0] - 2023-08-10

### Added

- Aggiunto nuovo settore "Da far rientrare" al **grafico a torta** nella **Home**.
- Adesso dopo aver cliccato su una voce della tabella territori nella **Home**  (_mostrata cliccando su un settore del grafico a torta_) si verrà reindirizzati alla pagina di dettaglio del territorio corrispondente. Risolve issue [#4](https://github.com/LukeSavefrogs/registro-territori/issues/4).
- Automatizzate release tramite [GitHub Actions](https://github.com/LukeSavefrogs/registro-territori/actions/workflows/upload.yml).

### Changed

- Modificato titolo della tabella nella Home da "Dettaglio territori {settore}" (_es. `Dettaglio territori non assegnati`_) a "Territori {settore}" (_es. `Territori non assegnati`_).

### Security

- Rimosso bug che avrebbe permesso di fare injection di codice JS all'interno della home tramite il nome dell'assegnazione.

## [v1.2.0] - ????-??-??

### Added

- Aggiunta schermata di **modifica** delle **assegnazioni** passate

### Changed

- Modificato **export** Excel. Ora vengono esportate solo le **assegnazioni più recenti** di un'anno dalla data odierna
- Corretti **bug** minori

**Database:**

- Modificato **nome campagna**: `Campagna Assemblea` -> `Campagna Congresso`
