<?php 
namespace Metadocx\Reporting\Converters\PDF;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PDFConverter {

    protected $_sConverterTool = "wkhtmltopdf";
    protected $_sInputHTML = null;
    protected $_sInputFileName = null;
    protected $_sOutputFileName = null;
    protected $_aOptions = [];
    protected $_aStringAttributes = [
        "header-left", "header-center", "header-right",
        "footer-left", "footer-center", "footer-right"
    ];

    protected $_aSizeAttributes = [
        "page-width", "page-height",
        "margin-top", "margin-bottom", "margin-left", "margin-right"
    ];

    public function convert($sContent) {

        
        $bDocker = false;

        /**
         * Prepare html input file
         */
        $this->prepareHTMLFile($sContent);
        

        /**
         * Prepare output file name
         */ 
        $sFileName = uniqid("PDF") . ".pdf";

        if ($bDocker) {
            $this->_sOutputFileName = "/tmp/data/" . $sFileName;
        } else {            
            $this->_sOutputFileName = storage_path("app/" . $sFileName);
        }
        
        
        switch ($this->_sConverterTool)  {           
            case "wkhtmltopdf":
                $this->wkhtmltopdf($bDocker);
                break;
        }

        /**
         * Return file name for download
         */
        if (file_exists($this->_sOutputFileName)) {
            return $this->_sOutputFileName;
        } else {
            return false;
        }

    }   

    
    /**
     * Use wkhtmltopdf to convert html to pdf
     */
    protected function wkhtmltopdf($bDocker = false) {

        if ($bDocker) {
            $sCommand = "wkhtmltopdf ";        
        } else {
            $sCommand = "/usr/local/bin/wkhtmltopdf ";        
        }
        
        if (array_key_exists("toc", $this->_aOptions)) {
            $sCommand .= "toc --xsl-style-sheet " . public_path("css/toc.xsl") . " ";
        }
        foreach($this->_aOptions as $name => $value) {
            
            Log::debug($name . " " . print_r($value, true));

            if ($name == "toc") {
                // Skip table of content
                continue;
            }
            
            if ($value === true || $value === false) {
                $sCommand .= "--" . $name;
            } elseif (in_array($name, $this->_aStringAttributes) ) {
                if ($value != "") {
                    $sCommand .= "--" . $name . "='" . $value . "'";
                } else {
                    continue;
                }
            } else {
                $sCommand .= "--" . $name . " " . $value;
            }
            
            if (in_array($name, $this->_aSizeAttributes)) {
                $sCommand .= "mm ";
            } else {
                $sCommand .= " ";
            }
            
        }

        if ($bDocker) {
            $sCommand .= "/tmp/data/" . basename($this->_sInputFileName) . " ";        
        } else {
            $sCommand .= $this->_sInputFileName . " ";        
        }
        
        $sCommand .= $this->_sOutputFileName;

       
        if ($bDocker) {

            //Log::debug("/usr/bin/docker run --rm -v " . storage_path("app") . ":/tmp/data metadocxpdf " . $sCommand . " 2>&1");
            exec("sudo /usr/bin/docker run --rm -v " . storage_path("app") . ":/tmp/data metadocxpdf " . $sCommand . " 2>&1", $output, $return_var);
            
            /**
             * Reset output file path
             */
            $this->_sOutputFileName = storage_path("app/" . basename($this->_sOutputFileName));

        } else {

            //Log::debug($sCommand);
            exec($sCommand, $output, $return_var);

        }

        /**
         * Remove temp html file
         */
        if (file_exists($this->_sInputFileName)) {
            unlink($this->_sInputFileName);
        }


    }

    /**
     * Create the html file from report html
     */
    protected function prepareHTMLFile($sContent) {


        $this->_sInputFileName = storage_path("app/" . uniqid("PDF") . ".html");

        $sPage = "<!DOCTYPE html>
                  <html lang=\"en\">
                    <head>
                        <meta charset=\"UTF-8\" />
                        <style>";
        $sPage .= file_get_contents("https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css");
        $sPage .= file_get_contents("https://cdn.jsdelivr.net/gh/metadocx/reporting@latest/dist/metadocx.min.css");        
        
        $sPage .= "     </style>                        
                    </head>
                    <body style=\"background-color:#fff;\">" . PHP_EOL;
        
        $sPage .= base64_decode($sContent);

        $sPage .= "</body>
                </html>";

        $nResult = file_put_contents($this->_sInputFileName, $sPage);
        
        
    }

    /**
     * Load options for pdf export
     */
    public function loadOptions($options) {

        $this->_aOptions = [];

        $this->print_media_type = true;            
        //$this->disable_smart_shrinking = true;
        //$this->dpi = 96;
        $this->zoom = 1.2;
        $this->orientation = $options["page"]["orientation"];
        if ($options["page"]["paperSize"] != "Custom") {
            $this->page_size = $options["page"]["paperSize"];        
        } else {
            $this->page_width = $options["page"]["width"];
            $this->page_height = $options["page"]["height"];
        }
        
        $this->margin_top = $options["page"]["margins"]["top"];
        $this->margin_bottom = $options["page"]["margins"]["bottom"];
        $this->margin_left = $options["page"]["margins"]["left"];
        $this->margin_right = $options["page"]["margins"]["right"];
        if ($this->toBool($options["grayscale"])) {
            $this->grayscale = true;
        }
        if (!$this->toBool($options["pdfCompression"])) {
            $this->no_pdf_compression = true;
        }
        if ($this->toBool($options["outline"])) {
            $this->outline = true;
        } else {
            $this->no_outline = true;
        }
        if ($this->toBool($options["backgroundGraphics"])) {
            $this->background = true;
        } else {
            $this->no_background = true;
        }

        $bHasHeader = false;
        if ($options["header"]["left"] != "") {
            $this->header_left = $options["header"]["left"];
            $bHasHeader = true;
        }
        if ($options["header"]["center"] != "") {
            $this->header_center = $options["header"]["center"];
            $bHasHeader = true;
        }
        if ($options["header"]["right"] != "") {
            $this->header_right = $options["header"]["right"];
            $bHasHeader = true;
        }
        if ($bHasHeader) {
            if ($this->toBool($options["header"]["displayHeaderLine"])) {
                $this->header_line = true;
            } else {
                $this->no_header_line = true;
            }
        }

        $bHasFooter = false;
        if ($options["footer"]["left"] != "") {
            $this->footer_left = $options["footer"]["left"];
            $bHasFooter = true;
        }
        if ($options["footer"]["center"] != "") {
            $this->footer_center = $options["footer"]["center"];
            $bHasFooter = true;
        }
        if ($options["footer"]["right"] != "") {
            $this->footer_right = $options["footer"]["right"];
            $bHasFooter = true;
        }
        if ($bHasFooter) {
            if ($this->toBool($options["footer"]["displayFooterLine"])) {
                $this->footer_line = true;
            } else {
                $this->no_footer_line = true;
            }        
        }

    }

    public function __get(string $name) {
        $name = str_replace("_", "-", $name);
        return $this->_aOptions[$name];
    }

    public function __set(string $name, mixed $value) {
        $name = str_replace("_", "-", $name);
        $this->_aOptions[$name] = $value;
        return $this;
    }

    /**
     * Convert values to bool
     */
    private function toBool($value) {
        if (is_bool($value)) {
            return (bool) $value;
        }

        $value = strtolower(trim($value));

        $aTrueValues = ["1","y","o","yes","true","oui","vrai","on","checked",true,1];
        
        if (in_array($value, $aTrueValues, true)) {
            return true;
        } else {
            return false;
        }
    }

}