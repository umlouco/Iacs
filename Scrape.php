<?php

namespace MarioFlores\Iacs;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use ZipArchive;

class Scrape {

    private $client;
    public $proxys;
    public $errors;
    public $path;
    private $links;
    public $vessles;

    public function getVessles() {
        $this->getZipLinks();
        $this->saveZip();
        $this->clean();
        return $this->vessles;
    }

    public function clean() {
        $clean = array();
        if (!empty($this->vessles)) {
            foreach ($this->vessles as $vessle) {
                if (is_numeric($vessle[0])) {
                    $clean[$vessle[0]] = $vessle;
                }
            }
        }
        $this->vessles = $clean; 
    }

    public function getZipLinks() {
        try {
            $this->setGuzzle();
            $response = $this->client->request("GET", "http://www.iacs.org.uk/ship-company-data/vessels-in-class/");
            $html = new Crawler($response->getBody()->getContents());
            $links = $html->filter('a')->each(function ($node, $i) {
                return $node->attr('href');
            });
            $this->links = array_filter($links, function($v) {
                if (strpos($v, '.zip') !== false) {
                    return $v;
                }
            });
        } catch (Exception $ex) {
            $this->errors[] = $ex->getMessage();
        }
    }

    public function saveZip() {
        if (empty($this->path)) {
            $this->errors[] = 'Path is not set';
            exit;
        }

        if (!empty($this->links)) {
            foreach ($this->links as $link) {
                $this->client->get('http://www.iacs.org.uk' . $link, ['save_to' => $this->path]);
                $this->extractZip();
            }
        }
    }

    public function extractZip() {
        $zip = new ZipArchive;
        $zip->open($this->path);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (strpos($zip->getNameIndex($i), 'Equasis') !== false) {
                $file = $zip->getFromIndex($i);
                $rows = explode(PHP_EOL, $file);
                foreach ($rows as $row) {
                    $this->vessles[] = explode(';', $row);
                }
            }
        }
    }

    public function setGuzzle() {
        $this->setHeaders();
        $this->client = new Client([
            'headers' => $this->setHeaders(),
            'timeout' => 60,
            'cookies' => new \GuzzleHttp\Cookie\CookieJar,
            'http_errors' => false,
            'allow_redirects' => true
        ]);
    }

    private function setHeaders() {
        return [
            'User-Agent' => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0",
            'Accept-Language' => "en-US,en;q=0.5"
        ];
    }

}
