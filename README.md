# Registro Territori

[<img alt="Deployed with FTP Deploy Action" src="https://img.shields.io/badge/Deployed With-FTP DEPLOY ACTION-%3CCOLOR%3E?style=for-the-badge&color=d00000">](https://github.com/SamKirkland/FTP-Deploy-Action)

**Gestionale dei territori** per la congregazione di **Ciampino**.

## Supporto

Per ottenere supporto è preferibile [**aprire un Issue**](https://github.com/LukeSavefrogs/registro-territori/issues) nel tab corrispondente o in alternativa [inviare una mail](mailto:lucasalvarani99@gmail.com?subject=TerritoriCiampino).

## Changelog

Per la lista completa delle modifiche consultare il [**Changelog**](CHANGELOG.md).

## Istruzioni per lo sviluppo

### Docker

> **Q**: Should I use `docker compose` or `docker-compose`?  
> **A**: Always use [**`docker compose`**](https://stackoverflow.com/a/66516826/8965861) when possible

- Start:

    ```shell
    docker compose up --build --watch
    docker compose watch --detach
    ```

- Stop:

    ```shell
    docker compose down
    ```

A questo punto saranno accessibili:

- [Sito](http://localhost:8080/) _(porta **8080**)_
- [PhpMyAdmin](http://localhost:8090/) _(porta **8090**)_

> **Q**: I am on Windows and `Generating optimized autoload files` is taking too long  
> **A**: Add the project folder to Windows Defender (or your antivirus of choice) exclusions

### Maintenance mode

- **Enable** maintenance mode:
    ```shell
    $ php artisan down --retry=60 --with-secret --no-interaction --no-ansi -vvv 

       INFO  Application is now in maintenance mode.

       INFO  You may bypass maintenance mode via [http://localhost:8080//2diaCV8mVM8YCjSY].
    ```
- **Disable** maintenance mode:
    ```shell
    $ php artisan up

       INFO  Application is now live.
    ```

## Online resources

- [Google Fonts](https://fonts.google.com/icons)
- [MaterializeCSS documentation](https://materializecss.com/)
