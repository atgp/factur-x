PHP Factur-X library
------------------

Factur-X is a Franco-German e-invoicing standard which complies with the European e-invoicing standard [EN 16931](https://ec.europa.eu/digital-building-blocks/wikis/display/DIGITAL/Obtaining+a+copy+of+the+European+standard+on+eInvoicing). 
The Factur-X specifications are available on the [FNFE-MPE](http://fnfe-mpe.org/factur-x/) website in English and French. 
The Factur-X standard is also called [ZUGFeRD 2.2](https://www.ferd-net.de/standards/zugferd-2.2/zugferd-2.2.html) in Germany.

This library enable you to manage your Factur-X PDF invoices files :
* **Generate Factur-X PDF invoice** from regular PDF invoice and Factur-X XML file
* **Extract Factur-X XML** from Factur-X PDF invoice 
* **Validate Factur-X XML** against the official Factur-X XML Schema Definition

Table of contents:
------------------

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [License](#license)
- [Changelog](#changelog)
- [Contributing](CONTRIBUTING.md)

Requirements
------------
- PHP 7.4+
- Composer
- [FPDI](https://github.com/Setasign/FPDI) (MIT License)
- [Smalot](https://github.com/smalot/pdfparser) (LGPL License)


Installation
------------

#### Install with Composer

```bash
composer require atgp/factur-x
```

Usage
-----
You can see the code from test page from "tests" directory, also here some simple examples of implementation :

```php
<?php
// Include or autoload (with Composer) all library classes

// Generates Factur-X PDF invoice from PDF and Factur-X XML
$writer = new \Atgp\FacturX\Writer();
$facturxPdf = $writer->generate($pdf, $facturxXml);

// Extracts Factur-X XML
$reader = new \Atgp\FacturX\Reader();
$facturxXml = $reader->extractXML($facturxPdf);

// Validates Factur-X XML against official Factur-X XML Schema Definition 
$validator = new \Atgp\FacturX\XsdValidator();
if (false === ($isValid = $validator->validate($facturxXml)) {
    var_dump($validator->getErrors());
}
// ... or throw exceptions if error(s) are occurred
$validator->validateWithException($facturxXml);
```

More options are available, look at source code for more information.

License
-------
This project is licensed under MIT License

Changelog
---------

- v2.0.0 [BC] : 2023-11-06
  - Requires php 7.4+
  - Refactor classes to clarify uses
  - Simplify requirements for "smalot/pdfparser"
  - Import external links on generated factur-x pdf
- v1.1.0 : 2019-01-09
  - Upgrade Factur-x xsd to v1.0.06
  - Fix PDF-A compliance regarding endobj and ICC profile
- v1.0.0 : 2019-01-09
  - Requires php 5.6+
  - First version of the library to read, check and write factur-x documents