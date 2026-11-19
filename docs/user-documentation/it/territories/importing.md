---
type: documentation
icon: heroicon-o-arrow-up-tray
title: Importare i territori
order: 4
---

Quando inizi a usare l'applicazione probabilmente hai già un elenco di territori, ad esempio in un foglio di calcolo.
Invece di crearli uno alla volta, puoi importarli da un **file CSV**.

## Preparare il file

Clicca **Scarica un file CSV di esempio** nella finestra di importazione per ottenere un modello. Ogni riga è un
territorio, con queste colonne:

| Colonna | Obbligatoria | Contenuto |
|---------|--------------|-----------|
| **Name** | Sì | Nome del territorio, es. `CS-01`. |
| **Territory zone** | Sì | Nome della zona. Se non esiste una zona con quel nome, viene creata. |
| **Is commercial?** | Sì | `1`/`0`, `yes`/`no` o `true`/`false`. |
| **Description** | No | Di solito l'elenco delle vie. |
| **Notes** | No | Note per chi lavora il territorio. |
| **Image path** | No | Link assoluto (`http://` o `https://`) a un'immagine del territorio. |
| **Geojson** | No | La mappa del territorio, come `FeatureCollection` GeoJSON. |

> 💡 **Suggerimento:** i programmi di fogli di calcolo (LibreOffice Calc, Excel, Fogli Google) possono salvare un foglio
> in formato CSV con *File → Salva con nome / Scarica → CSV*.

## Importare

1. Apri **Territori** e clicca **Importa territori**.
2. Clicca **Carica un file CSV** e scegli il tuo file.
3. Controlla come le colonne del file vengono associate ai campi del territorio e correggi l'associazione se serve.
4. Scegli le opzioni:
   - **Aggiorna record esistenti**: quando esiste già un territorio con lo stesso nome **nella stessa zona**, viene
     aggiornato invece di essere segnalato come duplicato;
   - **Come vuoi che vengano caricate le immagini?**: **Lascia link** salva il link così com'è, **Salva una copia**
     scarica l'immagine nell'applicazione, così continua a funzionare anche se il link originale smette di funzionare.
5. Clicca **Importa**.

![Finestra di importazione](/docs/user-documentation/it/territories/assets/territory-import.webp)

L'importazione avviene in background. Al termine ricevi una notifica con il numero di righe importate e, se alcune
righe non sono state importate, un link per scaricarle con il motivo dell'errore, così da correggerle e importarle di
nuovo.

## Regole e limiti

- Una riga viene scartata se esiste già un territorio con lo stesso nome nella stessa zona (anche tra quelli eliminati),
  a meno che non sia selezionato **Aggiorna record esistenti**.
- Con **Salva una copia**, le immagini vengono scaricate solo da indirizzi pubblici, devono essere vere immagini
  (`image/*`) e pesare al massimo 10 MB.
