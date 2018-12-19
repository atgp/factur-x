PHP Factur-X library
------------------
Factur-X is the e-invoicing standard for France and Germany. The Factur-X specifications are available on the FNFE-MPE website. The Factur-X standard is also called ZUGFeRD 2.0 in Germany.

This library enable you to manage your Factur-X PDF invoices files :
* **Generate Factur-X PDF invoice** from regular PDF invoice and Factur-X XML file
* **Extract Factur-X XML** from Factur-X PDF invoice 
* **Check Factur-X XML** against the official Factur-X XML Schema Definition

Table of contents:
------------------

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [License](#license)
- [Contributing](CONTRIBUTING.md)

Requirements
------------
- Apache2
- PHP 5.6+
- Composer
- [FPDI](https://github.com/Setasign/FPDI) (MIT License)
- [Smalot](https://github.com/smalot/pdfparser) (LGPL License)


Installation
------------

#### Download with Composer

```bash
composer require atgp/factur-x
```

Usage
-----
You can see the code from test page from "tests" directory, also here some simple examples of implementation :
```php
<?php
// Include or autoload (with Composer) all library classes

// Generating Factur-X PDF invoice from PDF and Factur-X XML
$facturx = new Facturx();
$facturxPdf = $facturx->generateFacturxFromFiles($pdf, $facturxXml);

// Extract Factur-X XML
$facturx = new Facturx();
$facturxXml = $facturx->getFacturxXmlFromPdf($facturxPdf);

// Check Factur-X XML against official Factur-X XML Schema Definition 
$facturx = new Facturx();
$isValid = $facturx->checkFacturxXsd($facturxXml);
```
More options are available, look at source code for more informations

License
-------
This project is licensed under MIT License
