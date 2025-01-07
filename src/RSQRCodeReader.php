<?php

namespace tuckdesign;

class RSQRCodeReader
{

    private $url = false;
    public $pdfFontCoef = 0.6;
    private $data = false;

    /**
     * Reads QR code data from pdf defined by path
     *
     * Requires 'exec' permissions and installed xpdf and zbar-tools
     *
     * @param $path string path to PDF file
     * @return array JSON | false
     */
    public function readFromPDF($path) {
        $tmp = tempnam(sys_get_temp_dir(), "rsqrcode");
        unlink($tmp);
        mkdir($tmp);
        if (exec('pdfimages -j ' . escapeshellarg($path) . ' '.$tmp.DIRECTORY_SEPARATOR.'image')!==false) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp));
            while($iterator->valid()) {
                if (!$iterator->isDot() && $iterator->isReadable() && $iterator->current()->isFile() && $iterator->current()->getRealPath()) {
                    if (exec('zbarimg -q '.escapeshellarg($iterator->current()->getRealPath()).' > '.escapeshellarg($tmp.DIRECTORY_SEPARATOR.'tmp.txt'))==false) {
                        $result = trim(file_get_contents($tmp.DIRECTORY_SEPARATOR.'tmp.txt'));
                        if (!$result) {
                            $iterator->next();
                            continue;
                        }
                        $m = [];
                        if (preg_match("/QR-Code:(.*)$/mSU", $result, $m)) {
                            $url = $m[1];
                            return $this->readFromURL($url);
                        }
                    }
                }
                $iterator->next();
            }
        }
        return false;
    }

    /**
     * Reads QR code data from image defined by path
     *
     * Requires 'exec' permissions and installed zbar-tools
     *
     * @param $path string path to image file
     * @return array JSON | false
     */
    public function readFromImage($path) {
        $tmp = tempnam(sys_get_temp_dir(), "rsqrcode");
        if (exec('zbarimg -q '.escapeshellarg($path).' '.escapeshellarg($tmp))==false) {
            $result = trim(file_get_contents($tmp));
            if ($result) {
                $m = [];
                if (preg_match("/QR-Code:(.*)$/mSU", $result, $m)) {
                    $url = $m[1];
                    @unlink($tmp);
                    return $this->readFromURL($url);
                }
            }
        }
        @unlink($tmp);
        return false;
    }

    /**
     * Reads QR code data from url
     *
     * @param $url string
     * @return array JSON | false
     */
    public function readFromURL($url) {
        $this->url = $url;
        $this->data = false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Accept: application/json'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result=curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $ret = json_decode($result, true, JSON_UNESCAPED_UNICODE);
            $this->data = $ret;
            if ($ret) {
                return $ret;
            }
        }
        return false;
    }

    /**
     * Get invoice PDF from url
     *
     * Requires mpdf
     *
     * @param $url string
     * @param $withDetails boolean Should we add complete specs in PDF
     *
     * @return \Mpdf\Mpdf invoice pdf | false
     */
    public function pdfURL($url = false, $withDetails = true) {
        if (!$url) {
            $url = $this->url;
        }
        if (!$url) {
            return false;
        }
        $data = $this->data?$this->data:$this->readFromURL($url);
        if (!$data) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $html=curl_exec($ch);
        curl_close($ch);
        if ($html) {
            $dom = new \DOMDocument();
            try {
                $dom->loadHTML($html);
                $xpath = new \DOMXPath($dom);
                $PrintInvoice = $dom->getElementById('PrintInvoice');
                if ($PrintInvoice) {
                    if ($withDetails) {
                        $invoiceNumber = $data['invoiceResult']['invoiceNumber'];
                        $m = [];
                        $token = '';
                        if (preg_match("/viewModel\\.Token\\('(.*)'/mSU", $html, $m)) {
                            $token = $m[1];
                        }
                        if (!$token) {
                            return false;
                        }
                        $specTable = null;
                        foreach ($PrintInvoice->childNodes as $child) {
                            $list = $xpath->query('//div/h3');
                            if (!empty($list)) {
                                foreach ($list as $el) {
                                    $elHTML = $this->getInnerHTML($el);
                                    if (strpos($elHTML, 'Спецификација рачуна')!==false) {
                                        $specTableQ = $xpath->query('descendant::table/tbody', $el->parentNode);
                                        foreach ($specTableQ as $item) {
                                            $specTable = $item;
                                            break 3;
                                        }
                                    }
                                }
                            }
                        }
                        if ($specTable) {
                            $details = $this->getInvoiceDetails($invoiceNumber, $token);
                            if ($details) {
                                $tr = null;
                                $trQ = $xpath->query('descendant::tr', $specTable);
                                if ($trQ && $trQ[0]) {
                                    $tr = $trQ[0];
                                }
                                if ($tr) {
                                    foreach ($details as $row) {
                                        $newTr = $tr->cloneNode(true);
                                        $tdQ = $xpath->query('descendant::td', $newTr);
                                        foreach ($tdQ as $td) {
                                            if (method_exists($td, 'getAttribute')) {
                                                $prop = $td->getAttribute('data-bind');
                                                $tmp = explode(':', $prop);
                                                if (isset($tmp[1])) {
                                                    $type = strtolower(trim($tmp[0]));
                                                    $propName = trim($tmp[1]);
                                                    $val = false;
                                                    foreach (array_keys($row) as $array_key) {
                                                        if (strtolower($array_key)==strtolower($propName)) {
                                                            if ($type=='text') {
                                                                $val = $row[$array_key];
                                                            } else if ($type=='decimalastext') {
                                                                $val = number_format((float)$row[$array_key], 2, ',', '.');
                                                            } else {
                                                                $val = $row[$array_key];
                                                            }
                                                        }
                                                    }
                                                    if ($val!==false) {
                                                        $td->textContent = $val;
                                                    }
                                                }
                                            }
                                        }
                                        $specTable->append($newTr);
                                    }
                                }
                            }
                        }
                    }
                    $mpdf = new \Mpdf\Mpdf();
                    $html = $this->getInnerHTML($PrintInvoice);
                    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
                    return $mpdf;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Returns QR code URL
     *
     * @return string URL | false
     */
    public function getURL() {
        return $this->url;
    }

    private function getInnerHTML($node) {
        $innerHTML= '';
        $this->inheritNodeStyles($node);
        $children = $node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $child->ownerDocument->saveXML( $child );
        }
        return $innerHTML;
    }

    private function inheritNodeStyles($node) {
        if (method_exists($node, 'getAttribute')) {
            $nodefontSize = $this->getFontSize($node);
            if ($nodefontSize) {
                $this->replaceFontSize($node, $nodefontSize);
            } else if (property_exists($node, 'parentNode') && $node->parentNode) {
                $parentFontSize = $this->getFontSize($node->parentNode);
                if ($parentFontSize) {
                    $this->replaceFontSize($node, $parentFontSize);
                }
            }
            foreach ($node->childNodes as $child) {
                $this->inheritNodeStyles($child);
            }
        }
    }

    private function getFontSize($node) {
        $s = null;
        if (method_exists($node, 'getAttribute')) {
            $s = $node->getAttribute('style');
        }
        if (!$s) {
            return null;
        }
        $m = [];
        $fontSize = '';
        if (preg_match("/font\\-size:\\s*([0-9.]*(pt|px))/", $s, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function replaceFontSize($node, $newFontSize) {
        $s = null;
        $coef = strpos($newFontSize, 'pt')?1:$this->pdfFontCoef;
        $newFontSize = ((float)$newFontSize*$coef).'pt';
        if (method_exists($node, 'getAttribute')) {
            $s = $node->getAttribute('style');
        }
        if (!$s) {
            $node->setAttribute('style', 'font-size: '.$newFontSize);
            return;
        }
        $s = str_replace('border-bottom: solid DarkGray 1px', 'border-bottom: 1px solid DarkGray', $s);
        if ($this->getFontSize($node)) {
            $node->setAttribute('style', preg_replace("/font\\-size:\\s.*(px|pt|;)(.*)/", 'font-size: '.$newFontSize.'; $2', $s));
        } else {
            $node->setAttribute('style', str_replace(';;',';', $s.'; font-size: '.$newFontSize));
        }
    }

    private function getInvoiceDetails($invoiceNumber, $token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_URL, 'https://suf.purs.gov.rs/specifications');
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['invoiceNumber' => $invoiceNumber, 'token' => $token]);
        $result=curl_exec($ch);
        if ($result) {
            $json = json_decode($result, true, JSON_UNESCAPED_UNICODE);
            if ($json && $json['success']) {
                return $json['items'];
            }
        }
        return false;
    }

}
