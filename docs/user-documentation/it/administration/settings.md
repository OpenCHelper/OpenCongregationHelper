---
type: documentation
icon: heroicon-o-adjustments-horizontal
title: Impostazioni
order: 5
---

Le pagine **Impostazioni** permettono ai super amministratori di cambiare le opzioni dell'applicazione senza modificare
il file `.env` sul server. Apri **Amministrazione → Impostazioni**: le pagine sono mostrate solo agli utenti con il
ruolo `super_admin`. Le opzioni sono raggruppate per area, con una pagina ciascuna nel menu a lato.

## Generali

![Impostazioni generali](/docs/user-documentation/it/administration/assets/settings-general.webp)

- **Indirizzo della Sala del Regno**: l'indirizzo della Sala del Regno della congregazione (facoltativo);
- **Logo**: mostrato nella barra in alto al posto del nome (al massimo 2 MB);
- **Colore principale**: il colore dei pulsanti, dei link e degli elementi selezionati, per gli utenti che non ne hanno
  scelto uno nelle proprie preferenze dell'interfaccia.

## Territori

![Impostazioni dei territori](/docs/user-documentation/it/administration/assets/settings-territories.webp)

- **Centro predefinito delle mappe**: dove si aprono le mappe finché nessun territorio ha una mappa, come
  `latitudine, longitudine` (es. `41.159063, -74.255188`). In seguito le mappe si aprono sui territori;
- **Mostra i dati personali nelle pagine condivise**: se attivo, la pagina pubblica di
  un'[assegnazione condivisa](../assignments/sharing) mostra anche il nome dell'assegnatario e le note del territorio.

In **Assegnazioni fuori da troppo tempo** i livelli sono elencati in ordine, ognuno con il suo colore, e la barra sotto
li disegna in scala mentre scrivi:

- i giorni dopo cui un'assegnazione ancora in corso è fuori da troppo tempo e poi persa: questi ultimi devono essere di
  più;
- il **giorno** e l'**ora** (nel fuso orario scelto nella pagina **Generali**) dei promemoria settimanali inviati agli
  assegnatari delle assegnazioni fuori da troppo tempo. Vengono inviati solo se a quell'ora lo scheduler è in
  esecuzione, come cron job o come processo sempre attivo (es. con Supervisor); su un hosting senza cron, il servizio
  esterno deve chiamarlo a quell'ora (vedi la [Guida al setup](../setup)).

## Campagne

![Impostazioni delle campagne](/docs/user-documentation/it/administration/assets/settings-campaigns.webp)

I giorni prima della fine da cui una campagna è **in scadenza** e **quasi scaduta**: questi ultimi devono essere meno.
Gli stati sono elencati in ordine, con i colori dei badge delle campagne, e la barra sotto li disegna in scala mentre
scrivi.

Clicca **Salva** per applicarle: hanno effetto subito, per tutti gli utenti.

## Valori predefiniti

Sotto ogni opzione, **Predefinito** mostra il suo valore prima che venga cambiato qui: per il centro delle mappe e i
dati personali, il valore impostato nel file `.env` da chi ha installato l'applicazione (vedi la [Guida al
setup](../setup)). Queste due opzioni continuano a seguire il file `.env` finché non vengono cambiate qui; per farne
seguire di nuovo il file `.env` a una, reimpostala al suo valore predefinito e salva. Il nome, la lingua e il fuso
orario dell'applicazione si impostano solo nel file `.env`.
