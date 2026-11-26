---
type: documentation
icon: heroicon-o-shield-check
title: Ruoli e permessi
order: 3
---

Ciò che ogni utente può vedere e fare dipende dai suoi **ruoli**. Un ruolo è un insieme di **permessi** con un nome.
Apri **Amministrazione → Ruoli** per gestirli.

![Elenco dei ruoli](/docs/user-documentation/it/administration/assets/roles-list.webp)

## Il super amministratore

Il ruolo **super_admin** (*Super Admin*) può fare tutto, comprese le azioni riservate a lui:

- assegnare i ruoli agli utenti;
- impersonare gli utenti;
- modificare le [impostazioni](settings);
- leggere i log dell'applicazione.

## Creare o modificare un ruolo

1. Clicca **Nuovo** (o **Modifica** su un ruolo esistente).
2. Inserisci il **Nome** (es. `publisher`). Lascia **Nome Guard** impostato su `web`.
3. Spunta i permessi nella scheda **Resources**. **Seleziona tutto** in ogni riquadro spunta tutti i permessi di
   quella risorsa; l'interruttore in alto li seleziona tutti.
4. Clicca **Salva**.

![Modifica di un ruolo](/docs/user-documentation/it/administration/assets/role-edit.webp)

## Significato dei permessi

Ogni risorsa ha i permessi delle azioni che offre:

| Permesso | Permette di | Risorse |
|----------|-------------|---------|
| **View Any** | aprire l'elenco di quella risorsa | tutte |
| **View** | aprire i dettagli di un record | tutte |
| **Create** / **Update** | creare, modificare i record | tutte (*Reparto* solo **Update**) |
| **Delete** | eliminare un record | tutte tranne *Reparto* e *Pubblicazioni periodiche* |
| **Delete Any** | eliminare insieme i record selezionati | Territorio, Zona, Assegnazione, Campagna, Utente, Poster, Richiesta, Tag, Ruolo |
| **Restore**, **Force Delete** (e le versioni *Any*) | ripristinare i record eliminati, eliminarli definitivamente | Territorio, Zona, Assegnazione, Campagna |
| **Reorder** | cambiare l'ordine dell'elenco | Pubblicazione |

*Utente* ha anche **Restore**, **Force Delete** (e le versioni *Any*), **Replicate** e **Reorder**, non usati al
momento. Il registro delle attività e quello delle email non hanno permessi: solo i super amministratori li leggono.

Alcuni permessi hanno un significato particolare:

- **Create** su *Territory Assignment* permette di **assegnare** i territori, **Update** permette di **riconsegnarli**;
- senza **View Any** su *Territory Assignment*, l'utente vede solo **le proprie** assegnazioni;
- **View Any** su *Territory* mostra le statistiche, la copertura del territorio e la mappa nella
  [dashboard](../basics/dashboard).

Le pagine non hanno permessi propri: tutti possono aprire il proprio [profilo](../basics/profile), e solo i super
amministratori le [impostazioni](settings) e i log dell'applicazione.

## Ruoli consigliati

| Ruolo | Permessi consigliati |
|-------|----------------------|
| **Proclamatore** | View / View Any su Territorio e Zona, View su Assegnazione. I proclamatori vedono i territori e solo le proprie assegnazioni. |
| **Servitore di territorio** | Tutto su Territorio, Zona, Assegnazione e Campagna, più View / View Any su Utente. |
| **Amministratore** | Il ruolo **super_admin**. |

> 💡 **Suggerimento:** dopo aver modificato un ruolo, [impersona](users) un utente con quel ruolo per verificare cosa
> vede.
