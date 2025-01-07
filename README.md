# rs-invoice-qrcode-reader
Reads QR codes from Serbian fiscal invoice in pdf or image formats and gets json data

It is using API from here: https://tap.suf.purs.gov.rs/help/view/377035109/%D0%A1%D0%BA%D0%B5%D0%BD%D0%B8%D1%80%D0%B0%D1%9A%D0%B5-%D1%80%D0%B0%D1%87%D1%83%D0%BD%D0%B0-%D1%81%D0%B0-JSON-%D0%BE%D0%B4%D0%B3%D0%BE%D0%B2%D0%BE%D1%80%D0%BE%D0%BC/sr-Cyrl-RS 

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)

## Requirements

Following is required:

- PHP 7.4+ 
- php-curl extension
- xpdf and zbar-tools installed on server (optional)
- mpdf (optional)

## Installation

Use [Composer](https://getcomposer.org/).
To [add a dependency](https://getcomposer.org/doc/04-schema.md#package-links) to your project.

Run the following to use the latest stable version
```sh
composer require tuckdesign/rs-invoice-qrcode-reader
```

## Getting started

The following is a basic usages examples of the RSQRCodeReader library.

```php
<?php
require_once 'vendor/autoload.php';

$qrReader = new \tuckdesign\RSQRCodeReader();
$data = $qrReader->readFromURL('<url read from QR Code on invoice>');
$data = $qrReader->readFromPDF('invoice.pdf'); // requires xpdf and zbar-tools installed on server and exec permissions for php
$data = $qrReader->readFromImage('invoice.png'); // requires zbar-tools installed on server and exec permissions for php
$mpdf = $qrReader->pdfURL(); // requires mpdf

```
