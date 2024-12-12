# Registro Territori

[<img alt="Deployed with FTP Deploy Action" src="https://img.shields.io/badge/Deployed With-FTP DEPLOY ACTION-%3CCOLOR%3E?style=for-the-badge&color=d00000">](https://github.com/SamKirkland/FTP-Deploy-Action)

**Gestionale dei territori** per la congregazione di **Ciampino**.

## Supporto

Per ottenere supporto è preferibile [**aprire un Issue**](https://github.com/LukeSavefrogs/registro-territori/issues) nel tab corrispondente o in alternativa [inviare una mail](mailto:lucasalvarani99@gmail.com?subject=TerritoriCiampino).

## Changelog

Per la lista completa delle modifiche consultare il [**Changelog**](CHANGELOG.md).

## Istruzioni per lo sviluppo

### Docker

> **Q**: `docker compose` o `docker-compose`?  
> **A**: Usare sempre [**`docker compose`**](https://stackoverflow.com/a/66516826/8965861) dove possibile

- Start:

    ```shell
    docker compose up -d
    ```

- Stop:

    ```shell
    docker compose down
    ```

A questo punto saranno accessibili:

- [Sito](http://localhost:8080/) _(porta **8080**)_
- [PhpMyAdmin](http://localhost:8090/) _(porta **8090**)_

```text
PSR-4 autoloading configured. Use "namespace Lukesavefrogs\RegistroTerritori;" in src/
Include the Composer autoloader with: require 'vendor/autoload.php';
```

### Problemi

#### `mysqli::__construct(): (HY000/1044): Access denied for user`

Se si dovesse incontrare un errore simile a questo:

```html
<br />
<b>Warning</b>:  mysqli::__construct(): (HY000/1044): Access denied for user 'territoriciampino'@'%' to database 'my_territoriciampino' in <b>/var/www/html/src/lib/db_connect.php</b> on line <b>20</b><br />
Access denied for user 'territoriciampino'@'%' to database 'my_territoriciampino'<br />
<b>Fatal error</b>:  Uncaught Error: mysqli object is already closed in /var/www/html/src/lib/db_connect.php:27
Stack trace:
#0 /var/www/html/src/lib/db_connect.php(27): mysqli-&gt;set_charset('utf8')
#1 /var/www/html/src/lib/set_login.php(3): Database::getConnection()
#2 {main}
thrown in <b>/var/www/html/src/lib/db_connect.php</b> on line <b>27</b>
<br />
```

Provare ad eseguire il seguente comando da console `mysql` (_come utente `root`_) all'interno del container database:

```sql
GRANT ALL PRIVILEGES on my_territoriciampino.* To territoriciampino;
```

## Online resources

- [Google Fonts](https://fonts.google.com/icons)
- [MaterializeCSS documentation](https://materializecss.com/)
