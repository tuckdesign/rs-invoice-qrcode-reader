<?php

namespace tuckdesign;

class RSQRCodeReader
{

    /**
     * Reads QR code data from pdf defined by path
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json', 'Accept: application/json'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result=curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $ret = json_decode($result, true, JSON_UNESCAPED_UNICODE);
            if ($ret) {
                return $ret;
            }
        }
        return false;
    }
}
