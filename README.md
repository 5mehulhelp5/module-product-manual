# GDMexico Product Manual

Módulo Magento 2 para cargar y mostrar un PDF de manual de armado por producto.

## Compatibilidad declarada

- PHP 7.4 y PHP 8.x
- Magento Framework 102.x y 103.x
- Magento 2.3.x y Magento 2.4.x, sujeto a la combinación de PHP soportada por cada versión de Magento

## Instalación mediante Composer

Agrega el repositorio VCS al `composer.json` del proyecto Magento:

```json
"product-manual": {
    "type": "vcs",
    "url": "https://github.com/Grupo-Dico/module-product-manual.git"
}
```

Instala una versión etiquetada:

```bash
composer require gdmexico/module-product-manual:^1.2
bin/magento module:enable GDMexico_ProductManual
bin/magento setup:upgrade
bin/magento cache:flush
```

En producción también ejecuta:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

## Desarrollo

Para instalar directamente la rama principal:

```bash
composer require gdmexico/module-product-manual:dev-main
```

## Publicación de una versión

```bash
git add .
git commit -m "Add PHP 7.4 compatibility"
git push origin main
git tag -a v1.2.0 -m "Release 1.2.0"
git push origin v1.2.0
```
