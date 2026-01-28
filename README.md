PHP Factur-X library
------------------

Factur-X is a Franco-German e-invoicing standard which complies with the European e-invoicing standard [EN 16931](https://ec.europa.eu/digital-building-blocks/wikis/display/DIGITAL/Obtaining+a+copy+of+the+European+standard+on+eInvoicing). 
The Factur-X specifications are available on the [FNFE-MPE](http://fnfe-mpe.org/factur-x/) website in English and French. 
The Factur-X standard is also called [ZUGFeRD](https://www.ferd-net.de/download-zugferd) in Germany.

This library enable you to manage your Factur-X PDF invoices files :
* **Generate Factur-X PDF invoice** from regular PDF invoice and Factur-X XML file
    * Using [setasign\Fpdi](https://github.com/Setasign/FPDI) 
* **Extract Factur-X XML** from Factur-X PDF invoice
    * Using [Smalot\PdfParser](https://github.com/smalot/pdfparser)
* **Validate Factur-X XML** against the official Factur-X XML Schema Definition
    * Using PHP native libxml functions

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

- v3.0.0 : 2026-01-28
    - (reader/validator/writer) Throw custom exception to facilitate exception handling
- v2.5.0 : 2026-01-09
    - (validator) Upgrade Factur-x XSD to v1.08 — Applicable from 15 January 2026
    - (validator) Handle all codes that can be used for credit notes
- v2.4.1 : 2025-11-18
  - (reader) Remove backtrace from thrown exception message
- v2.4.0 : 2025-09-24
  - (validator) Upgrade Factur-x XSD to v1.07.3
- v2.3.1 : 2025-03-14
  - (writer) Fix binary content indicator in header
- v2.3.0 : 2024-12-11
  - (ci) Add Github CI : php-stan for PHP versions between 7.4 and 8.4
- v2.2.1 : 2024-10-28
  - (reader) Clarify reader extraction method
- v2.2.0 : 2024-10-28
  - (validator) Upgrade Factur-x XSD to v1.0.7 
- v2.1.0 : 2024-02-26
  - (reader) Allow to configure Smalot pdf parser
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
