# Database

While developing, the database is accessible via the [PHPMyAdmin interface](http://localhost:8090/).

## Snippet

### Assegnazione massiva

```sql
INSERT INTO storico_uscite (id_territorio, assegnato_a, data_uscita, tipo_campagna)
SELECT 
    `id`            AS id_territorio,
    158             AS assegnato_a,
    CURRENT_DATE()  AS data_uscita,
    2               AS tipo_campagna
FROM `territori`
WHERE 
    `id_territorio` IN (0,1,2) AND
    `rimosso` = 0;
```

### Elenco dei territori fatti durante il 2020

```sql
SELECT 
    ter.id_territorio,
    zone.nome_zona,
    camp.nome_campagna,
    sto_usc.data_uscita,
    sto_usc.data_rientro,
    Datediff(sto_usc.data_rientro, sto_usc.data_uscita) as giorni_fuori
FROM 
    storico_uscite as sto_usc
LEFT JOIN territori as ter
    ON sto_usc.id_territorio = ter.id
LEFT JOIN proclamatori as proc
    ON sto_usc.assegnato_a = proc.id
LEFT JOIN tipo_campagne as camp
    ON sto_usc.tipo_campagna = camp.id
LEFT JOIN zone
    ON ter.zona = zone.id
WHERE 
    Year(sto_usc.data_uscita) = 2020  AND
    sto_usc.eliminato = 0             AND
    ter.rimosso = 0
ORDER BY sto_usc.data_uscita, ter.id_territorio, giorni_fuori
```

### Restituisci tutti i territori ancora NON rientrati che

```sql
SELECT ter.id_territorio, CONCAT(proc.cognome, ' ', proc.nome) as utente, sto_usc.data_uscita, camp.nome_campagna 
FROM
    storico_uscite as sto_usc
LEFT JOIN territori as ter
    ON sto_usc.id_territorio = ter.id
LEFT JOIN proclamatori as proc
    ON sto_usc.assegnato_a = proc.id
LEFT JOIN tipo_campagne as camp
    ON sto_usc.tipo_campagna = camp.id
WHERE 
    data_rientro IS NULL    AND 
    sto_usc.eliminato = 0   AND
    camp.nome_campagna != "Campagna Commemorazione"
ORDER BY utente
```

### Percentuale di percorrenza totale dell'ultimo anno teocratico

Periodo di campionamento: dal 1 Settembre dell'anno passato ad oggi

```sql
SELECT 
    IF (ter.territorio_commerciale, "SI", "NO") as territorio_commerciale, 
    COUNT(DISTINCT(ter.id)) as territori_usciti, 
    (
        SELECT 
            Count(id) as totale 
        FROM 
            territori 
        WHERE 
            rimosso = 0 
            AND territori.territorio_commerciale = ter.territorio_commerciale
    ) as territori_totali, 
    CONCAT(
        ROUND((
            Count(DISTINCT(ter.id_territorio)) * 100 / (
                SELECT
                    Count(id) as totale 
                FROM 
                    territori 
                WHERE 
                    rimosso = 0 
                    AND territori.territorio_commerciale = ter.territorio_commerciale
            )
        ), 2),
        '%'
    ) as percentuale_percorrenza, 
    CONCAT(
        DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), "%Y-09-01"),
        ' / ',
        CURDATE()
    ) as intervallo_campionamento 
FROM 
    (
        SELECT 
            Count(id) as totale 
        FROM 
            territori 
        WHERE 
            rimosso = 0
    ) as totale_terr, 
    `storico_uscite` as uscite 
LEFT JOIN `proclamatori` as proc
    ON uscite.assegnato_a = proc.id 
LEFT JOIN `territori` as ter
    ON uscite.id_territorio = ter.id 
WHERE 
    uscite.`eliminato` = 0
    AND uscite.`data_uscita` BETWEEN DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), "%Y-09-01") AND CURDATE()
    AND ter.`rimosso` = 0 
GROUP BY 
    ter.`territorio_commerciale`
```

### Elenco territori usciti nell'ultimo anno teocratico, media del periodo di percorrenza di ogni territorio e lista delle campagne in cui è stato lavorato

Periodo di campionamento: dal 1 Settembre dell.'anno passato ad oggi

```sql
SELECT 
    ter.id_territorio as territorio,
    IF (ter.territorio_commerciale, "SI", "NO") as territorio_commerciale,
    CONCAT(ROUND(AVG(DATEDIFF(uscite.data_rientro, uscite.data_uscita))), ' giorni') as percorrenza_media,
    COUNT(uscite.id) as totale_uscite,
    CONCAT(DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), "%Y-09-01"), ' / ', CURDATE()) as intervallo_campionamento,
    GROUP_CONCAT(DISTINCT camp.nome_campagna ORDER BY camp.nome_campagna SEPARATOR ", ") AS campagne_lavorate
FROM 
    `storico_uscite` as uscite
LEFT JOIN `proclamatori` as proc
    ON uscite.assegnato_a = proc.id
LEFT JOIN `territori` as ter
    ON uscite.id_territorio = ter.id
LEFT JOIN `tipo_campagne` as camp
    ON uscite.tipo_campagna = camp.id
WHERE 
    uscite.`eliminato` = 0 
    AND uscite.data_uscita BETWEEN DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), "%Y-09-01") AND CURDATE()
    AND ter.rimosso = 0
    AND uscite.data_rientro IS NOT NULL
GROUP BY 
    ter.id_territorio,
    ter.territorio_commerciale
ORDER BY 
    ter.territorio_commerciale,
    ter.id_territorio
```
