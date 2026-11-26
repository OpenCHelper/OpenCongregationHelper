---
type: documentation
icon: heroicon-o-rocket-launch
title: Configurare la congregazione
order: 1
---

Questa guida è per chi inizia a usare l'applicazione per la prima volta, dopo averla installata (vedi la
[Guida al setup](../setup)). Segui i passaggi nell'ordine.

## 1. Accedi come root

Accedi con l'utente root creato durante l'installazione: su una nuova installazione si apre la **configurazione
guidata**, in quattro passaggi:

1. **Installazione**: i controlli del server (gli stessi di `php artisan och:doctor`). Correggi ciò che è segnato come
   errore o avviso, invia un'**email di prova** per verificare che le email arrivino, e crea il collegamento dello
   storage o le chiavi degli assistenti IA mancanti con i loro pulsanti;
2. **Congregazione**: il nome dell'app, l'indirizzo della Sala del Regno, il logo e il colore principale;
3. **Amministratori**: le persone che gestiranno l'app con te. Diventano super amministratori e ricevono un'email per
   scegliere la password;
4. **Funzionalità**: gli [assistenti IA](../basics/profile), la [condivisione](../assignments/sharing) delle
   assegnazioni e i requisiti degli account.

Tutto si può cambiare in seguito dalle [Impostazioni](../administration/settings) e dagli
[utenti](../administration/users). Puoi anche **Saltare** la configurazione: finché non è completata o saltata, in cima
alle pagine viene mostrato un promemoria.

Poi apri il tuo [profilo](../basics/profile) per impostare il tuo nome, la lingua preferita e un contatto di emergenza.

## 2. Crea le zone

Apri **Zone** e crea una zona per ogni area del territorio della congregazione, scegliendo colori ben diversi (vedi
[Zone](../territories/zones)).

## 3. Carica i territori

- Se hai un elenco di territori, [importalo](../territories/importing) da un file CSV: le zone che non esistono ancora
  vengono create automaticamente.
- Altrimenti [creali](../territories/creating) uno alla volta.

Poi [disegna la mappa](../territories/maps) di ogni territorio, oppure allega la scansione della cartolina cartacea.

> 💡 **Suggerimento:** usa il filtro **Stato mappa interattiva** dell'elenco dei territori per trovare quelli ancora
> senza mappa.

## 4. Crea i ruoli e gli utenti

1. Crea i [ruoli](../administration/roles) che ti servono, es. *proclamatore* e *servitore di territorio*.
2. Crea gli [utenti](../administration/users): dai **Permetti accesso** e un ruolo a chi userà l'applicazione, e crea
   tutti gli altri come utenti virtuali.
3. [Impersona](../administration/users) un utente per ogni ruolo per verificare cosa vede.

## 5. Ricopia la situazione attuale

Per ogni territorio attualmente fuori, [assegnalo](../assignments/assigning) alla persona giusta. Usa
**Assegnazioni → Nuovo** se vuoi registrare anche la data di inizio reale o le assegnazioni passate dal registro
cartaceo. Le persone che possono accedere ricevono una notifica per ogni assegnazione in corso, non per quelle
registrate come già riconsegnate.

## 6. Organizza con i tag

Aggiungi dei [tag](../administration/tags) a territori e zone (es. *Condomini*, *Solo citofono*) per filtrarli e
cercarli più facilmente.

Hai finito: la [dashboard](../basics/dashboard) ora mostra la mappa di tutto il territorio.
