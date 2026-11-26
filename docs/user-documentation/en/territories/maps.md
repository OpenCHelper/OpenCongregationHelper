---
type: documentation
icon: heroicon-o-paint-brush
title: Drawing maps
order: 3
---

The **Map** tab of the territory form contains an interactive map where you draw the boundaries of the territory.

## Finding the area

A new map opens on the area of the territories that already have a map (on those of its zone, when you create the
territory from the **Territories** tab of a [zone](zones)), or on the **Default map centre** of the
[settings](../administration/settings) while no territory has a map yet. To reach the area of the territory:

- click the **location** button (red pin, bottom right) to center the map on your current position (the browser asks
  for permission the first time), or
- zoom with **+** / **−** or the mouse wheel and drag the map to the right place.

## The toolbar

![Map toolbar](/docs/user-documentation/en/territories/assets/territory-map-toolbar.webp)

1. **Zoom in / zoom out**.
2. **Full screen**: enlarges the map, handy when drawing large territories.
3. **Draw Polyline**: draws a line (e.g. a street to work).
4. **Draw Rectangle**: click and drag to draw a rectangle.
5. **Draw Polygons**: the tool used for most territories (see below).
6. **Edit Layers**: drag the vertices of the existing shapes to fix them.
7. **Cut Layers**: draw a shape to cut it out of an existing one (e.g. to exclude a park).
8. **Remove Layers**: click a shape to delete it.

## Drawing a polygon

1. Click **Draw Polygons** (5).
2. Click on the map to place the first vertex, then click on each corner of the territory, following the streets.
3. To close the shape click the first vertex again, or click **Finish**. **Remove Last Vertex** undoes the last click,
   **Cancel** discards the shape.
4. Save the territory.

![Drawing a polygon](/docs/user-documentation/en/territories/assets/territory-draw-polygon.webp)

> 💡 **Tip:** zoom in before drawing, so that the vertices follow the streets precisely. You can always fix them later
> with **Edit Layers**.

> ℹ️ **Note:** the areas and the lines are shown wherever the territory is used: on the assignment page, on the public
> page of a [shared assignment](../assignments/sharing) and on the [dashboard](../basics/dashboard) map. Text labels
> drawn with earlier versions are shown only in the map of the territory itself: remove them with **Remove Layers**,
> and write the directions in the **Notes** instead.

The drawn shapes are stored in GeoJSON format (shown in the **Geojson** field below the map). Territories with many
existing maps can also be loaded in one go with the [import](importing), which accepts a GeoJSON column.
