---
type: documentation
icon: heroicon-o-paint-brush
title: Disegnare le mappe
order: 3
---

La scheda **Mappa** del modulo del territorio contiene una mappa interattiva su cui disegnare i confini del territorio.

## Trovare la zona

Una nuova mappa parte con uno zoom molto ridotto. Per raggiungere l'area del territorio:

- clicca il pulsante di **posizione** (segnaposto rosso, in basso a destra) per centrare la mappa sulla tua posizione
  attuale (la prima volta il browser chiede il permesso), oppure
- usa **+** / **−** o la rotellina del mouse e trascina la mappa nel punto giusto.

## La barra degli strumenti

![Barra degli strumenti della mappa](/docs/user-documentation/it/territories/assets/territory-map-toolbar.webp)

1. **Zoom avanti / indietro**.
2. **Schermo intero**: ingrandisce la mappa, comodo per disegnare territori grandi.
3. **Draw Polyline**: disegna una linea (es. una via da lavorare).
4. **Draw Rectangle**: clicca e trascina per disegnare un rettangolo.
5. **Draw Polygons**: lo strumento usato per la maggior parte dei territori (vedi sotto).
6. **Draw Text**: aggiunge un testo sulla mappa.
7. **Edit Layers**: trascina i vertici delle forme esistenti per correggerle.
8. **Cut Layers**: disegna una forma per ritagliarla da una esistente (es. per escludere un parco).
9. **Remove Layers**: clicca una forma per eliminarla.

## Disegnare un poligono

1. Clicca **Draw Polygons** (5).
2. Clicca sulla mappa per posizionare il primo vertice, poi clicca su ogni angolo del territorio, seguendo le strade.
3. Per chiudere la forma clicca di nuovo il primo vertice, oppure clicca **Finish**. **Remove Last Vertex** annulla
   l'ultimo clic, **Cancel** scarta la forma.
4. Salva il territorio.

![Disegno di un poligono](/docs/user-documentation/it/territories/assets/territory-draw-polygon.webp)

> 💡 **Suggerimento:** aumenta lo zoom prima di disegnare, così i vertici seguono le strade con precisione. Puoi sempre
> correggerli in seguito con **Edit Layers**.

Le forme disegnate vengono salvate in formato GeoJSON (mostrato nel campo **Geojson** sotto la mappa). Se hai molte
mappe già pronte, puoi caricarle tutte insieme con l'[importazione](importing), che accetta una colonna GeoJSON.
