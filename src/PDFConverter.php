<?php 
namespace Metadocx\Reporting\Converters\PDF;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PDFConverter {

    /**
     * Tool to use to convert htl to PDF
     *
     * @var string
     */
    protected $_sConverterTool = "wkhtmltopdf";
    protected $_sInputHTML = null;
    /**
     * Cover page html file name
     *
     * @var [type]
     */
    protected $_sInputCoverPageFileName = null;
    /**
     * Main content html file name
     *
     * @var [type]
     */
    protected $_sInputFileName = null;
    /**
     * Output cover page (pdf) file name
     *
     * @var [type]
     */
    protected $_sOutputCoverPageFileName = null;
    /**
     * Output main content (pdf) file name
     *
     * @var [type]
     */
    protected $_sOutputContentFileName = null;
    /**
     * Final merged pdf file name
     *
     * @var [type]
     */
    protected $_sOutputFileName = null;
    /**
     * If we export each page as an image
     *
     * @var boolean
     */
    protected $_bConvertToImages = false;
    /**
     * Temporary folder where files will be generated
     *
     * @var [type]
     */
    protected $_sTempPath = null;
    /**
     * If document has a cover page
     *
     * @var boolean
     */
    protected $_bHasCoverPage = false;    
    /**
     * Coversion tool parameters
     *
     * @var array
     */
    protected $_aOptions = [];
    
    /**
     * wkhtmltopdf global options, must be inserted at start of command line
     *
     * @var array
     */
    protected $_aGlobalOptions = [
        "collate", "no-collate", "cookie-jar", "copies", "dpi",
        "extended-help", "grayscale", "help", "htmldoc", "image-dpi",
        "image-quality", "license", "log-level", "lowquality", "manpage",
        "margin-bottom", "margin-left", "margin-right", "margin-top",
        "orientation", "page-height", "page-size", "page-width", "no-pdf-coompression",
        "quiet", "read-args-from-stdin", "readme", "title", "use-xserver", "version"
    ];

    /**
     * wkhtmltopdf toc options
     *
     * @var array
     */
    protected $_aOutlineOptions = [
        "dump-default-toc-xsl", "dump-outline", "outline", "no-outline", "outline-depth"        
    ];

    /**
     * wkhtmltopdf page options
     *
     * @var array
     */
    protected $_aPageOptions = [
        "allow", "background","no-background","bypass-proxy-for","cache-dir","checkbox-checked-svg",
        "checkbox-svg","cookie","custom-header","custom-header-propagation","no-custom-header-propagation",
        "debug-javascript","no-debug-javascript","default-header","encoding","disable-external-links",
        "enable-external-links","disable-forms","enable-forms","images","no-images","disable-internal-links",
        "enable-internal-links","disable-javascript","enable-javascript","javascript-delay","keep-relative-links",
        "load-error-handling","load-media-error-handling","disable-local-file-access","enable-local-file-access",
        "minimum-font-size","exclude-from-outline","include-in-outline","page-offset","password","disable-plugins",
        "enable-plugins","post","post-file","print-media-type","no-print-media-type","proxy","proxy-hostname-lookup",
        "radiobutton-checked-svg","radiobutton-svg","resolve-relative-links","run-script","disable-smart-shrinking",
        "enable-smart-shrinking","ssl-crt-path","ssl-key-password","ssl-key-path","stop-slow-scripts",
        "no-stop-slow-scripts","disable-toc-back-links","enable-toc-back-links","user-style-sheet","username",
        "viewport-size","window-status","zoom",
    ];

    /**
     * wkhtmltopdf options that must be processed as strings
     *
     * @var array
     */
    protected $_aStringAttributes = [
        "header-left", "header-center", "header-right",
        "footer-left", "footer-center", "footer-right"
    ];

    /**
     * wkhtmltopdf options that must be processed as sizes append mm
     *
     * @var array
     */
    protected $_aSizeAttributes = [
        "page-width", "page-height",
        "margin-top", "margin-bottom", "margin-left", "margin-right"
    ];

    /**
     * Check if option is a global option
     *
     * @param [type] $sOptionName
     * @return boolean
     */
    protected function isGlobalOption($sOptionName) {
        return \in_array($sOptionName, $this->_aGlobalOptions);
    }

    /**
     * Check if option is a page option
     *
     * @param [type] $sOptionName
     * @return boolean
     */
    protected function isPageOption($sOptionName) {
        return \in_array($sOptionName, $this->_aPageOptions);
    }

    /**
     * Indicates if we must convert pdf to images
     *
     * @return void
     */
    public function getConvertToImages() {
        return $this->_bConvertToImages;
    }

    /**
     * Indicates if we must convert pdf to images
     *
     * @param [type] $bConvert
     * @return void
     */
    public function setConvertToImages($bConvert) {
        $this->_bConvertToImages = $this->toBool($bConvert);
        return $this;
    }

    /**
     * Convert HTML to PDF
     *
     * @param [type] $sCoverPage
     * @param [type] $sContent
     * @return void
     */
    public function convert($sCoverPage, $sContent) {

        /**
         * Indicates if we use docker to run conversion tool
         */
        $bDocker = false;

        /**
         * Prepare html input files 
         */
        $this->prepareHTMLFile($sCoverPage, $sContent);       

        /**
         * Prepare output file name
         */ 

        /**
         * Create a temporary folder to save temp files
         */
        $this->_sTempPath = uniqid("PDF");       
        /**
         * Set unique file names for temp files
         */ 
        $sCoverPageFileName = uniqid("PDFCover") . ".pdf";
        $sContentFileName = uniqid("PDFContent") . ".pdf";
        $sFileName = uniqid("PDF") . ".pdf";
        
        if ($bDocker) {
            /**
             * Fix path for docker, /tmp volume will be mounted to temp path 
             */
            $this->_sOutputCoverPageFileName = "/tmp/data/" . $sCoverPageFileName;
            $this->_sOutputContentFileName = "/tmp/data/" . $sContentFileName;
            $this->_sOutputFileName = "/tmp/data/" . $sFileName;
        } else {     
            /**
             * Create temp path folder
             */            
            mkdir(storage_path("app/" . $this->_sTempPath));
            $this->_sOutputCoverPageFileName = storage_path("app/" . $this->_sTempPath . "/" . $sCoverPageFileName);
            $this->_sOutputContentFileName = storage_path("app/" . $this->_sTempPath . "/" . $sContentFileName);
            $this->_sOutputFileName = storage_path("app/" . $this->_sTempPath . "/" . $sFileName);
        }
        
        /**
         * Call conversion tool
         */
        switch ($this->_sConverterTool)  {           
            case "wkhtmltopdf":
                $this->wkhtmltopdf($bDocker);
                break;
        }
    
        /**
         * Convert generated PDF file to images
         */
        $aPages = null;
        if ($this->getConvertToImages()) {
            $aPages = $this->convertPDFToImages();
        }

        $this->cleanUp();

        /**
         * Return file name or image array for download
         */
        if ($aPages !== null && is_array($aPages)) {
            return $aPages;
        } elseif (file_exists($this->_sOutputFileName)) {
            return $this->_sOutputFileName;
        } else {
            return false;
        }

    }   

    protected function appendWkhtmltopdfCommandLineArguments($bGlobal = false, $bPage = false, $bNoMargins = false) {

        $sCommand = "";

        foreach($this->_aOptions as $name => $value) {
         
            if ($bGlobal && !$this->isGlobalOption($name)) {
                continue;
            }

            if ($bPage && !$this->isPageOption($name)) {
                continue;
            }
                        
            /**
             * For cover page replace margins to 0
             */
            if ($bNoMargins && in_array($name, $this->_aSizeAttributes)) {
                $value = 0;
            }

            if ($name == "toc") {
                // Skip table of content
                continue;
            } elseif( $name == "zoom") {                
                $value = "1.25";                
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

        return $sCommand;

    }


    protected function wkhtmltopdfCoverPage($bDocker = false) {

        /**
         * Check if we have a cover page if not exit
         */
        if (!$this->_bHasCoverPage) {
            return null;
        }

        /**
         * Build wkhtmltopdf command
         */
        if ($bDocker) {
            $sCommand = "wkhtmltopdf ";        
        } else {
            $sCommand = "/usr/local/bin/wkhtmltopdf ";        
        }
                
        /**
         * Global options
         */
        $sCommand .= $this->appendWkhtmltopdfCommandLineArguments(true, false, true);        

        if ($bDocker) {
            $sCommand .= "/tmp/data/" . basename($this->_sInputCoverPageFileName) . " ";        
        } else {
            $sCommand .= $this->_sInputCoverPageFileName . " ";        
        }
        
        /**
         * Page options
         */
        $sCommand .= $this->appendWkhtmltopdfCommandLineArguments(false, true, true);   
        
        $sCommand .= $this->_sOutputCoverPageFileName;

       
        if ($bDocker) {

            exec("sudo /usr/bin/docker run --rm -v " . storage_path("app") . ":/tmp/data metadocxpdf " . $sCommand . " 2>&1", $output, $return_var);
            
            /**
             * Reset output file path
             */
            $this->_sOutputFileName = storage_path("app/" . basename($this->_sOutputFileName));

        } else {

            Log::debug($sCommand);
            exec($sCommand, $output, $return_var);

        }


    }

    /**
     * Generate main content 
     *
     * @param boolean $bDocker
     * @return void
     */
    protected function wkhtmltopdfContent($bDocker = false) {

        if ($bDocker) {
            $sCommand = "wkhtmltopdf ";        
        } else {
            $sCommand = "/usr/local/bin/wkhtmltopdf ";        
        }
                
        /**
         * Global options
         */
        $sCommand .= $this->appendWkhtmltopdfCommandLineArguments(true, false);       
                  
        /**
         * Table of content
         */
        if (array_key_exists("toc", $this->_aOptions)) {
            $sCommand .= "toc --xsl-style-sheet " . public_path("css/toc.xsl") . " ";
        }
        

        /**
         * Page 
         */
        if ($bDocker) {
            $sCommand .= "/tmp/data/" . basename($this->_sInputFileName) . " ";        
        } else {
            $sCommand .= $this->_sInputFileName . " ";        
        }
        
         /**
         * Page options
         */
        $sCommand .= $this->appendWkhtmltopdfCommandLineArguments(false, true);  
        
        $sCommand .= $this->_sOutputContentFileName;

       
        if ($bDocker) {

            //Log::debug("/usr/bin/docker run --rm -v " . storage_path("app") . ":/tmp/data metadocxpdf " . $sCommand . " 2>&1");
            exec("sudo /usr/bin/docker run --rm -v " . storage_path("app") . ":/tmp/data metadocxpdf " . $sCommand . " 2>&1", $output, $return_var);
            
            /**
             * Reset output file path
             */
            $this->_sOutputFileName = storage_path("app/" . basename($this->_sOutputFileName));

        } else {

            Log::debug($sCommand);
            exec($sCommand, $output, $return_var);

        }


    }

    /**
     * Merge cover page an main content
     *
     * @param boolean $bDocker
     * @return void
     */
    protected function wkhtmltopdfUnite($bDocker = false) {

        $aFiles = [];
        if (file_exists($this->_sOutputCoverPageFileName)) {
            
            // Take only first page of PDF
            $sCommand = "sudo /usr/bin/pdfseparate -f 1 -l 1 " . $this->_sOutputCoverPageFileName . " " . $this->_sOutputCoverPageFileName . ".p1.pdf";
            Log::debug($sCommand);
            exec($sCommand, $output, $return_var);

            if (file_exists($this->_sOutputCoverPageFileName . ".p1.pdf")) {
                $aFiles[] = $this->_sOutputCoverPageFileName . ".p1.pdf";
            } else {
                $aFiles[] = $this->_sOutputCoverPageFileName;
            }

        }
        if (file_exists($this->_sOutputContentFileName)) {
            $aFiles[] = $this->_sOutputContentFileName;
        }

        if (count($aFiles) > 1) {
            $sCommand = "sudo /usr/bin/pdfunite " . implode(" ", $aFiles) . " " . $this->_sOutputFileName;

            Log::debug($sCommand);
            exec($sCommand, $output, $return_var);
        } elseif (count($aFiles)==1) {
            /**
             * Only one file, use this file as the final pdf             
             */
            copy($aFiles[0], $this->_sOutputFileName);
        }
   

    }
    
    /**
     * Use wkhtmltopdf to convert html to pdf
     */
    protected function wkhtmltopdf($bDocker = false) {

        /**
         * Create a separate pdf file for the cover page (if one is available)
         * This is done to manage the margins
         */
        $this->wkhtmltopdfCoverPage($bDocker);
        /**
         * Create main document pdf file
         */
        $this->wkhtmltopdfContent($bDocker);
        /**
         * Merge cover page and content into one final pdf file
         */
        $this->wkhtmltopdfUnite($bDocker);
        

    }

    /**
     * Convert each page of a PDF file in images
     * Returns array of base64 encoded images
     *
     * @return void
     */
    protected function convertPDFToImages() {
        
        $aPages = [];
        if (file_exists($this->_sOutputFileName)) {

            $aPathInfo = pathinfo($this->_sOutputFileName);
        
            exec("sudo /usr/bin/pdftoppm -png " . $this->_sOutputFileName . " " . $aPathInfo["dirname"] . "/PDF", $output, $return_var);
                                   
            foreach (glob($aPathInfo["dirname"] . "/*.png") as $filename) {
                $aPages[] = "data:image/png;base64," . base64_encode(file_get_contents($filename));                
            }
        }

        return $aPages;
    }

    /**
     * Create the html files from report html and cover page
     */
    protected function prepareHTMLFile($sCoverPage, $sContent) {

        $this->_sInputCoverPageFileName = storage_path("app/" . uniqid("PDF") . ".html");
        $this->_sInputFileName = storage_path("app/" . uniqid("PDF") . ".html");

        /**
         * Cover page
         */
        if ($this->_bHasCoverPage) {

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
            
            $sPage .= base64_decode($sCoverPage);

            $sPage .= "</body>
                    </html>";

            $nResult = file_put_contents($this->_sInputCoverPageFileName, $sPage);

        }

        /**
         * Report 
         */
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
     * Clean up temp files
     *
     * @return void
     */
    public function cleanUp() {

    }

    /**
     * Load options for pdf export
     */
    public function loadOptions($options) {

        $this->_aOptions = [];

        $this->_bHasCoverPage = $this->toBool($options["coverpage"]);

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

    /**
     * Default getter
     *
     * @param string $name
     * @return void
     */
    public function __get(string $name) {
        $name = str_replace("_", "-", $name);
        return $this->_aOptions[$name];
    }

    /**
     * Default setter
     *
     * @param string $name
     * @param mixed $value
     */
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